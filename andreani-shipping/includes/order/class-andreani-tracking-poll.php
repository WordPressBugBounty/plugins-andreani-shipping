<?php
/**
 * Andreani_Tracking_Poll
 * Resuelve el numero de seguimiento del envio para el cliente final (incl.
 * invitados) sin recargar la pagina, sobre la Heartbeat API nativa de WordPress.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Tracking_Poll {

	const DATA_KEY      = 'andreani_track'; // clave del payload en el heartbeat (request y response).
	const MISS_TTL      = 20; // s — evita re-pegarle a la API en cada tick mientras aun no hay tracking.
	const TRACKING_META = '_order_andreani_tracking_number';
	const STATUS_META   = '_order_andreani_tracking_status';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'heartbeat_received', array( $this, 'on_heartbeat' ), 10, 2 );
		add_filter( 'heartbeat_nopriv_received', array( $this, 'on_heartbeat' ), 10, 2 );
	}

	/**
	 * Procesa el tick del Heartbeat. El canal lo comparten otros consumidores, asi que
	 * si no viene nuestro payload devolvemos $response intacto. La autenticidad del
	 * request la cubre el nonce propio del Heartbeat (core); la autorizacion del recurso
	 * la da el order_key (secreto no enumerable de WooCommerce) -> el cliente solo
	 * consulta SU orden, sin login.
	 *
	 * @param array $response Respuesta acumulada del heartbeat.
	 * @param array $data     Datos enviados por el cliente en 'heartbeat-send'.
	 * @return array
	 */
	public function on_heartbeat( $response, $data ) {
		if ( empty( $data[ self::DATA_KEY ] ) || ! is_array( $data[ self::DATA_KEY ] ) ) {
			return $response;
		}

		$req       = $data[ self::DATA_KEY ];
		$order_id  = isset( $req['order_id'] ) ? absint( $req['order_id'] ) : 0;
		$order_key = isset( $req['order_key'] ) ? sanitize_text_field( $req['order_key'] ) : '';

		if ( ! $order_id || '' === $order_key ) {
			return $response;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
			// Sin filtrar: no revelamos si la orden existe.
			return $response;
		}

		$response[ self::DATA_KEY ] = $this->resolve_tracking( $order );
		return $response;
	}

	/**
	 * Devuelve el tracking ya persistido o intenta traerlo de la API.
	 *
	 * @param WC_Order $order
	 * @return array { tracking_number, tracking_status, tracking_url, done }
	 */
	private function resolve_tracking( $order ) {
		$tracking = (string) $order->get_meta( self::TRACKING_META, true );

		if ( '' !== $tracking ) {
			return $this->build_payload( $order, $tracking );
		}

		if ( false !== get_transient( $this->miss_key( $order->get_id() ) ) ) {
			return $this->build_payload( $order, '' );
		}

		$shipment = Andreani_Shipments_Api::get_instance()->get_shipment( $order->get_id() );
		if ( is_wp_error( $shipment ) || ! is_array( $shipment ) ) {
			set_transient( $this->miss_key( $order->get_id() ), '1', self::MISS_TTL );
			return $this->build_payload( $order, '' );
		}

		$tracking = isset( $shipment['trackingNumber'] ) ? trim( (string) $shipment['trackingNumber'] ) : '';
		$status   = isset( $shipment['trackingStatus'] ) ? trim( (string) $shipment['trackingStatus'] ) : '';

		if ( '' === $tracking ) {
			set_transient( $this->miss_key( $order->get_id() ), '1', self::MISS_TTL );
			return $this->build_payload( $order, '', $status );
		}

		$order->update_meta_data( self::TRACKING_META, $tracking );
		if ( '' !== $status ) {
			$order->update_meta_data( self::STATUS_META, $status );
		}
		$order->save();
		delete_transient( $this->miss_key( $order->get_id() ) );

		Andreani_Utils::andreani_log( "[ORDEN #{$order->get_id()}] Tracking asociado via heartbeat: {$tracking}", 'info' );

		return $this->build_payload( $order, $tracking, $status );
	}

	/**
	 * @param WC_Order $order
	 * @param string   $tracking Numero de seguimiento (vacio = aun pendiente).
	 * @param string   $status   Estado logistico opcional.
	 * @return array
	 */
	private function build_payload( $order, $tracking, $status = '' ) {
		if ( '' === $status ) {
			$status = (string) $order->get_meta( self::STATUS_META, true );
		}

		return array(
			'tracking_number' => $tracking,
			'tracking_status' => $status,
			'tracking_url'    => '' !== $tracking ? 'https://www.andreani.com/envio/' . rawurlencode( $tracking ) : '',
			'done'            => '' !== $tracking,
		);
	}

	private function miss_key( $order_id ) {
		return 'andreani_poll_miss_' . $order_id;
	}
}
