<?php
defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/admin/trait-andreani-ajax-helpers.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/admin/class-andreani-shipment-exporter.php';

class Andreani_Ajax_Handler {
	use Andreani_Ajax_Helpers_Trait;

	/**
	 * Tope de órdenes por descarga masiva de etiquetas. La API devuelve un único
	 * PDF combinado; selecciones muy grandes degradan el tiempo de respuesta y el
	 * peso del archivo, así que se acota acá y se pide al merchant achicar.
	 */
	const BULK_ETIQUETAS_MAX = 50;

	const SIM_SEARCH_LIMIT = 20;
	const SIM_SEARCH_MIN   = 2;
	const CART_LINES_MAX   = 50;
	const PREVIEW_MAX_QUANTITY = 30;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_andreani_retry_order', array( $this, 'handle_retry_order' ) );
		add_action( 'wp_ajax_andreani_get_etiqueta', array( $this, 'handle_get_etiqueta' ) );
		add_action( 'wp_ajax_andreani_bulk_etiquetas', array( $this, 'handle_bulk_etiquetas' ) );
		add_action( 'wp_ajax_andreani_load_shipments_table', array( $this, 'handle_load_shipments_table' ) );
		add_action( 'wp_ajax_andreani_update_recipient_and_retry', array( $this, 'handle_update_recipient_and_retry' ) );
		add_action( 'wp_ajax_andreani_get_print_settings', array( $this, 'handle_get_print_settings' ) );
		add_action( 'wp_ajax_andreani_save_print_settings', array( $this, 'handle_save_print_settings' ) );
		add_action( 'wp_ajax_andreani_products_table', array( $this, 'handle_products_table' ) );
		add_action( 'wp_ajax_andreani_save_product_dims', array( $this, 'handle_save_product_dims' ) );
		add_action( 'wp_ajax_andreani_test_quote', array( $this, 'handle_test_quote' ) );
		add_action( 'wp_ajax_andreani_sim_search', array( $this, 'handle_sim_search' ) );
		add_action( 'wp_ajax_andreani_simulate_cart', array( $this, 'handle_simulate_cart' ) );
		add_action( 'wp_ajax_andreani_preview_bultos', array( $this, 'handle_preview_bultos' ) );
		add_action( 'wp_ajax_andreani_order_packing', array( $this, 'handle_order_packing' ) );

		Andreani_Shipment_Exporter::get_instance();
	}

	public function handle_retry_order() {
		check_ajax_referer( 'andreani_retry_order', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para reintentar el envio.', 'andreani-shipping' ),
			), 403 );
		}

		$order_id = $this->get_order_id_from_request();
		if ( ! $order_id ) {
			wp_send_json_error( array(
				'message' => __( 'ID de orden invalido.', 'andreani-shipping' ),
			), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array(
				'message' => __( 'Orden no encontrada.', 'andreani-shipping' ),
			), 404 );
		}

		$retry_count     = (int) $order->get_meta( '_andreani_retry_count', true );
		$current_attempt = $retry_count + 1;
		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Reintentando alta de envio desde admin (intento {$current_attempt})", 'info' );

		$this->fix_shipping_method_prefix( $order );

		$response = Andreani_Api_Manager::create_orden( $order_id );

		$order->update_meta_data( '_andreani_retry_count', $current_attempt );

		if ( is_wp_error( $response ) ) {
			$order->update_meta_data( '_andreani_last_error', $response->get_error_message() );
			$order->save();
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Reintento fallido: " . $response->get_error_message(), 'error' );
			wp_send_json_error( array(
				'message' => __( 'Error al reintentar el envío.', 'andreani-shipping' ),
				'type'    => 'error',
			) );
		}

		$order->save();

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Reintento exitoso", 'info' );
		wp_send_json_success( array(
			'message'  => __( 'Alta de envio reintentada correctamente.', 'andreani-shipping' ),
			'type'     => 'success',
			'row_html' => $this->get_row_html( $order_id ),
		) );
	}

	public function handle_get_etiqueta() {
		check_ajax_referer( 'andreani_get_etiqueta', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para descargar la etiqueta.', 'andreani-shipping' ),
			), 403 );
		}

		$order_id = $this->get_order_id_from_request();
		if ( ! $order_id ) {
			wp_send_json_error( array(
				'message' => __( 'ID de orden invalido.', 'andreani-shipping' ),
			), 400 );
		}

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Solicitando etiqueta de envio", 'debug' );

		$response = Andreani_Api_Manager::get_etiqueta( $order_id );

		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Error al obtener etiqueta: " . $response->get_error_message(), 'error' );
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %1$d: order ID, %2$s: error message */
					__( 'Error al obtener etiqueta para orden %1$d: %2$s', 'andreani-shipping' ),
					$order_id,
					$response->get_error_message()
				),
			), 500 );
		}

		if ( ! isset( $response['response']['pdf'] ) ) {
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Respuesta de etiqueta sin PDF", 'error' );
			wp_send_json_error( array(
				'message' => __( 'No se pudo obtener el PDF de la etiqueta.', 'andreani-shipping' ),
			), 500 );
		}

		$pdf_base64 = $response['response']['pdf'];
		$order      = wc_get_order( $order_id );
		$tracking   = $order ? $order->get_meta( '_order_andreani_tracking_number', true ) : '';

		$tracking_sanitized = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $tracking );
		if ( empty( $tracking_sanitized ) ) {
			$tracking_sanitized = 'sin-tracking';
		}

		$filename = sprintf( 'etiqueta-%d-%s.pdf', $order_id, $tracking_sanitized );

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Etiqueta generada - Tracking: {$tracking}", 'info' );

		wp_send_json_success( array(
			'pdf'      => $pdf_base64,
			'filename' => $filename,
		) );
	}

	public function handle_bulk_etiquetas() {
		check_ajax_referer( 'andreani_bulk_etiquetas', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenés permisos para descargar etiquetas.', 'andreani-shipping' ),
			), 403 );
		}

		$raw_ids   = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array();
		$order_ids = array_values( array_unique( array_filter( array_map( 'absint', $raw_ids ) ) ) );

		if ( empty( $order_ids ) ) {
			wp_send_json_error( array(
				'message' => __( 'Seleccioná al menos una orden.', 'andreani-shipping' ),
			), 400 );
		}

		if ( count( $order_ids ) > self::BULK_ETIQUETAS_MAX ) {
			wp_send_json_error( array(
				'message' => sprintf(
					/* translators: %d: cantidad máxima de etiquetas por descarga */
					__( 'Podés descargar hasta %d etiquetas por vez. Achicá la selección e intentá de nuevo.', 'andreani-shipping' ),
					self::BULK_ETIQUETAS_MAX
				),
			), 422 );
		}

		Andreani_Utils::andreani_log( '[BULK ETIQUETAS] Solicitando etiquetas para ' . count( $order_ids ) . ' órdenes', 'debug' );

		$response = Andreani_Api_Manager::get_etiquetas_bulk( $order_ids );

		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[BULK ETIQUETAS] Error: ' . $response->get_error_message(), 'error' );
			wp_send_json_error( array(
				'message' => $response->get_error_message(),
			), 500 );
		}

		if ( ! isset( $response['response']['pdf'] ) ) {
			Andreani_Utils::andreani_log( '[BULK ETIQUETAS] Respuesta sin PDF', 'error' );
			wp_send_json_error( array(
				'message' => __( 'No se pudo obtener el PDF de las etiquetas.', 'andreani-shipping' ),
			), 500 );
		}

		$included = (int) $response['response']['included'];
		$skipped  = (int) $response['response']['skipped'];
		$filename = sprintf( 'etiquetas-andreani-%s.pdf', wp_date( 'Ymd-His' ) );

		Andreani_Utils::andreani_log( "[BULK ETIQUETAS] Generadas {$included} etiquetas, {$skipped} omitidas", 'info' );

		wp_send_json_success( array(
			'pdf'      => $response['response']['pdf'],
			'filename' => $filename,
			'included' => $included,
			'skipped'  => $skipped,
		) );
	}

	public function handle_get_print_settings() {
		check_ajax_referer( 'andreani_get_print_settings', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para ver la configuración de impresión.', 'andreani-shipping' ),
			), 403 );
		}

		$response = Andreani_Api_Manager::get_print_settings();

		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[SETTINGS] Error al obtener configuración de impresión: ' . $response->get_error_message(), 'error' );
			wp_send_json_error( array(
				'message' => __( 'No se pudo obtener la configuración de impresión.', 'andreani-shipping' ),
			), 500 );
		}

		wp_send_json_success( array(
			'key' => isset( $response['key'] ) ? (int) $response['key'] : 1,
		) );
	}

	public function handle_save_print_settings() {
		check_ajax_referer( 'andreani_save_print_settings', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para guardar la configuración de impresión.', 'andreani-shipping' ),
			), 403 );
		}

		$key     = isset( $_POST['key'] ) ? absint( $_POST['key'] ) : 0;
		$formats = self::get_print_formats();

		if ( ! isset( $formats[ $key ] ) ) {
			wp_send_json_error( array(
				'message' => __( 'Formato de impresión inválido.', 'andreani-shipping' ),
			), 400 );
		}

		$format   = $formats[ $key ];
		$response = Andreani_Api_Manager::save_print_settings( $key, $format['zebra'], $format['per_page'] );

		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[SETTINGS] Error al guardar configuración de impresión: ' . $response->get_error_message(), 'error' );
			wp_send_json_error( array(
				'message' => __( 'No se pudo guardar la configuración de impresión.', 'andreani-shipping' ),
			), 500 );
		}

		Andreani_Utils::andreani_log( "[SETTINGS] Configuración de impresión guardada (key={$key})", 'info' );

		wp_send_json_success( array(
			'message' => __( 'Configuración de impresión guardada.', 'andreani-shipping' ),
			'key'     => $key,
		) );
	}

	/**
	 * Mapa autoritativo formato → payload de la API. El cliente solo manda `key`;
	 * los flags formatoZebra/enviosPerPage se derivan acá para evitar combinaciones inválidas.
	 *
	 * @return array
	 */
	public static function get_print_formats() {
		return array(
			1 => array( 'zebra' => false, 'per_page' => 1, 'label' => __( 'Hoja A4 (1 etiqueta por hoja)', 'andreani-shipping' ) ),
			2 => array( 'zebra' => false, 'per_page' => 4, 'label' => __( 'Hoja A4 (4 etiquetas por hoja)', 'andreani-shipping' ) ),
			3 => array( 'zebra' => true,  'per_page' => 1, 'label' => __( 'Zebra (10x15)', 'andreani-shipping' ) ),
		);
	}

	public function handle_update_recipient_and_retry() {
		check_ajax_referer( 'andreani_update_recipient_and_retry', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para editar el envío.', 'andreani-shipping' ),
			), 403 );
		}

		$order_id = $this->get_order_id_from_request();
		if ( ! $order_id ) {
			wp_send_json_error( array(
				'message' => __( 'ID de orden inválido.', 'andreani-shipping' ),
			), 400 );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( array(
				'message' => __( 'Orden no encontrada.', 'andreani-shipping' ),
			), 404 );
		}

		if ( $order->get_meta( '_order_andreani_created', true ) ) {
			wp_send_json_error( array(
				'message' => __( 'El envío ya fue generado en Andreani. No se puede editar.', 'andreani-shipping' ),
			), 409 );
		}

		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$dni   = isset( $_POST['dni'] )   ? sanitize_text_field( wp_unslash( $_POST['dni'] ) )   : '';

		$errors = $this->validate_recipient_input( $phone, $dni );
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array(
				'message' => __( 'Datos inválidos.', 'andreani-shipping' ),
				'errors'  => $errors,
			), 422 );
		}

		$order->set_billing_phone( $phone );
		if ( method_exists( $order, 'set_shipping_phone' ) ) {
			$order->set_shipping_phone( $phone );
		}
		if ( '' !== $dni ) {
			$order->update_meta_data( '_billing_dni', $dni );
		}
		$order->save();

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Destinatario actualizado por admin (phone={$phone}, dni={$dni}), reintentando alta", 'info' );

		$this->fix_shipping_method_prefix( $order );

		$retry_count     = (int) $order->get_meta( '_andreani_retry_count', true );
		$current_attempt = $retry_count + 1;
		$order->update_meta_data( '_andreani_retry_count', $current_attempt );

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Llamando Andreani_Api_Manager::create_orden (intento {$current_attempt})", 'info' );
		$response = Andreani_Api_Manager::create_orden( $order_id );
		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] create_orden retornó: " . ( is_wp_error( $response ) ? 'WP_Error: ' . $response->get_error_message() : 'OK' ), 'info' );

		if ( is_wp_error( $response ) ) {
			$order->save();
			wp_send_json_error( array(
				'message'  => $response->get_error_message(),
				'type'     => 'error',
				'row_html' => $this->get_row_html( $order_id ),
			) );
		}

		$order->save();

		wp_send_json_success( array(
			'message'  => __( 'Datos actualizados y envío generado correctamente.', 'andreani-shipping' ),
			'type'     => 'success',
			'row_html' => $this->get_row_html( $order_id ),
		) );
	}

	private function validate_recipient_input( $phone, $dni ) {
		$errors = array();

		if ( '' === $phone ) {
			$errors['phone'] = __( 'El teléfono es obligatorio.', 'andreani-shipping' );
		} else {
			$phone_digits = preg_replace( '/\D/', '', $phone );
			if ( strlen( $phone_digits ) < 8 ) {
				$errors['phone'] = __( 'El teléfono debe tener al menos 8 dígitos.', 'andreani-shipping' );
			}
		}
		if ( '' === $dni ) {
			$errors['dni'] = __( 'El DNI/CUIT es obligatorio.', 'andreani-shipping' );
		} else {
			$dni_digits = preg_replace( '/\D/', '', $dni );
			if ( strlen( $dni_digits ) < 7 || strlen( $dni_digits ) > 11 ) {
				$errors['dni'] = __( 'El DNI/CUIT debe tener entre 7 y 11 dígitos.', 'andreani-shipping' );
			}
		}

		return $errors;
	}

	public function handle_load_shipments_table() {
		check_ajax_referer( 'andreani_load_shipments', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenés permisos para ver los envíos.', 'andreani-shipping' ),
			), 403 );
		}

		Andreani_Shipments_List::apply_ajax_request_args( $_POST );

		$list_table = new Andreani_Shipments_List();
		$list_table->prepare_items();

		ob_start();
		$list_table->display();
		$table_html = ob_get_clean();

		$table_html = $list_table->get_api_failure_notice_html()
			. $list_table->get_fallback_notice_html()
			. $table_html;

		wp_send_json_success( array(
			'html'        => $table_html,
			'total_items' => $list_table->get_pagination_arg( 'total_items' ),
			'total_pages' => $list_table->get_pagination_arg( 'total_pages' ),
		) );
	}

	public function handle_products_table() {
		check_ajax_referer( 'andreani_products_table', 'nonce' );

		// La sección de productos exige manage_woocommerce (igual que Andreani_Admin_Menu::CAPABILITY),
		// criterio más estricto que el trait user_can_manage_orders que usan los handlers de envíos.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		Andreani_Products_Stats::finish_small_backfill();

		$args = array(
			'search'   => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
			'service'  => isset( $_POST['service'] ) ? Andreani_Products_Stats::sanitize_list( wp_unslash( $_POST['service'] ), Andreani_Products_Stats::SERVICES ) : array(),
			'modes'    => isset( $_POST['mode'] ) ? Andreani_Products_Stats::sanitize_list( wp_unslash( $_POST['mode'] ), Andreani_Products_Stats::MODES ) : array(),
			'per_page' => Andreani_Products_List::clamp_per_page( isset( $_POST['per_page'] ) ? $_POST['per_page'] : Andreani_Products_List::PER_PAGE_DEFAULT ),
			'paged'    => isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1,
		);

		$data        = Andreani_Products_List::get_products_data( $args );
		$items       = $data['items'];
		$total       = $data['total'];
		$per_page    = $args['per_page'];
		$paged       = $args['paged'];
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		ob_start();

		if ( empty( $items ) ) {
			echo '<p class="andreani-products-empty">' . esc_html__( 'No se encontraron productos.', 'andreani-shipping' ) . '</p>';
		} else {
			include ANDREANI_PLUGIN_DIR . 'includes/admin/views/products-rows.php';
		}

		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'        => $html,
			'total_items' => $total,
			'total_pages' => $total_pages,
			'counts'      => Andreani_Products_Stats::get_counts(),
			'analyzing'   => Andreani_Products_Stats::progress(),
		) );
	}

	public function handle_sim_search() {
		check_ajax_referer( 'andreani_sim_search', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$search  = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
		$results = array();

		if ( '' === $search || mb_strlen( $search ) >= self::SIM_SEARCH_MIN ) {
			$ids = Andreani_Products_List::query_ids( array(
				'search'  => $search,
				'orderby' => '' === $search ? 'sales' : 'title',
				'limit'   => self::SIM_SEARCH_LIMIT,
			) );

			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( ! $product ) {
					continue;
				}

				$item = Andreani_Products_List::build_item( $product );

				$results[] = array(
					'id'       => $id,
					'name'     => $item['name'],
					'sku'      => $item['sku'],
					'thumb'    => $item['thumb_url'],
					'packages' => $item['packages'],
					'how'      => Andreani_Product_Bultos::MODE_SINGLE === $item['mode'] ? '' : Andreani_Products_List::how_it_travels( $item ),
					'mode'     => $item['mode'],
					'missing'  => 'missing' === $item['state'],
				);
			}
		}

		wp_send_json_success( array(
			'results' => $results,
			'limit'   => self::SIM_SEARCH_LIMIT,
		) );
	}

	public function handle_simulate_cart() {
		check_ajax_referer( 'andreani_simulate_cart', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$packages = array();
		$skipped  = array();

		foreach ( $this->parse_cart_lines() as $line ) {
			$product = wc_get_product( $line['product_id'] );

			if ( ! $product ) {
				continue;
			}

			if ( 'missing' === Andreani_Products_Stats::classify_product( $product )['state'] ) {
				$skipped[] = $line['product_id'];
				continue;
			}

			foreach ( Andreani_Package_Builder::draw_package_groups_for_product( $product, $line['quantity'] ) as $package ) {
				$package['product_id'] = $line['product_id'];
				$package['name']       = $product->get_name();
				$packages[]            = $package;
			}
		}

		wp_send_json_success( array(
			'packages' => $packages,
			'skipped'  => $skipped,
		) );
	}

	private function parse_cart_lines() {
		$raw   = isset( $_POST['lines'] ) && is_array( $_POST['lines'] ) ? wp_unslash( $_POST['lines'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$lines = array();

		foreach ( array_slice( $raw, 0, self::CART_LINES_MAX ) as $line ) {
			$product_id = is_array( $line ) && isset( $line['product_id'] ) ? absint( $line['product_id'] ) : 0;
			$quantity   = is_array( $line ) && isset( $line['quantity'] ) ? min( 99, absint( $line['quantity'] ) ) : 0;

			if ( $product_id && $quantity ) {
				$lines[] = array(
					'product_id' => $product_id,
					'quantity'   => $quantity,
				);
			}
		}

		return $lines;
	}

	private function parse_dispatch_draft( $lenient = false ) {
		$weight = isset( $_POST['weight'] ) ? floatval( $_POST['weight'] ) : 0.0;
		$length = isset( $_POST['length'] ) ? floatval( $_POST['length'] ) : 0.0;
		$width  = isset( $_POST['width'] )  ? floatval( $_POST['width'] )  : 0.0;
		$height = isset( $_POST['height'] ) ? floatval( $_POST['height'] ) : 0.0;
		if ( $weight < 0 || $length < 0 || $width < 0 || $height < 0 ) {
			return new WP_Error( 'negative_dims', __( 'Las dimensiones no pueden ser negativas.', 'andreani-shipping' ), 'dims' );
		}

		$mode = Andreani_Product_Bultos::sanitize_dispatch_mode(
			isset( $_POST['dispatch_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['dispatch_mode'] ) ) : ''
		);

		$apilado = array();
		if ( Andreani_Product_Bultos::MODE_APILADO === $mode ) {
			$apilado_json = isset( $_POST['apilado_json'] ) ? sanitize_textarea_field( wp_unslash( $_POST['apilado_json'] ) ) : '';
			$decoded      = '' !== $apilado_json ? json_decode( $apilado_json, true ) : array();

			if ( is_array( $decoded ) ) {
				$apilado = array(
					'maxStackableUnits'   => isset( $decoded['maxStackableUnits'] ) ? absint( $decoded['maxStackableUnits'] ) : 0,
					'unitIncrementHeight' => isset( $decoded['unitIncrementHeight'] ) ? floatval( $decoded['unitIncrementHeight'] ) : 0,
					'unitIncrementWidth'  => isset( $decoded['unitIncrementWidth'] ) ? floatval( $decoded['unitIncrementWidth'] ) : 0,
					'unitIncrementDepth'  => isset( $decoded['unitIncrementDepth'] ) ? floatval( $decoded['unitIncrementDepth'] ) : 0,
				);
			}

			if ( ! Andreani_Product_Apilado::is_valid( $apilado ) ) {
				if ( ! $lenient ) {
					return new WP_Error( 'invalid_apilado', Andreani_Product_Apilado::invalid_message(), 'apilado' );
				}

				$apilado = array();
			}
		}

		$bultos = array();
		if ( Andreani_Product_Bultos::MODE_MULTIBULTO === $mode ) {
			$bultos_json = isset( $_POST['bultos_json'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bultos_json'] ) ) : '';
			$decoded     = '' !== $bultos_json ? json_decode( $bultos_json, true ) : array();
			$rows        = is_array( $decoded ) ? $decoded : array();
			$bultos      = Andreani_Product_Bultos::pieces_from_rows( $rows );

			if ( ! $lenient && empty( $bultos ) ) {
				return new WP_Error( 'invalid_bultos', Andreani_Product_Bultos::bultos_invalid_message(), 'bultos' );
			}

			if ( ! $lenient && Andreani_Product_Bultos::has_incomplete_rows( $rows ) ) {
				return new WP_Error( 'incomplete_bultos', Andreani_Product_Bultos::bultos_incomplete_message(), 'bultos' );
			}
		}

		$main_ref = Andreani_Product_Bultos::MODE_MULTIBULTO === $mode && isset( $_POST['main_ref'] )
			? Andreani_Product_Bultos::sanitize_main_ref( wp_unslash( $_POST['main_ref'] ) )
			: '';

		return array(
			'weight'   => $weight,
			'length'   => $length,
			'width'    => $width,
			'height'   => $height,
			'mode'     => $mode,
			'apilado'  => $apilado,
			'bultos'   => $bultos,
			'main_ref' => $main_ref,
		);
	}

	public function handle_save_product_dims() {
		check_ajax_referer( 'andreani_save_product_dims', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'andreani-shipping' ) ), 400 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'andreani-shipping' ) ), 404 );
		}

		$draft = $this->parse_dispatch_draft();
		if ( is_wp_error( $draft ) ) {
			wp_send_json_error( array(
				'message' => $draft->get_error_message(),
				'field'   => $draft->get_error_data(),
			), 422 );
		}

		$weight  = $draft['weight'];
		$length  = $draft['length'];
		$width   = $draft['width'];
		$height  = $draft['height'];
		$mode    = $draft['mode'];
		$apilado = $draft['apilado'];
		$bultos  = $draft['bultos'];

		$sku_to_store = null;
		if ( isset( $_POST['sku'] ) ) {
			$sku_to_store = Andreani_Product_Bultos::sku_to_store( wc_clean( wp_unslash( $_POST['sku'] ) ), $product->get_sku( 'edit' ) );
			if ( '' !== $sku_to_store && $this->sku_in_use( $sku_to_store, $product_id ) ) {
				wp_send_json_error( array(
					'message' => __( 'Ese SKU ya lo usa otro producto.', 'andreani-shipping' ),
					'field'   => 'sku',
				), 422 );
			}
		}

		$product->set_weight( $weight > 0 ? $weight : '' );
		$product->set_length( $length > 0 ? $length : '' );
		$product->set_width( $width > 0 ? $width : '' );
		$product->set_height( $height > 0 ? $height : '' );
		$product->save();

		if ( ! empty( $bultos ) ) {
			update_post_meta( $product_id, Andreani_Product_Bultos::META_KEY, wp_slash( wp_json_encode( $bultos ) ) );
		} else {
			delete_post_meta( $product_id, Andreani_Product_Bultos::META_KEY );
		}

		if ( Andreani_Product_Bultos::MODE_APILADO === $mode ) {
			update_post_meta( $product_id, Andreani_Product_Apilado::META_KEY, wp_json_encode( $apilado ) );
		} else {
			delete_post_meta( $product_id, Andreani_Product_Apilado::META_KEY );
		}

		if ( $product->is_type( 'variation' ) ) {
			update_post_meta( $product_id, Andreani_Product_Bultos::MODE_META, $mode );
		}

		if ( null !== $sku_to_store ) {
			if ( '' !== $sku_to_store ) {
				update_post_meta( $product_id, Andreani_Product_Bultos::SKU_META_KEY, wp_slash( $sku_to_store ) );
			} else {
				delete_post_meta( $product_id, Andreani_Product_Bultos::SKU_META_KEY );
			}
		}

		Andreani_Product_Bultos::save_main_ref( $product_id, $draft['main_ref'] );

		Andreani_Products_Stats::flush_pending();

		$item = Andreani_Products_List::build_item( wc_get_product( $product_id ) );

		ob_start();
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/products-row.php';
		$row_html = ob_get_clean();

		wp_send_json_success( array(
			'message' => __( 'Dimensiones guardadas correctamente.', 'andreani-shipping' ),
			'html'    => $row_html,
			'counts'  => Andreani_Products_Stats::get_counts(),
		) );
	}

	private function sku_in_use( $sku, $product_id ) {
		global $wpdb;

		$own_id = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id != %d LIMIT 1",
			Andreani_Product_Bultos::SKU_META_KEY,
			$sku,
			$product_id
		) );
		if ( $own_id ) {
			return true;
		}

		$woo_id = (int) wc_get_product_id_by_sku( $sku );

		return $woo_id && $woo_id !== (int) $product_id;
	}

	private function apply_dispatch_draft( $product, array $draft ) {
		$product->set_weight( $draft['weight'] > 0 ? $draft['weight'] : '' );
		$product->set_length( $draft['length'] > 0 ? $draft['length'] : '' );
		$product->set_width( $draft['width'] > 0 ? $draft['width'] : '' );
		$product->set_height( $draft['height'] > 0 ? $draft['height'] : '' );

		$metas = array(
			Andreani_Product_Bultos::META_KEY  => ! empty( $draft['bultos'] ) ? wp_json_encode( $draft['bultos'] ) : '',
			Andreani_Product_Apilado::META_KEY => ! empty( $draft['apilado'] ) ? wp_json_encode( $draft['apilado'] ) : '',
			Andreani_Product_Bultos::MAIN_REF_META => $draft['main_ref'],
			Andreani_Product_Bultos::MODE_META => $draft['mode'],
		);
		$ids   = array_filter( array( $product->get_id(), $product->get_parent_id() ) );

		$filter = function ( $value, $object_id, $meta_key ) use ( $metas, $ids ) {
			if ( isset( $metas[ $meta_key ] ) && in_array( (int) $object_id, $ids, true ) ) {
				return array( $metas[ $meta_key ] );
			}

			return $value;
		};

		add_filter( 'get_post_metadata', $filter, 10, 3 );

		return $filter;
	}

	public function handle_preview_bultos() {
		check_ajax_referer( Andreani_Product_Bultos::PREVIEW_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$draft        = $this->parse_dispatch_draft( true );
		$quantity     = isset( $_POST['quantity'] ) ? max( 1, min( 99, absint( $_POST['quantity'] ) ) ) : 1;
		$max_quantity = isset( $_POST['max_quantity'] ) ? min( self::PREVIEW_MAX_QUANTITY, absint( $_POST['max_quantity'] ) ) : 0;
		$rows         = is_wp_error( $draft ) ? array() : Andreani_Product_Bultos::preview_rows_from_draft( $draft );
		$packages     = is_wp_error( $draft ) ? array() : Andreani_Product_Bultos::preview_packages_from_draft( $draft, $quantity );
		$by_quantity  = array();

		if ( ! is_wp_error( $draft ) ) {
			for ( $q = 1; $q <= $max_quantity; $q++ ) {
				$by_quantity[ $q ] = Andreani_Product_Bultos::preview_packages_from_draft( $draft, $q );
			}
		}

		wp_send_json_success( array(
			'rows'                 => $rows,
			'html'                 => Andreani_Product_Bultos::render_preview( $rows ),
			'packages'             => $packages,
			'packages_by_quantity' => $by_quantity,
		) );
	}

	public function handle_order_packing() {
		check_ajax_referer( Andreani_Order_Packing::NONCE, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$order = wc_get_order( isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0 );
		if ( ! $order ) {
			wp_send_json_error( array( 'message' => __( 'Pedido no encontrado.', 'andreani-shipping' ) ), 404 );
		}

		$lines = array();
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();

			$lines[] = array(
				'product'    => $product ? $product : null,
				'product_id' => $product ? $product->get_id() : 0,
				'name'       => $item->get_name(),
				'quantity'   => $item->get_quantity(),
				'url'        => $product ? (string) get_edit_post_link( $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id(), 'raw' ) : '',
			);
		}

		$data = Andreani_Shipments_List::get_order_shipment_data( $order );

		wp_send_json_success( Andreani_Order_Packing::build( $lines, $data['delivery_mode'] ) );
	}

	public function handle_test_quote() {
		check_ajax_referer( 'andreani_test_quote', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$cp_destino = isset( $_POST['cp_destino'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_destino'] ) ) : '';

		if ( '' === trim( $cp_destino ) ) {
			wp_send_json_error( array( 'message' => __( 'El CP de destino es obligatorio.', 'andreani-shipping' ) ), 422 );
		}

		$lines = $this->parse_cart_lines();

		if ( empty( $lines ) ) {
			$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
			$quantity   = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

			if ( ! $product_id ) {
				wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'andreani-shipping' ) ), 400 );
			}

			$lines = array(
				array(
					'product_id' => $product_id,
					'quantity'   => max( 1, min( 99, $quantity ) ),
				),
			);
		}

		$contents      = array();
		$contents_cost = 0.0;
		$skipped       = array();
		$draft_filter  = null;

		foreach ( $lines as $line ) {
			$product = wc_get_product( $line['product_id'] );
			if ( ! $product ) {
				wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'andreani-shipping' ) ), 404 );
			}

			if ( 1 === count( $lines ) && isset( $_POST['dispatch_mode'] ) ) {
				$draft = $this->parse_dispatch_draft();
				if ( is_wp_error( $draft ) ) {
					wp_send_json_error( array(
						'message' => $draft->get_error_message(),
						'field'   => $draft->get_error_data(),
					), 422 );
				}

				$draft_filter = $this->apply_dispatch_draft( $product, $draft );
			}

			if ( 'missing' === Andreani_Products_Stats::classify_product( $product )['state'] ) {
				$skipped[] = $product->get_name();
				continue;
			}

			$contents[]     = array(
				'data'     => $product,
				'quantity' => $line['quantity'],
			);
			$contents_cost += floatval( $product->get_price() ) * $line['quantity'];
		}

		if ( empty( $contents ) ) {
			wp_send_json_error( array(
				'message' => __( 'Este producto no tiene medidas completas. Editá las dimensiones antes de cotizar.', 'andreani-shipping' ),
				'reason'  => 'missing_dims',
			) );
		}

		// Package mínimo que espera get_cotizacion(): destination + contents.
		$package = array(
			'destination'   => array(
				'postcode' => $cp_destino,
				'country'  => 'AR',
			),
			'contents'      => $contents,
			'contents_cost' => $contents_cost,
		);

		if ( ! Andreani_Api_Manager::is_api_available() ) {
			wp_send_json_error( array(
				'message' => __( 'La API de Andreani no está configurada. Verificá las credenciales en Configuración.', 'andreani-shipping' ),
			) );
		}

		$result = Andreani_Api_Manager::get_cotizacion( $package );

		if ( $draft_filter ) {
			remove_filter( 'get_post_metadata', $draft_filter, 10 );
		}

		if ( is_null( $result ) ) {
			wp_send_json_error( array(
				'message' => __( 'No se pudo obtener cotización. Verificá la configuración de Andreani.', 'andreani-shipping' ),
			) );
		}

		if ( ! empty( $result['errors'] ) && empty( $result['rates'] ) ) {
			wp_send_json_error( array(
				'message' => implode( ' ', array_map( 'sanitize_text_field', (array) $result['errors'] ) ),
			) );
		}

		$rates = isset( $result['rates'] ) ? $result['rates'] : array();

		if ( empty( $rates ) ) {
			wp_send_json_error( array(
				'message' => __( 'No hay opciones de envío disponibles para ese código postal.', 'andreani-shipping' ),
			) );
		}

		$formatted = array();
		foreach ( $rates as $rate ) {
			$label = isset( $rate['label'] ) ? $rate['label'] : ( isset( $rate['id'] ) ? $rate['id'] : '' );
			$cost  = isset( $rate['cost'] ) ? floatval( $rate['cost'] ) : 0.0;
			$formatted[] = array(
				'id'    => isset( $rate['id'] ) ? $rate['id'] : '',
				'label' => $label,
				'cost'  => $cost,
			);
		}

		wp_send_json_success( array(
			'rates'   => $formatted,
			'skipped' => $skipped,
		) );
	}
}
