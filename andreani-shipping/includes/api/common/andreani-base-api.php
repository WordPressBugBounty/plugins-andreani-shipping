<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

abstract class Andreani_Base_Api implements Andreani_API_Interface {
	const SHIPPING_METHOD_PREFIX = '';
	const CLIENT_INFO_OPTION = '';

	protected static $instance = null;
	protected static $client_type;
	protected $api_endpoints = array();
	protected $cp_origen;
	protected $mostrar_sin_decimales;
	protected $hash_andreani;
	protected $info_cliente;
	protected $last_validation_error = null;

	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}
		return static::$instance;
	}

	public function __construct() {
		$this->api_endpoints = Andreani_Api_Config::get_endpoints( static::$client_type );
	}

	/**
	 * Inicializar con credenciales
	 *
	 * @param array $settings Configuración con credenciales
	 * @throws Exception Si no se puede validar el hash
	 */
	public function init_with_credentials( $settings = array() ) {
		$this->cp_origen             = $settings['cp_origen'];
		$this->hash_andreani         = $settings['hash_andreani'];
		$this->mostrar_sin_decimales = isset( $settings['mostrar_sin_decimales'] ) ? $settings['mostrar_sin_decimales'] === 'yes' : false;
		$this->info_cliente          = $this->get_client_info();

		if ( empty( $this->info_cliente ) ) {
			if ( ! $this->validate_hash( $this->hash_andreani ) ) {
				$tipo = ucfirst( static::$client_type );
				throw new Exception( "Error al validar el hash de cliente {$tipo}" );
			}
		}
	}

	public static function get_client_type() {
		return static::$client_type;
	}

	public static function get_type_info() {
		return Andreani_Client_Type::from_id( static::$client_type );
	}

	/**
	 * Devuelve bool a proposito: los callers usan `if ( ! $api->validate_hash(...) )` y un
	 * WP_Error es truthy en PHP, asi que devolverlo aca leeria un fallo como exito.
	 * El motivo viaja aparte, por get_last_validation_error().
	 */
	public function validate_hash( $hash ) {
		$this->last_validation_error = null;

		$headers = array(
			'Authorization' => $hash,
			'Content-Type'  => 'application/json',
		);

		$response = Andreani_Utils::make_request( 'POST', $this->api_endpoints['login'], null, $headers );
		$response = Andreani_Api_Response::decode( $response );

		if ( is_wp_error( $response ) || ! isset( $response['response'] ) ) {
			$tipo = ucfirst( static::$client_type );
			$this->last_validation_error = self::classify_validation_error( $response );
			Andreani_Utils::andreani_log(
				"[AUTH] Credencial ID {$tipo} no validada [{$this->last_validation_error->get_error_code()}]: " . $this->last_validation_error->get_error_message(),
				'error'
			);
			return false;
		}

		$this->save_client_info( $response['response'] );
		$this->info_cliente = $response['response'];
		return true;
	}

	/**
	 * @return WP_Error|null Motivo del ultimo validate_hash fallido.
	 */
	public function get_last_validation_error() {
		return $this->last_validation_error;
	}

	/**
	 * @return WP_Error Motivo del fallo, con el mensaje que se le muestra al comercio.
	 */
	protected static function classify_validation_error( $response ) {
		if ( ! is_wp_error( $response ) ) {
			return new WP_Error(
				'andreani_service_error',
				__( 'Andreani respondió de forma inesperada. Esperá unos minutos y volvé a intentar.', 'andreani-shipping' )
			);
		}

		$code = $response->get_error_code();

		// invalid_json = Andreani contesto 2xx con algo que no es JSON: llegamos, responde mal.
		if ( 'invalid_json' === $code ) {
			return new WP_Error(
				'andreani_service_error',
				__( 'Andreani respondió de forma inesperada. Esperá unos minutos y volvé a intentar.', 'andreani-shipping' )
			);
		}

		if ( 'http_error' !== $code ) {
			return new WP_Error(
				'andreani_connection_error',
				__( 'No pudimos conectar con los servidores de Andreani. Verificá que tu servidor permita conexiones salientes hacia woocommerce-api-acom.andreani.com y volvé a intentar. Si el problema persiste, contactate con Andreani: la credencial puede estar bien.', 'andreani-shipping' )
			);
		}

		$data   = $response->get_error_data();
		$status = ( is_array( $data ) && isset( $data['status'] ) ) ? (int) $data['status'] : 0;

		if ( 401 === $status || 403 === $status ) {
			return new WP_Error(
				'andreani_invalid_credential',
				__( 'La Credencial ID no es válida. Generá una nueva desde el portal de Andreani y volvé a pegarla acá.', 'andreani-shipping' )
			);
		}

		return new WP_Error(
			'andreani_service_error',
			__( 'Los servidores de Andreani no están respondiendo en este momento. Esperá unos minutos y volvé a intentar.', 'andreani-shipping' )
		);
	}

	public function get_sucursales( $codigo_postal = '' ) {
		if ( empty( $codigo_postal ) ) {
			return new WP_Error( 'invalid_postal_code', 'Debe proporcionar un Código Postal para obtener las sucursales.' );
		}

		$codigo_postal = Andreani_Postcode::normalize( $codigo_postal );

		$headers = array(
			'Content-Type'  => 'application/json',
			'X-Auth-Token'  => $this->info_cliente['accessToken'],
		);

		$url      = add_query_arg( 'postalCode', $codigo_postal, $this->api_endpoints['sucursales'] );
		$response = Andreani_Utils::make_request( 'GET', $url, null, $headers, 0, 15 );
		$response = Andreani_Api_Response::decode( $response );

		return Andreani_Api_Response::process_sucursales( $response );
	}

	/**
	 * Sucursales habilitadas como ORIGEN para un código postal, ordenadas por cercanía
	 * tal como las devuelve el back.
	 *
	 * @param string      $codigo_postal Código postal de la tienda.
	 * @param string|null $lat           Latitud opcional para afinar la cercanía.
	 * @param string|null $lng           Longitud opcional para afinar la cercanía.
	 * @return array|WP_Error { options, details } o WP_Error.
	 */
	public function get_sucursales_origen( $codigo_postal = '', $lat = null, $lng = null ) {
		if ( empty( $codigo_postal ) ) {
			return new WP_Error( 'invalid_postal_code', 'Debe proporcionar un Código Postal para obtener las sucursales de origen.' );
		}

		$codigo_postal = Andreani_Postcode::normalize( $codigo_postal );

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->get_access_token(),
		);

		$query = array(
			'postalCode' => $codigo_postal,
		);

		if ( null !== $lat && '' !== $lat ) {
			$query['lat'] = $lat;
		}
		if ( null !== $lng && '' !== $lng ) {
			$query['lng'] = $lng;
		}

		$url      = add_query_arg( $query, $this->api_endpoints['sucursales_origen'] );
		$response = Andreani_Utils::make_request( 'GET', $url, null, $headers, 0, 15 );
		$response = Andreani_Api_Response::decode( $response );

		return Andreani_Api_Response::process_sucursales( $response );
	}

	/**
	 * Sucursal que Andreani asigna hoy por defecto para un código postal.
	 *
	 * @param string $codigo_postal Código postal de la tienda.
	 * @return array|WP_Error { codigo, descripcion, direccion, ... } o WP_Error.
	 */
	public function get_origen_predeterminado( $codigo_postal = '' ) {
		if ( empty( $codigo_postal ) ) {
			return new WP_Error( 'invalid_postal_code', 'Debe proporcionar un Código Postal para obtener la sucursal de origen predeterminada.' );
		}

		$codigo_postal = Andreani_Postcode::normalize( $codigo_postal );

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->get_access_token(),
		);

		$url      = add_query_arg( 'postalCode', $codigo_postal, $this->api_endpoints['origen_default'] );
		$response = Andreani_Utils::make_request( 'GET', $url, null, $headers, 0, 15 );
		$response = Andreani_Api_Response::decode( $response );

		return Andreani_Api_Response::process_origen_predeterminado( $response );
	}

	/**
	 * Obtener cotización de envío (Template Method)
	 * Las clases hijas deben implementar build_cotizacion_body()
	 *
	 * @param array $package Paquete de WooCommerce
	 * @return array Array con 'rates' y 'errors'
	 */
	public function get_cotizacion( $package ) {
		$rates  = array();
		$errors = array();

		$validation_error = Andreani_Api_Utils::validate_package( $package );
		if ( is_wp_error( $validation_error ) ) {
			$errors[] = $validation_error->get_error_message();
			return array(
				'rates'  => $rates,
				'errors' => $errors,
			);
		}

		$cp_destino = isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : '';
		if ( empty( $cp_destino ) ) {
			$errors[] = __( 'Código postal de destino no proporcionado.', 'andreani-shipping' );
			return array(
				'rates'  => $rates,
				'errors' => $errors,
			);
		}

		$cp_destino = Andreani_Postcode::normalize( $cp_destino );
		$package['destination']['postcode'] = $cp_destino;

		Andreani_Utils::set_session_data( 'cp_destino', $cp_destino );

		$headers = array(
			'Content-Type'  => 'application/json',
			'X-Auth-Token'  => $this->info_cliente['accessToken'],
		);

		$body = $this->build_cotizacion_body( $package );

		if ( null === $body ) {
			return array(
				'rates'  => $rates,
				'errors' => $errors,
			);
		}

		$response = Andreani_Utils::make_request( 'POST', $this->api_endpoints['cotizacion'], $body, $headers );
		$response = Andreani_Api_Response::decode( $response );
		$result   = Andreani_Api_Response::process_cotizacion( $response );

		if ( is_wp_error( $result ) ) {
			$errors[] = $result->get_error_message();
		} else {
			$cart_total = isset( $package['contents_cost'] ) ? floatval( $package['contents_cost'] ) : 0;
			$rates      = Andreani_Api_Response::build_shipping_rates(
				$result,
				static::SHIPPING_METHOD_PREFIX,
				$cart_total
			);
		}

		return array(
			'rates'  => $rates,
			'errors' => $errors,
		);
	}

	public function create_orden( $order_id ) {
		if ( ! $this->validate_order( $order_id ) ) {
			return new WP_Error(
				'andreani_order_validation_error',
				sprintf( 'Orden %d no es válida para envío Andreani', $order_id )
			);
		}

		$order           = wc_get_order( $order_id );
		$shipping_method = Andreani_Api_Utils::get_order_shipping_method( $order );

		if ( strpos( $shipping_method, static::SHIPPING_METHOD_PREFIX ) === false ) {
			$tipo = ucfirst( static::$client_type );
			return new WP_Error(
				'andreani_order_invalid_shipping',
				sprintf( 'La orden %d no tiene un método de envío Andreani %s', $order_id, $tipo )
			);
		}

		$delivery_mode = Andreani_Api_Utils::get_delivery_mode_from_shipping_method(
			$shipping_method,
			static::SHIPPING_METHOD_PREFIX
		);

		if ( empty( $delivery_mode ) ) {
			return new WP_Error(
				'andreani_order_invalid_delivery_mode',
				sprintf( 'La orden %d no tiene un modo de entrega válido', $order_id )
			);
		}

		$contract = $this->get_contract_for_delivery_mode( $delivery_mode );

		if ( is_wp_error( $contract ) ) {
			return $contract;
		}

		$context = $this->build_order_context( $order, $contract );

		if ( is_wp_error( $context ) ) {
			$tipo      = ucfirst( static::$client_type );
			$error_msg = sprintf(
				'Error al crear orden %d en Andreani %s: %s',
				$order_id,
				$tipo,
				$context->get_error_message()
			);
			$body_payload = array(
				'error_type'    => 'validation_pre_api',
				'error_code'    => $context->get_error_code(),
				'order_id'      => $order_id,
				'client_type'   => static::$client_type,
				'delivery_mode' => $delivery_mode,
				'message'       => $context->get_error_message(),
				'detail'        => $context->get_error_data(),
			);
			$body_json = wp_json_encode( $body_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			Andreani_Utils::andreani_log(
				"[ORDEN #{$order_id}] Validación PRE-API fallida ({$context->get_error_code()}): " . $context->get_error_message(),
				'error'
			);
			$order->update_meta_data( '_andreani_last_error', $error_msg );
			$order->update_meta_data( '_andreani_last_error_code', $context->get_error_code() );
			$order->update_meta_data( '_andreani_last_error_body', $body_json );
			$order->save();
			return new WP_Error( $context->get_error_code(), $error_msg, $context->get_error_data() );
		}

		$andreani_data = $this->assemble_order_body( $context, $contract );

		if ( empty( $andreani_data ) ) {
			$tipo = ucfirst( static::$client_type );
			$error_msg = sprintf( 'Error al mapear datos de orden %d para Andreani %s', $order_id, $tipo );
			$body_payload = array(
				'error_type'    => 'validation_pre_api',
				'error_code'    => 'andreani_data_mapping_error',
				'order_id'      => $order_id,
				'client_type'   => static::$client_type,
				'delivery_mode' => $delivery_mode,
				'message'       => $error_msg,
			);
			$body_json = wp_json_encode( $body_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			$order->update_meta_data( '_andreani_last_error', $error_msg );
			$order->update_meta_data( '_andreani_last_error_code', 'andreani_data_mapping_error' );
			$order->update_meta_data( '_andreani_last_error_body', $body_json );
			$order->save();
			return new WP_Error( 'andreani_data_mapping_error', $error_msg );
		}

		$cp_destino = isset( $andreani_data['destination']['postal_code'] ) ? $andreani_data['destination']['postal_code'] : 'N/A';
		$tipo       = ucfirst( static::$client_type );
		Andreani_Utils::andreani_log(
			"[ORDEN #{$order_id}] Enviando a Andreani {$tipo} - CP destino: {$cp_destino}, Método: {$delivery_mode}",
			'info'
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'X-Auth-Token'  => $this->info_cliente['accessToken'],
		);

		$response = Andreani_Utils::make_request( 'POST', $this->api_endpoints['orden'], $andreani_data, $headers, 3 );

		$body_json = wp_json_encode( $andreani_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( is_wp_error( $response ) ) {
			$error_msg = sprintf( 'Error al crear orden %d en Andreani %s: %s', $order_id, $tipo, $response->get_error_message() );
			Andreani_Utils::andreani_log(
				"[ORDEN #{$order_id}] Error al crear en Andreani {$tipo}: " . $response->get_error_message(),
				'error'
			);
			$order->update_meta_data( '_andreani_last_error', $error_msg );
			$order->update_meta_data( '_andreani_last_error_code', 'andreani_order_creation_error' );
			$order->update_meta_data( '_andreani_last_error_body', $body_json );
			$order->save();
			return new WP_Error( 'andreani_order_creation_error', $error_msg );
		}

		$decoded_response = Andreani_Api_Response::decode( $response );
		if ( is_wp_error( $decoded_response ) ) {
			$error_msg = sprintf( 'Respuesta inválida de Andreani para orden %d: %s', $order_id, $decoded_response->get_error_message() );
			Andreani_Utils::andreani_log(
				"[ORDEN #{$order_id}] Respuesta inválida de Andreani: " . $decoded_response->get_error_message(),
				'error'
			);
			$order->update_meta_data( '_andreani_last_error', $error_msg );
			$order->update_meta_data( '_andreani_last_error_code', $decoded_response->get_error_code() );
			$order->update_meta_data( '_andreani_last_error_body', $body_json );
			$order->save();
			return $decoded_response;
		}

		$this->save_order_response( $order, $decoded_response );

		return $decoded_response;
	}

	/**
	 * Descarga la etiqueta (PDF) de un envío ya creado en Andreani. Genérico para
	 * todos los segmentos: usa el endpoint 'etiqueta' resuelto por tipo de cliente.
	 *
	 * @param int $order_id ID de la orden WooCommerce
	 * @return array|WP_Error Array con el PDF en base64, o WP_Error
	 */
	public function get_etiqueta( $order_id ) {
		if ( ! $this->validate_order( $order_id ) ) {
			return new WP_Error(
				'andreani_order_validation_error',
				sprintf( 'Orden %d no es válida para envío Andreani', $order_id )
			);
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error(
				'andreani_order_not_found',
				sprintf( 'Orden %d no encontrada', $order_id )
			);
		}

		$tracking_number = $this->resolve_tracking_number( $order );
		if ( empty( $tracking_number ) ) {
			return new WP_Error(
				'andreani_order_not_created',
				sprintf( 'La orden %d no ha sido creada en Andreani', $order_id )
			);
		}

		$body = array(
			'trackingNumbers' => array( $tracking_number ),
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'X-Auth-Token'  => $this->info_cliente['accessToken'],
		);

		$response = Andreani_Utils::make_request( 'POST', $this->api_endpoints['etiqueta'], $body, $headers );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'andreani_etiqueta_error',
				sprintf( 'Error al obtener etiqueta para orden %d: %s', $order_id, $response->get_error_message() )
			);
		}

		return array(
			'success'  => true,
			'response' => array(
				'trackingNumber' => $tracking_number,
				'pdf'            => base64_encode( $response ),
			),
		);
	}

	/**
	 * Descarga en una sola operación las etiquetas (PDF combinado) de varias órdenes.
	 * Genérico para todos los segmentos: junta los tracking numbers de las órdenes que
	 * ya tienen envío creado y hace UNA request al endpoint 'etiqueta'. Las órdenes sin
	 * tracking se omiten y se reportan al caller para feedback de parciales.
	 *
	 * @param int[] $order_ids IDs de órdenes WooCommerce.
	 * @return array|WP_Error Array con el PDF combinado en base64 + contadores, o WP_Error.
	 */
	public function get_etiquetas_bulk( array $order_ids ) {
		$tracking_numbers = array();
		$skipped_orders   = array();

		$orders = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$skipped_orders[] = $order_id;
				continue;
			}
			$orders[] = $order;
		}

		$tracking_map = $this->lookup_tracking_numbers( $orders );

		foreach ( $orders as $order ) {
			$tracking_number = isset( $tracking_map[ (string) $order->get_id() ] ) ? $tracking_map[ (string) $order->get_id() ] : '';
			if ( '' === $tracking_number ) {
				$skipped_orders[] = $order->get_id();
				continue;
			}

			$tracking_numbers[] = $tracking_number;
		}

		if ( empty( $tracking_numbers ) ) {
			return new WP_Error(
				'andreani_etiquetas_bulk_no_tracking',
				__( 'Ninguna de las órdenes seleccionadas tiene número de seguimiento todavía.', 'andreani-shipping' )
			);
		}

		$body = array(
			'trackingNumbers' => array_values( $tracking_numbers ),
		);

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->info_cliente['accessToken'],
		);

		$response = Andreani_Utils::make_request( 'POST', $this->api_endpoints['etiqueta'], $body, $headers );

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'andreani_etiquetas_bulk_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Error al obtener las etiquetas: %s', 'andreani-shipping' ),
					$response->get_error_message()
				)
			);
		}

		return array(
			'success'  => true,
			'response' => array(
				'pdf'            => base64_encode( $response ),
				'included'       => count( $tracking_numbers ),
				'skipped'        => count( $skipped_orders ),
				'skipped_orders' => $skipped_orders,
			),
		);
	}

	/**
	 * Resuelve el número de seguimiento de una orden. Corpo lo persiste en el meta
	 * al alta; pyme/MM lo asocian después y vive en la API — por eso, si el meta
	 * está vacío, se busca en el back por número de orden.
	 *
	 * @param WC_Order $order
	 * @return string Tracking number, o '' si todavía no está disponible.
	 */
	protected function resolve_tracking_number( $order ) {
		$map = $this->lookup_tracking_numbers( array( $order ) );
		return isset( $map[ (string) $order->get_id() ] ) ? $map[ (string) $order->get_id() ] : '';
	}

	/**
	 * Construye un mapa orderId => trackingNumber para un set de órdenes: toma el meta
	 * cuando está (corpo) y resuelve el resto (pyme/MM) con UNA sola consulta bulk al back.
	 *
	 * @param WC_Order[] $orders
	 * @return array<string,string>
	 */
	protected function lookup_tracking_numbers( array $orders ) {
		$map      = array();
		$need_api = array();

		foreach ( $orders as $order ) {
			$meta = (string) $order->get_meta( '_order_andreani_tracking_number', true );
			if ( '' !== $meta ) {
				$map[ (string) $order->get_id() ] = $meta;
			} else {
				$need_api[] = (string) $order->get_id();
			}
		}

		if ( ! empty( $need_api ) ) {
			$shipments = Andreani_Shipments_Api::get_instance()->lookup_shipments( $need_api );
			if ( ! is_wp_error( $shipments ) && is_array( $shipments ) ) {
				foreach ( $shipments as $shipment ) {
					if ( ! empty( $shipment['salesOrderNumber'] ) && ! empty( $shipment['trackingNumber'] ) ) {
						$map[ (string) $shipment['salesOrderNumber'] ] = (string) $shipment['trackingNumber'];
					}
				}
			}
		}

		return $map;
	}

	/**
	 * Obtiene la configuración de impresión de etiquetas de la cuenta. Si el
	 * merchant nunca guardó una, la API devuelve el default (A4, 1 etiqueta/hoja).
	 *
	 * @return array|WP_Error Array con { key, formatoZebra, enviosPerPage }, o WP_Error
	 */
	public function get_print_settings() {
		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->info_cliente['accessToken'],
		);

		$response = Andreani_Utils::make_request( 'GET', $this->api_endpoints['settings'], null, $headers, 0, 15 );
		$response = Andreani_Api_Response::decode( $response );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return new WP_Error( 'andreani_settings_invalid_response', 'Respuesta de configuración no válida.' );
		}

		return $response['response'];
	}

	/**
	 * Persiste la configuración de impresión de etiquetas de la cuenta.
	 *
	 * @param int  $key      Formato seleccionado (1=A4 1/hoja, 2=A4 4/hoja, 3=Zebra 10x15)
	 * @param bool $zebra    true para formato Zebra
	 * @param int  $per_page Etiquetas por hoja
	 * @return array|WP_Error Array con la config persistida, o WP_Error
	 */
	public function save_print_settings( $key, $zebra, $per_page ) {
		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->info_cliente['accessToken'],
		);

		$body = array(
			'key'           => (int) $key,
			'formatoZebra'  => (bool) $zebra,
			'enviosPerPage' => (int) $per_page,
		);

		$response = Andreani_Utils::make_request( 'PATCH', $this->api_endpoints['settings'], $body, $headers );
		$response = Andreani_Api_Response::decode( $response );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return new WP_Error( 'andreani_settings_invalid_response', 'Respuesta de configuración no válida.' );
		}

		return $response['response'];
	}

	/**
	 * Envía a Andreani desde dónde despacha la tienda, para que quede en la cuenta
	 * y no sólo en esta instalación de WordPress.
	 *
	 * @param array  $origen Origen persistido (ver Andreani_Origen::get()).
	 * @param string $cp     Código postal de origen.
	 * @return array|WP_Error Origen persistido por la API, o WP_Error.
	 */
	public function save_origen( $origen, $cp ) {
		if ( empty( $this->info_cliente['accessToken'] ) ) {
			return new WP_Error( 'andreani_origen_sin_token', 'La cuenta todavía no tiene una sesión válida.' );
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'X-Auth-Token' => $this->info_cliente['accessToken'],
		);

		$fijada = Andreani_Origen::MODO_FIJADA === $origen['modo'];

		$body = array(
			'calle'          => (string) $origen['calle'],
			'numero'         => (string) $origen['numero'],
			'piso'           => (string) $origen['piso'],
			'localidad'      => (string) $origen['localidad'],
			'provincia'      => (string) $origen['provincia'],
			'codigoPostal'   => (string) $cp,
			'remitente'        => (string) $origen['remitente_nombre'],
			'sucursalAsignada' => $fijada ? (string) $origen['sucursal_codigo'] : '',
		);

		$response = Andreani_Utils::make_request( 'PATCH', $this->api_endpoints['settings_origen'], $body, $headers, 0, 15 );
		$response = Andreani_Api_Response::decode( $response );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return new WP_Error( 'andreani_origen_invalid_response', 'Respuesta de origen no válida.' );
		}

		return $response['response'];
	}

	protected function get_client_info() {
		return get_option( static::CLIENT_INFO_OPTION, null );
	}

	public function get_access_token() {
		return isset( $this->info_cliente['accessToken'] ) ? $this->info_cliente['accessToken'] : null;
	}

	protected function validate_order( $order_id ) {
		$tipo = ucfirst( static::$client_type );
		return Andreani_Api_Utils::validate_order_for_shipping(
			$order_id,
			static::SHIPPING_METHOD_PREFIX,
			$tipo
		);
	}

	protected function build_order_context( $order, Andreani_Contract $contract ) {
		$destination = Andreani_Order_Mapper::get_destination_data( $order );
		if ( is_wp_error( $destination ) ) {
			return $destination;
		}

		$recipient = Andreani_Order_Mapper::get_recipient_data( $order, $destination['use_shipping'] );

		$recipient_error = self::validate_recipient( $recipient );
		if ( is_wp_error( $recipient_error ) ) {
			return $recipient_error;
		}

		$products = Andreani_Order_Mapper::prepare_products_for_orden( $order, $this->mostrar_sin_decimales );
		if ( is_wp_error( $products ) ) {
			return $products;
		}

		$code_branch = '';
		if ( $contract->is_sucursal() ) {
			$code_branch = Andreani_Order_Mapper::get_branch_code_for_order( $order->get_id() );
			if ( empty( $code_branch ) ) {
				Andreani_Utils::andreani_log(
					"[ORDEN #{$order->get_id()}] Código de sucursal no encontrado - el cliente debe seleccionar una sucursal en el checkout",
					'error'
				);
				return new WP_Error(
					'andreani_branch_missing',
					'Código de sucursal no encontrado: el cliente no seleccionó sucursal en el checkout',
					array(
						'delivery_mode' => 'sucursal',
						'order_id'      => $order->get_id(),
					)
				);
			}
		}

		return array(
			'destination'    => $destination,
			'recipient'      => $recipient,
			'products'       => $products,
			'code_branch'    => $code_branch,
			'shipping_total' => floatval( $order->get_shipping_total() ),
			'remito'         => (string) $order->get_id(),
		);
	}

	protected function save_order_response( $order, $response ) {
		$order_id  = $order->get_id();
		$type_info = static::get_type_info();

		$identifier = isset( $response['response'][ $type_info->tracking_response_key() ] )
			? $response['response'][ $type_info->tracking_response_key() ]
			: '';

		if ( empty( $identifier ) ) {
			Andreani_Utils::andreani_log(
				"[ORDEN #{$order_id}] Respuesta sin {$type_info->tracking_response_key()} - Revisar respuesta de API",
				'error'
			);
			return;
		}

		$order->update_meta_data( $type_info->tracking_meta_key(), sanitize_text_field( $identifier ) );
		$order->update_meta_data( '_order_andreani_created', true );
		$order->update_meta_data( '_order_andreani_client_type', $type_info->id() );
		$order->delete_meta_data( '_andreani_last_error' );
		$order->delete_meta_data( '_andreani_last_error_code' );
		$order->delete_meta_data( '_andreani_last_error_body' );

		if ( ! empty( $response['response']['numeroInterno'] ) ) {
			$order->update_meta_data( '_order_andreani_numero_interno', sanitize_text_field( $response['response']['numeroInterno'] ) );
		}

		$shipping_method = Andreani_Api_Utils::get_order_shipping_method( $order );
		$delivery_mode   = Andreani_Api_Utils::get_delivery_mode_from_shipping_method( $shipping_method, $type_info->prefix() );
		$order->update_meta_data( '_order_andreani_delivery_mode', sanitize_text_field( $delivery_mode ) );

		$contract = $this->get_contract_for_delivery_mode( $delivery_mode );
		if ( $contract instanceof Andreani_Contract && '' !== $contract->get_number() ) {
			$order->update_meta_data( '_order_andreani_contract_number', sanitize_text_field( $contract->get_number() ) );
		}

		$this->save_origin_meta( $order );

		$order->save();

		Andreani_Utils::andreani_log(
			"[ORDEN #{$order_id}] Creada exitosamente en Andreani {$type_info->label()} - {$type_info->tracking_log_label()}: {$identifier}",
			'info'
		);
	}

	/**
	 * Metadatos del origen impreso en la etiqueta. Se reescriben en cada alta:
	 * una re-alta con otra configuración tiene que reflejar la sucursal nueva.
	 *
	 * @param WC_Order $order
	 */
	protected function save_origin_meta( $order ) {
		$pin = class_exists( 'Andreani_Origen' ) ? Andreani_Origen::get_pin() : '';

		if ( '' === $pin ) {
			$order->update_meta_data( '_order_andreani_sucursal_origen_codigo', '' );
			$order->update_meta_data(
				'_order_andreani_sucursal_origen_nombre',
				__( 'Automática — asignada por Andreani', 'andreani-shipping' )
			);
			$order->update_meta_data( '_order_andreani_sucursal_origen_direccion', '' );
			return;
		}

		$origen = Andreani_Origen::get();
		$nombre = '' !== (string) $origen['sucursal_nombre'] ? (string) $origen['sucursal_nombre'] : $pin;

		$order->update_meta_data( '_order_andreani_sucursal_origen_codigo', sanitize_text_field( $pin ) );
		$order->update_meta_data( '_order_andreani_sucursal_origen_nombre', sanitize_text_field( $nombre ) );
		$order->update_meta_data( '_order_andreani_sucursal_origen_direccion', sanitize_text_field( (string) $origen['sucursal_direccion'] ) );
	}

	protected static function validate_recipient( array $recipient ) {
		$missing = array();

		if ( empty( $recipient['name'] ) ) {
			$missing[] = 'name';
		}
		if ( empty( $recipient['last_name'] ) ) {
			$missing[] = 'last_name';
		}
		if ( empty( $recipient['phone_number'] ) ) {
			$missing[] = 'phone';
		}

		if ( empty( $missing ) ) {
			return true;
		}

		$labels = array(
			'name'       => 'nombre',
			'last_name'  => 'apellido',
			'phone'      => 'teléfono',
		);
		$missing_labels = array_map(
			function( $field ) use ( $labels ) {
				return isset( $labels[ $field ] ) ? $labels[ $field ] : $field;
			},
			$missing
		);
		$message = sprintf(
			'No se puede crear el envío: falta %s del destinatario. Editá los datos y reintentá.',
			implode( ', ', $missing_labels )
		);

		return new WP_Error(
			'andreani_missing_recipient_fields',
			$message,
			array( 'missing_fields' => $missing )
		);
	}

	abstract protected function build_cotizacion_body( $package );

	abstract protected function get_contract_for_delivery_mode( $delivery_mode );

	abstract protected function assemble_order_body( array $context, Andreani_Contract $contract );

	abstract protected function save_client_info( $data );
}
