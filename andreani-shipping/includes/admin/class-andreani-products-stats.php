<?php
/**
 * Clasificación del catálogo para la página "Productos": cómo viaja cada uno,
 * si le faltan medidas y los contadores de arriba.
 *
 * La clasificación se persiste como meta de cada producto para que listar,
 * filtrar y contar sea una consulta acotada y no una lectura del catálogo entero.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Products_Stats {

	const TRANSIENT = 'andreani_products_counts';
	const TTL       = 300;

	const META_CLASS = '_andreani_dispatch_class';
	const META_MODE  = '_andreani_dispatch_mode_class';

	const CLASS_PAQUETERIA = 'paqueteria';
	const CLASS_BIGGER     = 'bigger';
	const CLASS_MISSING    = 'missing';
	const CLASS_NA         = 'na';

	const FILTER_READY      = 'ready';
	const FILTER_MISSING    = 'missing';
	const FILTER_BIGGER     = 'bigger';
	const FILTER_APILADO    = 'apilado';
	const FILTER_MULTIBULTO = 'multibulto';

	const SERVICES = array( self::CLASS_PAQUETERIA, self::CLASS_BIGGER, self::CLASS_MISSING );
	const MODES    = array( Andreani_Product_Bultos::MODE_SINGLE, Andreani_Product_Bultos::MODE_APILADO, Andreani_Product_Bultos::MODE_MULTIBULTO );

	const BATCH_HOOK      = 'andreani_classify_batch';
	const BATCH_SIZE      = 200;
	const OPTION_VERSION  = 'andreani_classify_version';
	const OPTION_RUNNING  = 'andreani_classify_running';
	const VERSION         = '1';
	const CHILDREN_INLINE = 50;

	const PROGRESS_TRANSIENT = 'andreani_products_progress';
	const PROGRESS_TTL       = 15;

	private static $left_hint  = null;
	private static $pending    = array();
	private static $hooked_end = false;

	public static function register() {
		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'queue' ) );
		}

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'on_meta_change' ), 10, 3 );
		}

		foreach ( array( 'save_post_product', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'flush' ) );
		}

		foreach ( array( 'woocommerce_weight_unit', 'woocommerce_dimension_unit' ) as $option ) {
			add_action( 'update_option_' . $option, array( __CLASS__, 'restart' ) );
		}

		add_action( self::BATCH_HOOK, array( __CLASS__, 'run_batch' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_start' ) );
	}

	public static function flush() {
		delete_transient( self::TRANSIENT );
	}

	/**
	 * @return string[] Metas de producto cuyo cambio puede alterar la clasificación.
	 */
	public static function watched_meta_keys() {
		return array( '_weight', '_length', '_width', '_height', '_virtual', Andreani_Product_Bultos::META_KEY, Andreani_Product_Apilado::META_KEY );
	}

	public static function on_meta_change( $meta_id, $post_id, $meta_key ) {
		if ( in_array( $meta_key, self::watched_meta_keys(), true ) ) {
			self::queue( $post_id );
		}
	}

	public static function queue( $product_id ) {
		self::$pending[ (int) $product_id ] = true;

		if ( ! self::$hooked_end ) {
			self::$hooked_end = true;
			add_action( 'shutdown', array( __CLASS__, 'flush_pending' ) );
		}
	}

	/**
	 * Clasifica lo encolado en esta request. Los guardados del admin la llaman
	 * antes de responder: el shutdown corre después de enviar la respuesta.
	 */
	public static function flush_pending() {
		$ids           = array_keys( self::$pending );
		self::$pending = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );

			if ( ! $product ) {
				continue;
			}

			self::store( $product );

			if ( $product->is_type( 'variable' ) ) {
				self::refresh_children( $product );
			}
		}

		if ( $ids ) {
			self::flush();
		}
	}

	private static function refresh_children( $parent ) {
		$children = $parent->get_children();

		if ( count( $children ) > self::CHILDREN_INLINE ) {
			self::clear_metas( $children );
			self::start_backfill();
			return;
		}

		foreach ( $children as $child_id ) {
			$child = wc_get_product( $child_id );

			if ( $child ) {
				self::store( $child );
			}
		}
	}

	/**
	 * @param array $classification Resultado de classify().
	 * @return array{0:string,1:string} Valores de META_CLASS y META_MODE.
	 */
	public static function meta_values( array $classification ) {
		$class = array(
			'missing' => self::CLASS_MISSING,
			'bigger'  => self::CLASS_BIGGER,
		);

		return array(
			isset( $class[ $classification['state'] ] ) ? $class[ $classification['state'] ] : self::CLASS_PAQUETERIA,
			$classification['mode'],
		);
	}

	public static function store( $product ) {
		if ( $product->is_type( 'variable' ) || $product->is_virtual() ) {
			$values = array( self::CLASS_NA, Andreani_Product_Bultos::MODE_SINGLE );
		} else {
			$values = self::meta_values( self::classify_product( $product ) );
		}

		update_post_meta( $product->get_id(), self::META_CLASS, $values[0] );
		update_post_meta( $product->get_id(), self::META_MODE, $values[1] );
	}

	/**
	 * @return string Filtro válido o cadena vacía.
	 */
	public static function sanitize_filter( $filter ) {
		$filter = is_string( $filter ) ? $filter : '';

		return in_array( $filter, array( self::FILTER_READY, self::FILTER_MISSING, self::FILTER_BIGGER, self::FILTER_APILADO, self::FILTER_MULTIBULTO ), true )
			? $filter
			: '';
	}

	/**
	 * @param mixed    $values  Lista cruda del request.
	 * @param string[] $allowed Valores permitidos.
	 * @return string[] Solo los valores de la lista blanca, sin repetir.
	 */
	public static function sanitize_list( $values, array $allowed ) {
		if ( ! is_array( $values ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( $values, function ( $value ) use ( $allowed ) {
			return is_string( $value ) && in_array( $value, $allowed, true );
		} ) ) );
	}

	/**
	 * Valores de las metas que satisfacen un filtro; una clave vacía no restringe.
	 *
	 * @param string $filter Filtro ya saneado.
	 * @return array{classes:string[],mode:string}
	 */
	public static function filter_conditions( $filter ) {
		$conditions = array(
			'classes' => array(),
			'mode'    => '',
		);

		switch ( $filter ) {
			case self::FILTER_READY:
				$conditions['classes'] = array( self::CLASS_PAQUETERIA, self::CLASS_BIGGER );
				break;
			case self::FILTER_MISSING:
				$conditions['classes'] = array( self::CLASS_MISSING );
				break;
			case self::FILTER_BIGGER:
				$conditions['classes'] = array( self::CLASS_BIGGER );
				break;
			case self::FILTER_APILADO:
				$conditions['mode'] = Andreani_Product_Bultos::MODE_APILADO;
				break;
			case self::FILTER_MULTIBULTO:
				$conditions['mode'] = Andreani_Product_Bultos::MODE_MULTIBULTO;
				break;
		}

		return $conditions;
	}

	/**
	 * Estado y modo de un producto con los valores tal como están cargados en la
	 * tienda (peso y medidas en la unidad de la tienda; bultos en cm y gramos).
	 *
	 * @param mixed $weight  Peso.
	 * @param mixed $length  Largo.
	 * @param mixed $width   Ancho.
	 * @param mixed $height  Alto.
	 * @param array $bultos  Bultos adicionales.
	 * @param array $apilado Config de apilado cruda.
	 * @return array{state:string,mode:string,reason:string,value:float}
	 */
	public static function classify( $weight, $length, $width, $height, array $bultos, array $apilado ) {
		$weight_kg = (float) Andreani_Order_Mapper::convert_weight_to_unit( $weight, 'kg' );
		$width_cm  = (float) Andreani_Order_Mapper::convert_dimension_to_cm( $width );
		$height_cm = (float) Andreani_Order_Mapper::convert_dimension_to_cm( $height );
		$length_cm = (float) Andreani_Order_Mapper::convert_dimension_to_cm( $length );

		if ( ! empty( $bultos ) ) {
			$mode = Andreani_Product_Bultos::MODE_MULTIBULTO;
		} elseif ( Andreani_Product_Apilado::is_valid( $apilado ) ) {
			$mode = Andreani_Product_Bultos::MODE_APILADO;
		} else {
			$mode = Andreani_Product_Bultos::MODE_SINGLE;
		}

		if ( $weight_kg <= 0 || $width_cm <= 0 || $height_cm <= 0 || $length_cm <= 0 ) {
			return array(
				'state'  => 'missing',
				'mode'   => $mode,
				'reason' => '',
				'value'  => 0.0,
			);
		}

		$bigger = Andreani_Product_Bultos::bigger_from_values( $weight_kg, $width_cm, $height_cm, $length_cm, $bultos, $apilado );

		return array(
			'state'  => $bigger['is_bigger'] ? 'bigger' : 'ok',
			'mode'   => $mode,
			'reason' => $bigger['reason'],
			'value'  => $bigger['value'],
		);
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return array{state:string,mode:string,reason:string,value:float}
	 */
	public static function classify_product( $product ) {
		return self::classify(
			$product->get_weight(),
			$product->get_length(),
			$product->get_width(),
			$product->get_height(),
			Andreani_Package_Builder::resolve_bultos( $product ),
			Andreani_Package_Builder::resolve_apilado( $product )
		);
	}

	/**
	 * @param array $rows Filas de GROUP BY con class, mode y n.
	 * @return array<string,int> all, ready, missing, bigger, apilado y multibulto.
	 */
	public static function counts_from_rows( array $rows ) {
		$counts = array(
			'all'                   => 0,
			self::FILTER_READY      => 0,
			self::CLASS_PAQUETERIA  => 0,
			Andreani_Product_Bultos::MODE_SINGLE => 0,
			self::FILTER_MISSING    => 0,
			self::FILTER_BIGGER     => 0,
			self::FILTER_APILADO    => 0,
			self::FILTER_MULTIBULTO => 0,
		);

		foreach ( $rows as $row ) {
			$n = (int) $row['n'];

			if ( self::CLASS_NA === $row['class'] ) {
				continue;
			}

			$counts['all'] += $n;

			if ( self::CLASS_MISSING === $row['class'] ) {
				$counts[ self::FILTER_MISSING ] += $n;
			} else {
				$counts[ self::FILTER_READY ] += $n;
			}

			if ( self::CLASS_BIGGER === $row['class'] ) {
				$counts[ self::FILTER_BIGGER ] += $n;
			} elseif ( self::CLASS_PAQUETERIA === $row['class'] ) {
				$counts[ self::CLASS_PAQUETERIA ] += $n;
			}

			if ( Andreani_Product_Bultos::MODE_APILADO === $row['mode'] ) {
				$counts[ self::FILTER_APILADO ] += $n;
			} elseif ( Andreani_Product_Bultos::MODE_MULTIBULTO === $row['mode'] ) {
				$counts[ self::FILTER_MULTIBULTO ] += $n;
			} else {
				$counts[ Andreani_Product_Bultos::MODE_SINGLE ] += $n;
			}
		}

		return $counts;
	}

	/**
	 * Un GROUP BY sobre las metas persistidas; solo se cachean los números.
	 *
	 * @return array<string,int>
	 */
	public static function get_counts() {
		$cached = get_transient( self::TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT cls.meta_value AS class, md.meta_value AS mode, COUNT(*) AS n
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} cls ON cls.post_id = p.ID AND cls.meta_key = %s
				INNER JOIN {$wpdb->postmeta} md ON md.post_id = p.ID AND md.meta_key = %s
				WHERE p.post_type IN ('product', 'product_variation') AND p.post_status = 'publish'
				GROUP BY cls.meta_value, md.meta_value",
				self::META_CLASS,
				self::META_MODE
			),
			ARRAY_A
		); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$counts = self::counts_from_rows( is_array( $rows ) ? $rows : array() );

		set_transient( self::TRANSIENT, $counts, self::TTL );

		return $counts;
	}

	/**
	 * @return array{done:int,total:int}|null Avance del análisis, o null si terminó.
	 */
	public static function finish_small_backfill() {
		if ( 'yes' !== get_option( self::OPTION_RUNNING, 'no' ) ) {
			return;
		}

		global $wpdb;

		$left = (int) $wpdb->get_var( self::scope_sql( 'missing_meta' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $left <= self::BATCH_SIZE ) {
			self::run_batch();
			delete_transient( self::PROGRESS_TRANSIENT );
			return;
		}

		self::$left_hint = $left;
	}

	public static function progress() {
		if ( 'yes' !== get_option( self::OPTION_RUNNING, 'no' ) ) {
			return null;
		}

		$cached = get_transient( self::PROGRESS_TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$total = (int) $wpdb->get_var( self::scope_sql( '' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$left  = null !== self::$left_hint ? self::$left_hint : (int) $wpdb->get_var( self::scope_sql( 'missing_meta' ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::$left_hint = null;

		$progress = array(
			'done'  => max( 0, $total - $left ),
			'total' => $total,
		);

		set_transient( self::PROGRESS_TRANSIENT, $progress, self::PROGRESS_TTL );

		return $progress;
	}

	private static function scope_sql( $mode, $limit = 0 ) {
		global $wpdb;

		$select = 'ids' === $mode ? 'SELECT p.ID' : 'SELECT COUNT(*)';
		$where  = "p.post_type IN ('product', 'product_variation') AND p.post_status NOT IN ('trash', 'auto-draft')";

		if ( 'missing_meta' === $mode || 'ids' === $mode ) {
			$where .= $wpdb->prepare( " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = %s)", self::META_CLASS ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$sql = "{$select} FROM {$wpdb->posts} p WHERE {$where}";

		if ( 'ids' === $mode ) {
			$sql .= ' ORDER BY p.ID ASC LIMIT ' . (int) $limit;
		}

		return $sql;
	}

	public static function maybe_start() {
		if ( self::VERSION !== get_option( self::OPTION_VERSION, '' ) ) {
			update_option( self::OPTION_VERSION, self::VERSION, false );
			self::restart();
			return;
		}

		if ( 'yes' === get_option( self::OPTION_RUNNING, 'no' ) ) {
			self::schedule_batch();
		}
	}

	/**
	 * Descarta lo persistido y vuelve a analizar el catálogo en segundo plano.
	 */
	public static function restart() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", self::META_CLASS, self::META_MODE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		self::flush();
		self::start_backfill();
	}

	private static function clear_metas( array $ids ) {
		global $wpdb;

		$ids = array_map( 'intval', $ids );

		if ( ! $ids ) {
			return;
		}

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) AND post_id IN (" . implode( ',', $ids ) . ')', self::META_CLASS, self::META_MODE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
	}

	private static function start_backfill() {
		update_option( self::OPTION_RUNNING, 'yes', false );
		delete_transient( self::PROGRESS_TRANSIENT );
		self::schedule_batch();
	}

	private static function schedule_batch( $chained = false ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! wp_next_scheduled( self::BATCH_HOOK ) ) {
				wp_schedule_single_event( time(), self::BATCH_HOOK );
			}
			return;
		}

		// La acción en curso cuenta como programada: al encadenar hay que encolar igual.
		if ( $chained || ! as_has_scheduled_action( self::BATCH_HOOK, array(), 'andreani' ) ) {
			as_enqueue_async_action( self::BATCH_HOOK, array(), 'andreani' );
		}
	}

	/**
	 * Clasifica un lote de productos sin meta y se vuelve a encolar hasta terminar.
	 */
	public static function run_batch() {
		global $wpdb;

		$ids = $wpdb->get_col( self::scope_sql( 'ids', self::BATCH_SIZE ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		foreach ( $ids as $id ) {
			$product = wc_get_product( (int) $id );

			if ( $product ) {
				self::store( $product );
			} else {
				update_post_meta( (int) $id, self::META_CLASS, self::CLASS_NA );
				update_post_meta( (int) $id, self::META_MODE, Andreani_Product_Bultos::MODE_SINGLE );
			}
		}

		self::flush();
		delete_transient( self::PROGRESS_TRANSIENT );

		if ( count( $ids ) >= self::BATCH_SIZE ) {
			self::schedule_batch( true );
			return;
		}

		update_option( self::OPTION_RUNNING, 'no', false );
	}
}
