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
		add_action( 'wp_ajax_andreani_toggle_tracking_sync', array( $this, 'handle_toggle_tracking_sync' ) );

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
	 * Kill-switch del sync de seguimiento en segundo plano desde la grilla. Prende/apaga el
	 * proceso y (des)agenda el tick, para que el merchant pueda frenarlo sin entrar por FTP.
	 */
	public function handle_toggle_tracking_sync() {
		check_ajax_referer( 'andreani_toggle_tracking_sync', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenes permisos para cambiar la sincronización de seguimiento.', 'andreani-shipping' ),
			), 403 );
		}

		$enabled = isset( $_POST['enabled'] ) && '1' === (string) $_POST['enabled'];
		Andreani_Tracking_Sync::set_enabled( $enabled );

		wp_send_json_success( array(
			'enabled' => Andreani_Tracking_Sync::is_enabled(),
			'message' => $enabled
				? __( 'Seguimiento automático activado.', 'andreani-shipping' )
				: __( 'Seguimiento automático desactivado.', 'andreani-shipping' ),
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

		$args = array(
			'search'       => isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '',
			'missing_dims' => ! empty( $_POST['missing_dims'] ),
			'per_page'     => isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : Andreani_Products_List::PER_PAGE_DEFAULT,
			'paged'        => isset( $_POST['paged'] ) ? absint( $_POST['paged'] ) : 1,
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
			?>
			<div class="andreani-products-list">
				<?php foreach ( $items as $item ) : ?>
				<div class="andreani-product-item">
					<div class="andreani-product-item__main">
						<?php if ( ! empty( $item['thumb_url'] ) ) : ?>
							<img class="andreani-product-item__thumb" src="<?php echo esc_url( $item['thumb_url'] ); ?>" alt="" />
						<?php else : ?>
							<span class="andreani-product-item__thumb-ph" aria-hidden="true">
								<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
							</span>
						<?php endif; ?>
						<div class="andreani-product-item__info">
							<div class="andreani-product-item__title">
								<span class="andreani-product-item__name"><?php echo esc_html( $item['name'] ); ?></span>
								<?php if ( ! empty( $item['sku'] ) ) : ?>
									<span class="andreani-product-item__sku">· <?php echo esc_html( $item['sku'] ); ?></span>
								<?php endif; ?>
								<?php if ( $item['missing_dims'] ) : ?>
									<span class="andr-badge andr-badge--error andr-badge--sm"><?php esc_html_e( 'Sin dimensiones', 'andreani-shipping' ); ?></span>
								<?php endif; ?>
								<?php if ( $item['missing_weight'] ) : ?>
									<span class="andr-badge andr-badge--error andr-badge--sm"><?php esc_html_e( 'Sin peso', 'andreani-shipping' ); ?></span>
								<?php endif; ?>
								<?php if ( ! $item['missing_dims'] && ! $item['missing_weight'] ) : ?>
									<?php if ( ! empty( $item['is_bigger'] ) ) : ?>
										<span class="andr-badge andr-badge--info andr-badge--sm"><?php esc_html_e( 'Bigger', 'andreani-shipping' ); ?></span>
									<?php else : ?>
										<span class="andr-badge andr-badge--neutral andr-badge--sm"><?php esc_html_e( 'Paquetería', 'andreani-shipping' ); ?></span>
									<?php endif; ?>
								<?php endif; ?>
							</div>
							<div class="andreani-product-item__meas">
								<?php if ( ! $item['missing_dims'] ) : ?>
									<span class="andreani-meas">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" y1="22" x2="12" y2="12"/></svg>
										<?php echo esc_html( $item['length'] . '×' . $item['width'] . '×' . $item['height'] . ' cm' ); ?>
									</span>
								<?php endif; ?>
								<?php if ( ! $item['missing_weight'] ) : ?>
									<span class="andreani-meas">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>
										<?php echo esc_html( $item['weight'] . ' kg' ); ?>
									</span>
								<?php endif; ?>
								<?php if ( $item['bultos'] > 0 ) : ?>
									<span class="andreani-meas">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
										<?php printf( esc_html( _n( '%d bulto extra', '%d bultos extra', (int) $item['bultos'], 'andreani-shipping' ) ), (int) $item['bultos'] ); ?>
									</span>
								<?php endif; ?>
								<?php if ( $item['missing_dims'] && $item['missing_weight'] ) : ?>
									<span class="andreani-meas andreani-meas--empty"><?php esc_html_e( 'Cargá peso y medidas para poder cotizar', 'andreani-shipping' ); ?></span>
								<?php endif; ?>
							</div>
						</div>
					</div>
					<div class="andreani-product-item__actions">
						<button type="button"
							class="andr-btn andr-btn--icon andreani-product-edit-btn"
							title="<?php esc_attr_e( 'Editar dimensiones', 'andreani-shipping' ); ?>"
							aria-label="<?php esc_attr_e( 'Editar dimensiones', 'andreani-shipping' ); ?>"
							data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
							data-product-name="<?php echo esc_attr( $item['name'] ); ?>"
							data-thumb-url="<?php echo esc_url( isset( $item['thumb_url_lg'] ) ? $item['thumb_url_lg'] : '' ); ?>"
							data-weight="<?php echo esc_attr( $item['weight'] ); ?>"
							data-length="<?php echo esc_attr( $item['length'] ); ?>"
							data-width="<?php echo esc_attr( $item['width'] ); ?>"
							data-height="<?php echo esc_attr( $item['height'] ); ?>"
							data-bultos="<?php echo esc_attr( $item['bultos'] ); ?>"
							data-bultos-json="<?php echo esc_attr( wp_json_encode( isset( $item['bultos_data'] ) ? $item['bultos_data'] : array() ) ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
						</button>
						<button type="button"
							class="andr-btn andr-btn--icon andreani-product-quote-btn"
							title="<?php esc_attr_e( 'Probar cotización', 'andreani-shipping' ); ?>"
							aria-label="<?php esc_attr_e( 'Probar cotización', 'andreani-shipping' ); ?>"
							data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
							data-product-name="<?php echo esc_attr( $item['name'] ); ?>"
							data-thumb-url="<?php echo esc_url( isset( $item['thumb_url_lg'] ) ? $item['thumb_url_lg'] : '' ); ?>">
							<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
						</button>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
			<?php
			if ( $total_pages > 1 ) {
				echo '<div class="andreani-products-pagination">';
				echo '<span>' . sprintf(
					/* translators: 1: current page, 2: total pages */
					esc_html__( 'Página %1$d de %2$d', 'andreani-shipping' ),
					(int) $paged,
					(int) $total_pages
				) . '</span>';
				if ( $paged > 1 ) {
					echo '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-products-page-btn" data-paged="' . esc_attr( $paged - 1 ) . '">&laquo; ' . esc_html__( 'Anterior', 'andreani-shipping' ) . '</button>';
				}
				if ( $paged < $total_pages ) {
					echo '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-products-page-btn" data-paged="' . esc_attr( $paged + 1 ) . '">' . esc_html__( 'Siguiente', 'andreani-shipping' ) . ' &raquo;</button>';
				}
				echo '</div>';
			}
		}

		$html = ob_get_clean();

		wp_send_json_success( array(
			'html'        => $html,
			'total_items' => $total,
			'total_pages' => $total_pages,
		) );
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

		$weight = isset( $_POST['weight'] ) ? floatval( $_POST['weight'] ) : 0.0;
		$length = isset( $_POST['length'] ) ? floatval( $_POST['length'] ) : 0.0;
		$width  = isset( $_POST['width'] )  ? floatval( $_POST['width'] )  : 0.0;
		$height = isset( $_POST['height'] ) ? floatval( $_POST['height'] ) : 0.0;
		if ( $weight < 0 || $length < 0 || $width < 0 || $height < 0 ) {
			wp_send_json_error( array( 'message' => __( 'Las dimensiones no pueden ser negativas.', 'andreani-shipping' ) ), 422 );
		}

		$product->set_weight( $weight > 0 ? $weight : '' );
		$product->set_length( $length > 0 ? $length : '' );
		$product->set_width( $width > 0 ? $width : '' );
		$product->set_height( $height > 0 ? $height : '' );
		$product->save();

		// Bultos adicionales: llega el array completo (cada bulto con sus 4 medidas) en JSON.
		// Mismo criterio que Andreani_Product_Bultos: solo se persisten los bultos con las
		// 4 medidas > 0. El bulto principal es el peso/dimensiones de arriba.
		$bultos      = array();
		$bultos_json = isset( $_POST['bultos_json'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bultos_json'] ) ) : '';
		if ( '' !== $bultos_json ) {
			$decoded = json_decode( $bultos_json, true );
			if ( is_array( $decoded ) ) {
				foreach ( $decoded as $b ) {
					$bw = isset( $b['weight'] ) ? floatval( $b['weight'] ) : 0;
					$bx = isset( $b['width'] )  ? floatval( $b['width'] )  : 0;
					$by = isset( $b['height'] ) ? floatval( $b['height'] ) : 0;
					$bz = isset( $b['length'] ) ? floatval( $b['length'] ) : 0;
					if ( $bw > 0 && $bx > 0 && $by > 0 && $bz > 0 ) {
						$bultos[] = array( 'weight' => $bw, 'width' => $bx, 'height' => $by, 'length' => $bz );
					}
				}
			}
		}

		if ( ! empty( $bultos ) ) {
			update_post_meta( $product_id, Andreani_Product_Bultos::META_KEY, wp_json_encode( $bultos ) );
		} else {
			delete_post_meta( $product_id, Andreani_Product_Bultos::META_KEY );
		}

		wp_send_json_success( array(
			'message' => __( 'Dimensiones guardadas correctamente.', 'andreani-shipping' ),
		) );
	}

	public function handle_test_quote() {
		check_ajax_referer( 'andreani_test_quote', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$cp_destino = isset( $_POST['cp_destino'] ) ? sanitize_text_field( wp_unslash( $_POST['cp_destino'] ) ) : '';

		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'ID de producto inválido.', 'andreani-shipping' ) ), 400 );
		}
		if ( '' === trim( $cp_destino ) ) {
			wp_send_json_error( array( 'message' => __( 'El CP de destino es obligatorio.', 'andreani-shipping' ) ), 422 );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Producto no encontrado.', 'andreani-shipping' ) ), 404 );
		}

		// Verificamos dims antes de llamar a la API para dar un error claro.
		$weight = floatval( $product->get_weight() );
		$width  = floatval( $product->get_width() );
		$height = floatval( $product->get_height() );
		$length = floatval( $product->get_length() );

		if ( $weight <= 0 || $width <= 0 || $height <= 0 || $length <= 0 ) {
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
			'contents'      => array(
				array(
					'data'     => $product,
					'quantity' => 1,
				),
			),
			'contents_cost' => floatval( $product->get_price() ),
		);

		if ( ! Andreani_Api_Manager::is_api_available() ) {
			wp_send_json_error( array(
				'message' => __( 'La API de Andreani no está configurada. Verificá las credenciales en Configuración.', 'andreani-shipping' ),
			) );
		}

		$result = Andreani_Api_Manager::get_cotizacion( $package );

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
				'label' => $label,
				'cost'  => $cost,
			);
		}

		wp_send_json_success( array( 'rates' => $formatted ) );
	}
}
