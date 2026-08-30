<?php
/**
 * Andreani_Tracking_Sync
 * Sincronizacion de fondo del estado de tracking, sobre Action Scheduler. Mantiene
 * al dia los envios que NADIE esta mirando (el caso "cliente mirando" lo cubre el
 * Heartbeat de [[class-andreani-tracking-poll]]).
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Tracking_Sync {

	const HOOK          = 'andreani_tracking_sync';
	const GROUP         = 'andreani';
	const TICK          = 600;
	const BATCH         = 100;       // tope del endpoint bulk ByOrderNumbers.
	const BASE_INTERVAL = 600;       // intervalo de arranque del backoff.
	const MAX_INTERVAL   = 7200;     // tope del backoff (1 chequeo cada 2h) para detectar cambios mas rapido.
	const ABANDON_AFTER  = 2592000;  // sin cambios en 30 dias -> se cierra.
	const CHAIN_DELAY    = 60;       // pacing entre lotes encadenados: con cola grande drena mas lento en vez de saturar la DB.
	const LOCK_TTL       = 300;      // el lock de ejecucion se auto-libera si un run() muere sin limpiarlo.

	const NEXT_META     = '_andreani_poll_next';
	const INTERVAL_META = '_andreani_poll_interval';
	const CHANGE_META   = '_andreani_poll_last_change';
	const DONE_META     = '_andreani_poll_done';
	const TRACKING_META = '_order_andreani_tracking_number';
	const STATUS_META   = '_order_andreani_tracking_status';

	const ENABLED_OPTION = 'andreani_tracking_sync_enabled';
	const LOCK_TRANSIENT = 'andreani_tracking_sync_lock';

	// "No entregado" NO es terminal: la API puede revisitarlo a "Entregado"; lo cierra
	// el abandono por inactividad, no este array.
	const TERMINAL = array( 'entregado' );

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );

		if ( defined( 'ANDREANI_PLUGIN_FILE' ) ) {
			register_deactivation_hook( ANDREANI_PLUGIN_FILE, array( __CLASS__, 'unschedule' ) );
		}
	}

	/**
	 * Limpieza al desactivar el plugin: saca el tick recurrente para no dejar jobs huerfanos.
	 */
	public static function unschedule() {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * Agenda el tick recurrente una sola vez (idempotente). Si Action Scheduler no esta
	 * disponible (WooCommerce a medio cargar), no hace nada: se reintenta en el proximo init.
	 */
	public function maybe_schedule() {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( ! self::is_enabled() ) {
			return;
		}
		if ( as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
			return;
		}
		as_schedule_recurring_action( time() + self::TICK, self::TICK, self::HOOK, array(), self::GROUP );
	}

	/**
	 * El sync corre salvo que se lo apague explicitamente (toggle de la grilla). Default ON
	 * para no cambiar el comportamiento de las tiendas que ya lo tienen andando.
	 */
	public static function is_enabled() {
		return '0' !== get_option( self::ENABLED_OPTION, '1' );
	}

	/**
	 * Prende/apaga el sync y sincroniza el estado del scheduler (agenda o limpia el tick).
	 */
	public static function set_enabled( $enabled ) {
		update_option( self::ENABLED_OPTION, $enabled ? '1' : '0' );
		if ( $enabled ) {
			self::get_instance()->maybe_schedule();
		} else {
			self::unschedule();
		}
	}

	/**
	 * Punto de entrada del hook. Corta si el sync esta apagado y toma un lock para que dos
	 * ejecuciones no se solapen; el trabajo real esta en run_batch().
	 */
	public function run() {
		if ( ! self::is_enabled() ) {
			return;
		}

		// Un solo run() a la vez: evita que el tick recurrente y un lote encadenado se solapen
		// y vuelvan a presionar la DB en paralelo. El TTL lo auto-libera si un run() muere.
		if ( get_transient( self::LOCK_TRANSIENT ) ) {
			return;
		}
		set_transient( self::LOCK_TRANSIENT, 1, self::LOCK_TTL );

		try {
			$this->run_batch();
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
	}

	/**
	 * Procesa UN lote de hasta BATCH ordenes vencidas. Si el lote vino lleno (puede haber mas
	 * pendientes), encadena el proximo con un delay para drenar sin esperar al proximo tick.
	 */
	private function run_batch() {
		$order_ids = $this->due_order_ids();
		if ( empty( $order_ids ) ) {
			return;
		}

		$shipments = Andreani_Shipments_Api::get_instance()->lookup_shipments( $order_ids );
		if ( is_wp_error( $shipments ) ) {
			Andreani_Utils::andreani_log( '[TRACKING_SYNC] lookup fallo: ' . $shipments->get_error_message(), 'warning' );
			return;
		}

		$by_order = array();
		if ( is_array( $shipments ) ) {
			foreach ( $shipments as $s ) {
				if ( isset( $s['salesOrderNumber'] ) ) {
					$by_order[ (string) $s['salesOrderNumber'] ] = $s;
				}
			}
		}

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$this->apply( $order, isset( $by_order[ (string) $order_id ] ) ? $by_order[ (string) $order_id ] : null );
		}

		// Lote lleno => probablemente queda mas cola. El proximo lote se encadena con un delay
		// (no async inmediato): con cola grande el sync avanza mas lento pero no satura la DB.
		if ( count( $order_ids ) >= self::BATCH && function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + self::CHAIN_DELAY, self::HOOK, array(), self::GROUP );
		}
	}

	/**
	 * Ordenes a chequear: envio creado, no cerradas, y con el proximo chequeo vencido
	 * (o sin agendar todavia). Se ordena por fecha y NO por _andreani_poll_next: ordenar
	 * por esa meta excluiria justo a las ordenes que aun no la tienen (las nuevas). El
	 * loopback drena la cola entera, asi que ningun envio queda sin chequear.
	 *
	 * Consulta directa a la tabla de meta segun el store (HPOS o posts). NO usa el argumento
	 * `meta_query` de wc_get_orders: bajo el store CPT (posts) dispara wc_doing_it_wrong desde
	 * WC 9.2, y en el sync de fondo eso derivaba en un bucle que saturaba la DB.
	 *
	 * @return int[]
	 */
	private function due_order_ids() {
		global $wpdb;

		// Identificadores de tabla/columna de este whitelist, nunca de input => interpolarlos es seguro.
		if ( Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			$orders   = "{$wpdb->prefix}wc_orders";
			$meta     = "{$wpdb->prefix}wc_orders_meta";
			$id_col   = 'id';
			$fk_col   = 'order_id';
			$type_col = 'type';
			$date_col = 'date_created_gmt';
		} else {
			$orders   = $wpdb->posts;
			$meta     = $wpdb->postmeta;
			$id_col   = 'ID';
			$fk_col   = 'post_id';
			$type_col = 'post_type';
			$date_col = 'post_date_gmt';
		}

		$sql = $wpdb->prepare(
			"SELECT o.{$id_col} FROM {$orders} o
			 WHERE o.{$type_col} = 'shop_order'
			   AND EXISTS     (SELECT 1 FROM {$meta} mc  WHERE mc.{$fk_col}  = o.{$id_col} AND mc.meta_key  = '_order_andreani_created' AND mc.meta_value = '1')
			   AND NOT EXISTS (SELECT 1 FROM {$meta} md  WHERE md.{$fk_col}  = o.{$id_col} AND md.meta_key  = %s)
			   AND ( NOT EXISTS (SELECT 1 FROM {$meta} mn  WHERE mn.{$fk_col}  = o.{$id_col} AND mn.meta_key  = %s)
			         OR EXISTS  (SELECT 1 FROM {$meta} mn2 WHERE mn2.{$fk_col} = o.{$id_col} AND mn2.meta_key = %s AND CAST(mn2.meta_value AS UNSIGNED) <= %d) )
			 ORDER BY o.{$date_col} ASC
			 LIMIT %d",
			self::DONE_META,
			self::NEXT_META,
			self::NEXT_META,
			time(),
			self::BATCH
		);

		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Aplica el resultado de la API a una orden y reprograma su proximo chequeo (backoff).
	 *
	 * @param WC_Order   $order
	 * @param array|null $shipment ShipmentDto de la API, o null si no vino en el bulk.
	 */
	private function apply( $order, $shipment ) {
		$now        = time();
		$prev_track = (string) $order->get_meta( self::TRACKING_META, true );
		$prev_state = (string) $order->get_meta( self::STATUS_META, true );

		$track = ( $shipment && isset( $shipment['trackingNumber'] ) ) ? trim( (string) $shipment['trackingNumber'] ) : '';
		$state = ( $shipment && isset( $shipment['trackingStatus'] ) ) ? trim( (string) $shipment['trackingStatus'] ) : '';

		$changed = false;
		if ( '' !== $track && $track !== $prev_track ) {
			$order->update_meta_data( self::TRACKING_META, $track );
			$changed = true;
		}
		if ( '' !== $state && $state !== $prev_state ) {
			$order->update_meta_data( self::STATUS_META, $state );
			$changed = true;
		}

		if ( $changed ) {
			$interval = self::BASE_INTERVAL;
			$order->update_meta_data( self::CHANGE_META, $now );
		} else {
			$prev_interval = (int) $order->get_meta( self::INTERVAL_META, true );
			$interval      = $prev_interval > 0 ? min( $prev_interval * 2, self::MAX_INTERVAL ) : self::BASE_INTERVAL;
		}

		$last_change = (int) $order->get_meta( self::CHANGE_META, true );
		if ( $last_change <= 0 ) {
			$created     = $order->get_date_created();
			$last_change = $created ? $created->getTimestamp() : $now;
			$order->update_meta_data( self::CHANGE_META, $last_change );
		}

		$done = $this->is_terminal( '' !== $state ? $state : $prev_state )
			|| ( $now - $last_change ) > self::ABANDON_AFTER;

		if ( $done ) {
			$order->update_meta_data( self::DONE_META, 1 );
			$order->delete_meta_data( self::NEXT_META );
		} else {
			$order->update_meta_data( self::INTERVAL_META, $interval );
			$order->update_meta_data( self::NEXT_META, $this->next_run( $now, $interval ) );
		}

		$order->save();
	}

	/**
	 * Proximo chequeo con jitter +-15% para des-sincronizar lotes que se crearon juntos.
	 */
	private function next_run( $now, $interval ) {
		$spread = (int) round( $interval * 0.15 );
		$jitter = $spread > 0 ? wp_rand( -$spread, $spread ) : 0;
		return $now + max( 60, $interval + $jitter );
	}

	private function is_terminal( $state ) {
		return in_array( strtolower( trim( (string) $state ) ), self::TERMINAL, true );
	}
}
