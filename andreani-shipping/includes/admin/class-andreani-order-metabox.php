<?php
/**
 * Metabox de Andreani en la pagina de pedido
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Order_Metabox {

	private static $instance = null;

	const METABOX_ID = 'andreani-shipping-metabox';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ), 10, 2 );
	}

	public function register_metabox( $post_type, $post_or_order ) {
		$order = $this->get_order_from_context( $post_type, $post_or_order );

		if ( ! $order || ! $this->order_has_andreani_shipping( $order ) ) {
			return;
		}

		$screen = $this->get_order_screen();

		$logo_url = ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani.png';
		$title    = sprintf(
			'<img src="%s" alt="Andreani" style="height:16px;vertical-align:middle;">',
			esc_url( $logo_url )
		);

		add_meta_box(
			self::METABOX_ID,
			$title,
			array( $this, 'render_metabox' ),
			$screen,
			'side',
			'high'
		);
	}

	private function get_order_from_context( $post_type, $post_or_order ) {
		if ( $post_or_order instanceof WC_Order ) {
			return $post_or_order;
		}

		if ( 'shop_order' === $post_type && $post_or_order instanceof WP_Post ) {
			return wc_get_order( $post_or_order->ID );
		}

		return null;
	}

	private function get_order_screen() {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return wc_get_page_screen_id( 'shop-order' );
		}
		return 'shop_order';
	}

	private function order_has_andreani_shipping( $order ) {
		foreach ( $order->get_shipping_methods() as $method ) {
			if ( ANDREANI_SHIPPING_METHOD_ID === $method->get_method_id() ) {
				return true;
			}
		}
		return false;
	}

	public function render_metabox( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );

		if ( ! $order ) {
			return;
		}

		$data             = $this->get_order_shipping_data( $order );
		$data['timeline'] = $this->build_timeline_steps( $data );

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/metabox-tracking.php';
	}

	/**
	 * Mini-timeline logístico para el sidebar. Replica la lógica del detail-view
	 * (rank_map + map_tracking_status) en versión compacta: 4 pasos
	 * (Pendiente de ingreso → En camino → [Listo para retirar si sucursal] → Entregado),
	 * resaltando el paso activo según el tracking_status real. Devuelve [] si todavía
	 * no hay tracking_status (el template oculta el bloque).
	 */
	private function build_timeline_steps( array $data ) {
		$tracking_status = isset( $data['tracking_status'] ) ? (string) $data['tracking_status'] : '';

		if ( '' === $tracking_status || ! class_exists( 'Andreani_Shipments_List' ) ) {
			return array();
		}

		$mapped       = Andreani_Shipments_List::map_tracking_status( $tracking_status );
		$rank_map     = array( 'pending_entry' => 1, 'in_transit' => 2, 'ready_pickup' => 3, 'delivered' => 4, 'not_delivered' => 4 );
		$current_rank = isset( $rank_map[ $mapped ] ) ? $rank_map[ $mapped ] : 1;
		$is_failed    = ( 'not_delivered' === $mapped );
		$is_sucursal  = ! empty( $data['is_sucursal'] );

		$step_defs = array(
			array( 'rank' => 1, 'label' => __( 'Ingr.', 'andreani-shipping' ) ),
			array( 'rank' => 2, 'label' => __( 'Camino', 'andreani-shipping' ) ),
		);
		if ( $is_sucursal ) {
			$step_defs[] = array( 'rank' => 3, 'label' => __( 'Retiro', 'andreani-shipping' ) );
		}
		$step_defs[] = array(
			'rank'  => 4,
			'label' => $is_failed ? __( 'No entr.', 'andreani-shipping' ) : __( 'Entreg.', 'andreani-shipping' ),
		);

		$steps = array();
		foreach ( $step_defs as $def ) {
			if ( $def['rank'] < $current_rank ) {
				$state = 'done';
			} elseif ( $def['rank'] === $current_rank ) {
				$state = $is_failed ? 'failed' : 'active';
			} else {
				$state = 'pending';
			}

			$steps[] = array(
				'label' => $def['label'],
				'state' => $state,
			);
		}

		return $steps;
	}

	private function get_order_shipping_data( $order ) {
		$order_id      = $order->get_id();
		$created       = (bool) $order->get_meta( '_order_andreani_created', true );
		$client_type   = $order->get_meta( '_order_andreani_client_type', true );
		$tracking      = $order->get_meta( '_order_andreani_tracking_number', true );
		$pedido_id     = $order->get_meta( '_order_andreani_pedido_id', true );
		$sucursal      = $order->get_meta( '_andreani_sucursal_nombre', true );
		$delivery_mode = (string) $order->get_meta( '_order_andreani_delivery_mode', true );
		$retry_count   = (int) $order->get_meta( '_andreani_retry_count', true );

		if ( empty( $sucursal ) ) {
			$sucursal = $order->get_meta( 'sucursal_andreani', true );
		}

		if ( empty( $client_type ) ) {
			$client_type = Andreani_Api_Manager::get_client_type();
		}

		$type_info        = Andreani_Client_Type::from_id( $client_type );
		$status           = 'error';
		$status_label     = __( 'Error al generar', 'andreani-shipping' );

		if ( $created ) {
			$status       = 'success';
			$status_label = __( 'Generado', 'andreani-shipping' );
		}

		$tracking_status       = (string) $order->get_meta( '_order_andreani_tracking_status', true );
		$tracking_status_label = '';
		$tracking_status_class = '';
		if ( '' !== $tracking_status && class_exists( 'Andreani_Shipments_List' ) ) {
			$has_payment_step      = $type_info ? $type_info->has_payment_step() : false;
			$mapped                = Andreani_Shipments_List::map_tracking_status( $tracking_status );
			$status_config         = Andreani_Shipments_List::get_shipping_status_config( $mapped, $has_payment_step );
			$tracking_status_label = $status_config['label'];
			$tracking_status_class = $status_config['class'];
		}

		$identifier       = $tracking;
		$identifier_label = __( 'Nro. Seguimiento', 'andreani-shipping' );

		if ( empty( $identifier ) && ! empty( $pedido_id ) ) {
			$numero_interno   = (string) $order->get_meta( '_order_andreani_numero_interno', true );
			$identifier       = '' !== $numero_interno
				? $numero_interno
				: '#' . $order->get_order_number() . '-' . $pedido_id;
			$identifier_label = __( 'ID Orden', 'andreani-shipping' );
		}

		$last_error      = $order->get_meta( '_andreani_last_error', true );
		$last_error_code = $order->get_meta( '_andreani_last_error_code', true );
		$last_error_body = $order->get_meta( '_andreani_last_error_body', true );

		$recipient_dni = $order->get_meta( '_shipping_dni', true );
		if ( empty( $recipient_dni ) ) {
			$recipient_dni = $order->get_meta( '_billing_dni', true );
		}

		$recipient_phone = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
		if ( '' === $recipient_phone ) {
			$recipient_phone = (string) $order->get_billing_phone();
		}

		$destination_city = (string) $order->get_shipping_city();
		if ( '' === $destination_city ) {
			$destination_city = (string) $order->get_billing_city();
		}

		$is_sucursal = false !== strpos( strtolower( $delivery_mode ), 'sucursal' );

		$data = array(
			'order_id'         => $order_id,
			'status'           => $status,
			'status_label'     => $status_label,
			'tracking_status'       => $tracking_status,
			'tracking_status_label' => $tracking_status_label,
			'tracking_status_class' => $tracking_status_class,
			'client_type'      => $client_type,
			'tracking_number'  => $tracking,
			'display_tracking' => $tracking,
			'pedido_id'        => $pedido_id,
			'identifier'       => $identifier,
			'identifier_label' => $identifier_label,
			'sucursal'         => $sucursal,
			'delivery_mode'    => $delivery_mode,
			'is_sucursal'      => $is_sucursal,
			'destination_city' => $destination_city,
			'can_retry'        => ! $created,
			'can_download'     => $type_info && $type_info->supports_label_pdf() && ! empty( $tracking ),
			'created'          => $created,
			'last_error'       => $last_error,
			'last_error_code'  => $last_error_code,
			'last_error_body'  => $last_error_body,
			'recipient_name'     => $order->get_billing_first_name(),
			'recipient_lastname' => $order->get_billing_last_name(),
			'recipient_phone'    => $recipient_phone,
			'recipient_email'    => $order->get_billing_email(),
			'recipient_dni'      => (string) $recipient_dni,
		);

		return $this->maybe_hydrate_from_api( $order, $data );
	}

	private function maybe_hydrate_from_api( $order, array $data ) {
		$data['source']         = 'wc';
		$data['api_failure']    = false;
		$data['api_updated_at'] = '';

		if ( ! $this->should_hydrate( $order ) ) {
			return $data;
		}

		static $cache = array();
		$key = $order->get_id();

		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = $this->fetch_api_data( (string) $key );
		}

		$merged = array_merge( $data, $cache[ $key ] );

		// Pyme/MM no persisten el tracking_status en el meta (vive en la API): si la
		// hidratación lo trajo, recalculamos el label/clase del estado logístico.
		if ( ! empty( $merged['tracking_status'] ) && class_exists( 'Andreani_Shipments_List' ) ) {
			$type_info        = Andreani_Client_Type::from_id( isset( $merged['client_type'] ) ? $merged['client_type'] : '' );
			$has_payment_step = $type_info ? $type_info->has_payment_step() : false;
			$mapped           = Andreani_Shipments_List::map_tracking_status( $merged['tracking_status'] );
			$config           = Andreani_Shipments_List::get_shipping_status_config( $mapped, $has_payment_step );
			$merged['tracking_status_label'] = $config['label'];
			$merged['tracking_status_class'] = $config['class'];
		}

		return $merged;
	}

	private function fetch_api_data( $order_id ) {
		$api = Andreani_Shipments_Api::get_instance();

		// Sin pre-flight a /health (no existe en el back): el propio get_shipment ya
		// marca api_failure si el back no responde, igual que la grilla y el detalle.
		$shipment = $api->get_shipment( $order_id );

		if ( is_wp_error( $shipment ) ) {
			return array( 'api_failure' => true );
		}

		$hydrated = array( 'source' => 'api' );

		if ( ! empty( $shipment['trackingNumber'] ) ) {
			$hydrated['tracking_number'] = $shipment['trackingNumber'];
		}
		if ( ! empty( $shipment['pedidoId'] ) ) {
			$hydrated['pedido_id'] = $shipment['pedidoId'];
		}
		if ( ! empty( $shipment['errorMessage'] ) ) {
			$hydrated['last_error'] = $shipment['errorMessage'];
		}
		if ( ! empty( $shipment['updatedAt'] ) ) {
			$hydrated['api_updated_at'] = $shipment['updatedAt'];
		}
		if ( ! empty( $shipment['status'] ) ) {
			$hydrated['api_status'] = $shipment['status'];
		}
		if ( ! empty( $shipment['trackingStatus'] ) ) {
			$hydrated['tracking_status'] = $shipment['trackingStatus'];
		}

		return $hydrated;
	}

	private function should_hydrate( $order ) {
		if ( ! defined( 'ANDREANI_API_CUTOFF' ) || ! class_exists( 'Andreani_Shipments_Api' ) ) {
			return false;
		}

		$cutoff = strtotime( ANDREANI_API_CUTOFF );
		if ( ! $cutoff ) {
			return false;
		}

		$created = $order->get_date_created();
		if ( ! $created ) {
			return false;
		}

		return $created->getTimestamp() >= $cutoff;
	}
}
