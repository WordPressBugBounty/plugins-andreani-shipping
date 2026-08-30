<?php
/**
 * Andreani_Shipments_Api
 * Cliente HTTP para consultar envíos del cliente contra la API.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Shipments_Api {
	const MAX_BULK_IDENTIFIERS = 100;
	const MAX_PAGE_SIZE        = 100;
	const DEFAULT_TIMEOUT      = 10;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	/**
	 * POST /api/v1/Shipments/ByOrderNumbers
	 *
	 * @param array $sales_order_numbers Lista de IDs de orden WC. Máx 100 (capeado).
	 * @return array|WP_Error Lista de envíos (puede estar vacía) o error.
	 */
	public function lookup_shipments( array $sales_order_numbers ) {
		$identifiers = array_values( array_unique( array_filter(
			array_map( 'strval', $sales_order_numbers ),
			static function ( $v ) {
				return '' !== trim( $v );
			}
		) ) );

		if ( empty( $identifiers ) ) {
			return array();
		}

		$identifiers = array_slice( $identifiers, 0, self::MAX_BULK_IDENTIFIERS );

		$headers = $this->build_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$url      = Andreani_Api_Config::get_api_base_url() . '/api/v1/Shipments/ByOrderNumbers';
		$body     = array( 'salesOrderNumbers' => $identifiers );
		$response = Andreani_Utils::make_request( 'POST', $url, $body, $headers, 0, self::DEFAULT_TIMEOUT );

		return $this->unwrap_response( $response, '[SHIPMENTS_LOOKUP]' );
	}

	/**
	 * GET /api/v1/Shipments/{salesOrderNumber}
	 *
	 * @param string $sales_order_number Order ID WC.
	 * @return array|WP_Error Detalle del envío o error.
	 */
	public function get_shipment( $sales_order_number ) {
		$sales_order_number = trim( (string) $sales_order_number );
		if ( '' === $sales_order_number ) {
			return new WP_Error( 'invalid_param', __( 'salesOrderNumber requerido.', 'andreani-shipping' ) );
		}

		$headers = $this->build_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$url      = Andreani_Api_Config::get_api_base_url() . '/api/v1/Shipments/' . rawurlencode( $sales_order_number );
		$response = Andreani_Utils::make_request( 'GET', $url, null, $headers, 0, self::DEFAULT_TIMEOUT );

		return $this->unwrap_response( $response, '[SHIPMENTS_GET]' );
	}

	/**
	 * GET /api/v1/Shipments?<filters>&page=&pageSize=
	 *
	 * @param array $filters Filtros opcionales: from (ISO 8601), to (ISO 8601),
	 *                       status (string|array), clientType (Pyme|Corporative),
	 *                       search, sortBy (createdAt|updatedAt), sortDir (asc|desc).
	 * @param int   $page     Página, default 1.
	 * @param int   $page_size Tamaño de página, capeado a 100.
	 * @return array|WP_Error PagedShipmentResponse o error.
	 */
	public function list_shipments( array $filters = array(), $page = 1, $page_size = 25 ) {
		$headers = $this->build_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$page      = max( 1, (int) $page );
		$page_size = min( max( 1, (int) $page_size ), self::MAX_PAGE_SIZE );

		$base    = Andreani_Api_Config::get_api_base_url() . '/api/v1/Shipments';
		$url     = $this->build_list_url( $base, $filters, $page, $page_size );

		$response = Andreani_Utils::make_request( 'GET', $url, null, $headers, 0, self::DEFAULT_TIMEOUT );

		return $this->unwrap_response( $response, '[SHIPMENTS_LIST]' );
	}

	private function build_headers() {
		$api = Andreani_Api_Manager::get_api();
		if ( ! $api ) {
			return new WP_Error( 'no_active_api', __( 'No hay cliente Andreani configurado.', 'andreani-shipping' ) );
		}

		$token = $api->get_access_token();
		if ( empty( $token ) ) {
			return new WP_Error( 'no_access_token', __( 'Sesión Andreani expirada. Volvé a guardar la configuración.', 'andreani-shipping' ) );
		}

		return array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $token,
		);
	}

	private function build_list_url( $base, array $filters, $page, $page_size ) {
		$query_pairs = array();

		$simple_keys = array( 'from', 'to', 'clientType', 'search', 'sortBy', 'sortDir' );
		foreach ( $simple_keys as $key ) {
			if ( isset( $filters[ $key ] ) && '' !== (string) $filters[ $key ] ) {
				$query_pairs[] = rawurlencode( $key ) . '=' . rawurlencode( (string) $filters[ $key ] );
			}
		}

		if ( isset( $filters['status'] ) ) {
			$statuses = is_array( $filters['status'] ) ? $filters['status'] : array( $filters['status'] );
			foreach ( $statuses as $s ) {
				$s = trim( (string) $s );
				if ( '' !== $s ) {
					$query_pairs[] = 'status=' . rawurlencode( $s );
				}
			}
		}

		$query_pairs[] = 'page=' . (int) $page;
		$query_pairs[] = 'pageSize=' . (int) $page_size;

		return $base . '?' . implode( '&', $query_pairs );
	}

	/**
	 * Desempaqueta el sobre { message, response } que devuelve el back.
	 *
	 * @param mixed  $response Resultado de Andreani_Utils::make_request.
	 * @param string $log_tag  Prefijo de log.
	 * @return array|WP_Error
	 */
	private function unwrap_response( $response, $log_tag ) {
		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( "{$log_tag} Error HTTP: " . $response->get_error_message(), 'error' );
			return $response;
		}

		$decoded = json_decode( $response, true );
		if ( ! is_array( $decoded ) ) {
			Andreani_Utils::andreani_log( "{$log_tag} Respuesta inválida (no JSON).", 'error' );
			return new WP_Error( 'invalid_response', __( 'Respuesta no válida del back.', 'andreani-shipping' ) );
		}

		return array_key_exists( 'response', $decoded ) ? $decoded['response'] : $decoded;
	}
}
