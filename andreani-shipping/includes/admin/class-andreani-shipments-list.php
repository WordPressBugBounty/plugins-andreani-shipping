<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Andreani_Shipments_List extends WP_List_Table {

	const PER_PAGE_DEFAULT = 10;
	const PER_PAGE_OPTIONS = array( 5, 10, 25 );

	// Estados visuales del envío. Fuente de verdad: el TrackingStatus logístico real
	// (`_order_andreani_tracking_status`, lo asocia el pipeline de eventos); si no
	// existe, cae al meta `_order_andreani_shipping_status` y, por último, a los flags
	// `_order_andreani_created` + `_andreani_*_shipped` (ver `compute_shipping_status()`).
	const SHIPPING_STATUS_NOT_PACKAGED  = 'not_packaged';
	const SHIPPING_STATUS_PACKAGED      = 'packaged';
	const SHIPPING_STATUS_PENDING_ENTRY = 'pending_entry';
	const SHIPPING_STATUS_READY         = 'ready';
	const SHIPPING_STATUS_IN_TRANSIT    = 'in_transit';
	const SHIPPING_STATUS_READY_PICKUP  = 'ready_pickup';
	const SHIPPING_STATUS_DELIVERED     = 'delivered';
	const SHIPPING_STATUS_NOT_DELIVERED = 'not_delivered';
	const SHIPPING_STATUS_ERROR         = 'error';

	// Estado de pago — solo aplica a Pyme. Pasa de pending a paid cuando el
	// merchant carga el `_andreani_pyme_manual_tracking` (señal de que pagó
	// el envío desde el portal de Pymes Andreani).
	const PAYMENT_STATUS_PENDING = 'pending';
	const PAYMENT_STATUS_PAID    = 'paid';

	const META_SHIPPING_STATUS = '_order_andreani_shipping_status';
	const META_TRACKING_STATUS = '_order_andreani_tracking_status';

	/**
	 * Cuando la primera carga (sin filtros del user) trae 0 envíos hoy y
	 * caemos al fallback de los N últimos, esta propiedad guarda la cantidad
	 * para que el template muestre un banner discreto. 0 = sin fallback.
	 */
	public $fallback_recent_count = 0;

	/**
	 * True si el último hydrate() falló contra la API (timeout, 5xx, token
	 * vencido). El AJAX handler agrega un banner al merchant para que entienda
	 * por qué algunos campos pueden estar desactualizados.
	 */
	public $api_failure = false;

	public function __construct() {
		parent::__construct( array(
			'singular' => __( 'Envío', 'andreani-shipping' ),
			'plural'   => __( 'Envíos', 'andreani-shipping' ),
			'ajax'     => true,
		) );
	}

	/**
	 * Inyecta args desde una request AJAX al $_REQUEST con sanitización + whitelist.
	 * WP_List_Table lee paginación, sort y filtros desde $_REQUEST; este método
	 * centraliza el mapping (POST → $_REQUEST) sin que cada handler lo replique.
	 */
	public static function apply_ajax_request_args( array $source ) {
		if ( isset( $source['paged'] ) ) {
			$_REQUEST['paged'] = absint( $source['paged'] );
		}

		if ( isset( $source['per_page'] ) ) {
			$candidate = absint( $source['per_page'] );
			if ( in_array( $candidate, self::PER_PAGE_OPTIONS, true ) ) {
				$_REQUEST['per_page'] = $candidate;
			}
		}

		$text_keys = array( 'orderby', 'order', 'client_type', 'andreani_status', 's', 'andreani_date_from', 'andreani_date_to' );
		foreach ( $text_keys as $key ) {
			if ( isset( $source[ $key ] ) ) {
				$_REQUEST[ $key ] = sanitize_text_field( wp_unslash( $source[ $key ] ) );
			}
		}
	}

	public static function resolve_per_page() {
		if ( isset( $_REQUEST['per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, sanitized.
			$candidate = absint( $_REQUEST['per_page'] );
			if ( in_array( $candidate, self::PER_PAGE_OPTIONS, true ) ) {
				return $candidate;
			}
		}
		return self::PER_PAGE_DEFAULT;
	}

	public function get_columns() {
		$columns = array(
			'cb'           => '<input type="checkbox" />',
			'order_number' => __( 'Pedido', 'andreani-shipping' ),
			'customer'     => __( 'Cliente', 'andreani-shipping' ),
			'destination'  => __( 'Destino', 'andreani-shipping' ),
			'service'      => __( 'Servicio', 'andreani-shipping' ),
			'payment'      => __( 'Pago', 'andreani-shipping' ),
			'status'       => __( 'Estado', 'andreani-shipping' ),
			'tracking'     => __( 'Seguimiento', 'andreani-shipping' ),
			'date'         => __( 'Fecha', 'andreani-shipping' ),
			'actions'      => __( 'Acciones', 'andreani-shipping' ),
		);

		return $columns;
	}

	public function get_sortable_columns() {
		return array(
			'order_number' => array( 'order_id', false ),
			'date'         => array( 'date', true ),
			'status'       => array( 'status', false ),
		);
	}

	public function column_cb( $item ) {
		// Metadata para que la bulk bar evalúe qué acciones aplican al 100% de
		// la selección (descargar etiqueta, re-empaquetar, etc.) sin volver a
		// pegarle al server. Los nombres `data-*` son convención del frontend.
		return sprintf(
			'<input type="checkbox" name="order_ids[]" value="%s" data-status="%s" data-has-tracking="%s" data-shipped="%s" data-has-error="%s" data-client-type="%s" data-payment-pending="%s" />',
			esc_attr( $item['order_id'] ),
			esc_attr( isset( $item['shipping_status'] ) ? $item['shipping_status'] : '' ),
			! empty( $item['display_tracking'] ) ? '1' : '0',
			! empty( $item['shipped'] ) ? '1' : '0',
			! empty( $item['last_error'] ) ? '1' : '0',
			esc_attr( isset( $item['client_type'] ) ? $item['client_type'] : '' ),
			self::is_payment_pending( $item ) ? '1' : '0'
		);
	}

	private static function is_payment_pending( $item ) {
		$type_info = Andreani_Client_Type::from_id( isset( $item['client_type'] ) ? $item['client_type'] : '' );
		if ( ! $type_info || ! $type_info->has_payment_step() ) {
			return false;
		}

		$shipping_status = isset( $item['shipping_status'] ) ? $item['shipping_status'] : '';
		$payment_status  = isset( $item['payment_status'] ) ? $item['payment_status'] : '';

		return self::SHIPPING_STATUS_PACKAGED === $shipping_status && self::PAYMENT_STATUS_PENDING === $payment_status;
	}

	public function column_order_number( $item ) {
		$order_url   = $this->get_order_edit_url( $item['order_id'] );
		$client_type = isset( $item['client_type'] ) ? $item['client_type'] : '';
		$type_info   = Andreani_Client_Type::from_id( $client_type );

		$type_caption = $type_info
			? sprintf(
				'<span class="andreani-order-number__type andreani-order-number__type--%1$s">%2$s</span>',
				esc_attr( $type_info->id() ),
				esc_html( $type_info->short_label() )
			)
			: '';

		$chevron = '<svg class="andreani-order-number__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';

		return sprintf(
			'<div class="andreani-order-number-wrap"><a href="%1$s" target="_blank" rel="noopener" class="andreani-order-number"><span class="andreani-order-number__hash">#</span><span class="andreani-order-number__num">%2$s</span></a>%3$s%4$s</div>',
			esc_url( $order_url ),
			esc_html( $item['order_number'] ),
			$type_caption,
			$chevron // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		);
	}

	public function column_customer( $item ) {
		$name  = esc_html( $item['customer_name'] );
		$email = isset( $item['customer_email'] ) ? trim( (string) $item['customer_email'] ) : '';
		$dni   = isset( $item['dni'] ) ? trim( (string) $item['dni'] ) : '';

		$meta_lines = array();

		if ( '' !== $email ) {
			$meta_lines[] = '<a href="mailto:' . esc_attr( $email ) . '" class="andreani-customer__email">' . esc_html( $email ) . '</a>';
		}

		if ( '' !== $dni ) {
			$meta_lines[] = '<span class="andreani-customer__dni">' . esc_html__( 'DNI:', 'andreani-shipping' ) . ' ' . esc_html( $dni ) . '</span>';
		}

		$meta = '';
		if ( $meta_lines ) {
			$meta = '<div class="andreani-customer__meta">' . implode( '<br>', $meta_lines ) . '</div>';
		}

		return '<div class="andreani-customer__name">' . $name . '</div>' . $meta;
	}

	public function column_destination( $item ) {
		$address_1 = isset( $item['shipping_address_1'] ) ? trim( (string) $item['shipping_address_1'] ) : '';
		$address_2 = isset( $item['shipping_address_2'] ) ? trim( (string) $item['shipping_address_2'] ) : '';
		$city      = isset( $item['shipping_city'] ) ? trim( (string) $item['shipping_city'] ) : '';
		$state     = isset( $item['shipping_state'] ) ? trim( (string) $item['shipping_state'] ) : '';
		$cp        = isset( $item['shipping_postcode'] ) ? trim( (string) $item['shipping_postcode'] ) : '';

		if ( '' === $address_1 && '' === $city && '' === $cp ) {
			return '<span class="andreani-destination--empty">—</span>';
		}

		$line_1_parts = array_filter( array( $address_1, $address_2 ) );
		$line_2_parts = array_filter( array( $city, $state, $cp ) );

		$line_1 = $line_1_parts
			? '<div class="andreani-destination__line">' . esc_html( implode( ', ', $line_1_parts ) ) . '</div>'
			: '';
		$line_2 = $line_2_parts
			? '<div class="andreani-destination__line andreani-destination__line--muted">' . esc_html( implode( ', ', $line_2_parts ) ) . '</div>'
			: '';

		return '<div class="andreani-destination">' . $line_1 . $line_2 . '</div>';
	}

	public function column_client_type( $item ) {
		$type_info = Andreani_Client_Type::from_id( $item['client_type'] );
		$label     = $type_info ? $type_info->short_label() : 'Pyme';
		$class     = $type_info ? $type_info->badge_css_class() : 'andr-badge--pyme';

		return sprintf(
			'<span class="andr-badge %s">%s</span>',
			esc_attr( $class ),
			esc_html( $label )
		);
	}

	public function column_service( $item ) {
		$mode   = isset( $item['delivery_mode'] ) ? (string) $item['delivery_mode'] : '';
		$config = self::get_service_config( $mode );

		return sprintf(
			'<span class="andr-badge andr-badge--icon-only %s" data-label="%s" aria-label="%s">%s</span>',
			esc_attr( $config['class'] ),
			esc_attr( $config['label'] ),
			esc_attr( $config['label'] ),
			$config['icon'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		);
	}

	/**
	 * Mapea `delivery_mode` (que llega del meta con tildes, espacios y mayúsculas
	 * variables según el origen) a un chip visual estable.
	 */
	public static function get_service_config( $mode ) {
		$normalized = function_exists( 'remove_accents' ) ? remove_accents( $mode ) : $mode;
		$normalized = strtolower( trim( $normalized ) );
		$normalized = str_replace( array( '-', '_' ), ' ', $normalized );

		$icon_home       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>';
		$icon_zap        = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>';
		$icon_truck_big  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
		$icon_store      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9h18l-1.5 -3.5a2 2 0 0 0 -1.84 -1.5H6.34a2 2 0 0 0 -1.84 1.5L3 9z"/><path d="M3 9v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2 -2V9"/><path d="M9 22V12h6v10"/></svg>';

		if ( false !== strpos( $normalized, 'llega' ) || false !== strpos( $normalized, 'arrives' ) ) {
			return array(
				'label' => __( 'Llega hoy', 'andreani-shipping' ),
				'class' => 'andr-badge--neutral',
				'icon'  => $icon_zap,
			);
		}

		if ( false !== strpos( $normalized, 'sucursal' ) ) {
			return array(
				'label' => __( 'Sucursal', 'andreani-shipping' ),
				'class' => 'andr-badge--neutral',
				'icon'  => $icon_store,
			);
		}

		if ( false !== strpos( $normalized, 'bigger' ) ) {
			return array(
				'label' => __( 'Bigger', 'andreani-shipping' ),
				'class' => 'andr-badge--neutral',
				'icon'  => $icon_truck_big,
			);
		}

		return array(
			'label' => __( 'Estándar', 'andreani-shipping' ),
			'class' => 'andr-badge--neutral',
			'icon'  => $icon_home,
		);
	}

	public function column_payment( $item ) {
		$payment = isset( $item['payment_status'] ) ? $item['payment_status'] : '';

		if ( '' === $payment ) {
			return '<span class="andreani-payment--na">—</span>';
		}

		$config = self::get_payment_status_config( $payment );

		return sprintf(
			'<span class="andr-badge andr-badge--icon-only %s" data-label="%s" aria-label="%s">%s</span>',
			esc_attr( $config['class'] ),
			esc_attr( $config['label'] ),
			esc_attr( $config['label'] . ' — ' . $config['hint'] ),
			$config['icon'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		);
	}

	public function column_status( $item ) {
		$shipping_status  = isset( $item['shipping_status'] ) ? $item['shipping_status'] : self::SHIPPING_STATUS_NOT_PACKAGED;
		$type_info        = Andreani_Client_Type::from_id( isset( $item['client_type'] ) ? $item['client_type'] : '' );
		$has_payment_step = $type_info ? $type_info->has_payment_step() : false;

		$config = self::get_shipping_status_config( $shipping_status, $has_payment_step );

		$has_error = self::SHIPPING_STATUS_NOT_PACKAGED === $shipping_status && ! empty( $item['last_error'] );

		if ( $has_error ) {
			$label     = __( 'Hubo un error al empaquetar', 'andreani-shipping' );
			$aria      = $label;
			$extra_cls = ' andr-status--has-error';
			$dot       = '<span class="andr-status__error-dot" aria-hidden="true"></span>';
		} else {
			$label     = $config['label'];
			$aria      = $config['label'] . ' — ' . $config['hint'];
			$extra_cls = '';
			$dot       = '';
		}

		return sprintf(
			'<span class="andr-status andr-status--icon-only %s%s" data-label="%s" aria-label="%s">%s%s</span>',
			esc_attr( $config['class'] ),
			esc_attr( $extra_cls ),
			esc_attr( $label ),
			esc_attr( $aria ),
			$config['icon'], // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$dot // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML estatico interno.
		);
	}

	public function column_tracking( $item ) {
		$type_info       = Andreani_Client_Type::from_id( isset( $item['client_type'] ) ? $item['client_type'] : '' );
		$internal_number = isset( $item['internal_number'] ) ? trim( (string) $item['internal_number'] ) : '';
		$tracking_num    = isset( $item['tracking_number'] ) ? trim( (string) $item['tracking_number'] ) : '';
		$display         = isset( $item['display_tracking'] ) ? trim( (string) $item['display_tracking'] ) : '';

		$pieces = array();

		if ( $type_info && $type_info->can( 'id_orden_display' ) ) {
			if ( '' !== $internal_number ) {
				$pieces[] = $this->build_identifier_row( __( 'ID orden', 'andreani-shipping' ), $internal_number );
			}
			$nro_seg = $tracking_num ?: $display;
			if ( '' !== $nro_seg ) {
				$pieces[] = $this->build_identifier_row( __( 'Nro seg', 'andreani-shipping' ), $nro_seg );
			}
		} else {
			$nro_seg = $display ?: $tracking_num;
			if ( '' !== $nro_seg ) {
				$pieces[] = $this->build_identifier_row( __( 'Nro seg', 'andreani-shipping' ), $nro_seg );
			}
		}

		if ( empty( $pieces ) ) {
			return '<span class="andreani-tracking--empty">—</span>';
		}

		return '<div class="andreani-identifier-stack">' . implode( '', $pieces ) . '</div>';
	}

	private function build_identifier_row( $label, $value ) {
		return sprintf(
			'<span class="andreani-identifier andreani-copy-click" data-tracking="%1$s" title="%2$s"><span class="andreani-identifier__label">%3$s</span><span class="andreani-identifier__value">%1$s</span></span>',
			esc_attr( $value ),
			esc_attr__( 'Click para copiar', 'andreani-shipping' ),
			esc_html( $label )
		);
	}

	public function column_date( $item ) {
		$date = $item['date_created'];
		if ( empty( $date ) ) {
			return '-';
		}

		$timestamp = strtotime( $date );
		return sprintf(
			'<span title="%s">%s</span>',
			esc_attr( date_i18n( 'Y-m-d H:i:s', $timestamp ) ),
			esc_html( date_i18n( 'd/m/Y', $timestamp ) )
		);
	}

	public function column_actions( $item ) {
		$actions = array();

		if ( self::is_payment_pending( $item ) ) {
			$actions[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener" class="andr-btn andr-btn--icon andr-btn--ghost andr-btn--sm andreani-action--pay" title="%s" aria-label="%s">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
				</a>',
				esc_url( ANDREANI_PYME_HISTORIAL_URL ),
				esc_attr__( 'Pagar en Andreani Pymes', 'andreani-shipping' ),
				esc_attr__( 'Pagar', 'andreani-shipping' )
			);
		}

		if ( ! empty( $item['display_tracking'] ) ) {
			$tracking_url = 'https://www.andreani.com/envio/' . rawurlencode( $item['display_tracking'] );
			$actions[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener" class="andr-btn andr-btn--icon andr-btn--ghost andr-btn--sm" title="%s">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
				</a>',
				esc_url( $tracking_url ),
				esc_attr__( 'Ver seguimiento', 'andreani-shipping' )
			);
		}

		$item_type_info = Andreani_Client_Type::from_id( $item['client_type'] );
		if ( $item_type_info && $item_type_info->supports_label_pdf() && ! empty( $item['display_tracking'] ) ) {
			$actions[] = sprintf(
				'<button type="button" class="andr-btn andr-btn--icon andr-btn--ghost andr-btn--sm andreani-download-label"
					data-order-id="%s"
					data-ajax-url="%s"
					data-action="andreani_get_etiqueta"
					data-nonce="%s"
					title="%s">
					<span class="dashicons dashicons-media-document"></span>
				</button>',
				esc_attr( $item['order_id'] ),
				esc_url( admin_url( 'admin-ajax.php' ) ),
				esc_attr( wp_create_nonce( 'andreani_get_etiqueta' ) ),
				esc_attr__( 'Descargar etiqueta', 'andreani-shipping' )
			);
		}

		if ( 'error' === $item['andreani_status'] && $item['can_retry'] ) {
			$actions[] = sprintf(
				'<button type="button" class="andr-btn andr-btn--icon andr-btn--ghost andr-btn--sm andreani-retry"
					data-order-id="%s"
					data-ajax-url="%s"
					data-action="andreani_retry_order"
					data-nonce="%s"
					title="%s">
					<span class="dashicons dashicons-update"></span>
				</button>',
				esc_attr( $item['order_id'] ),
				esc_url( admin_url( 'admin-ajax.php' ) ),
				esc_attr( wp_create_nonce( 'andreani_retry_order' ) ),
				esc_attr__( 'Reintentar', 'andreani-shipping' )
			);
		}

		if ( empty( $actions ) ) {
			return '<span class="andreani-actions--empty">-</span>';
		}

		return '<div class="andreani-actions">' . implode( '', $actions ) . '</div>';
	}

	public function no_items() {
		esc_html_e( 'No se encontraron envios de Andreani.', 'andreani-shipping' );
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$client_type      = isset( $_REQUEST['client_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['client_type'] ) ) : '';
		$status           = isset( $_REQUEST['andreani_status'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['andreani_status'] ) ) : '';
		?>
		<div class="alignleft actions">
			<select name="client_type">
				<option value=""><?php esc_html_e( 'Todos los tipos', 'andreani-shipping' ); ?></option>
				<?php foreach ( Andreani_Client_Type::all() as $type_option ) : ?>
					<option value="<?php echo esc_attr( $type_option->id() ); ?>" <?php selected( $client_type, $type_option->id() ); ?>><?php echo esc_html( $type_option->label() ); ?></option>
				<?php endforeach; ?>
			</select>

			<select name="andreani_status">
				<option value=""><?php esc_html_e( 'Todos los estados', 'andreani-shipping' ); ?></option>
				<option value="not_packaged" <?php selected( $status, 'not_packaged' ); ?>><?php esc_html_e( 'Por empaquetar', 'andreani-shipping' ); ?></option>
				<option value="pending_entry" <?php selected( $status, 'pending_entry' ); ?>><?php esc_html_e( 'Pendiente de ingreso', 'andreani-shipping' ); ?></option>
				<option value="in_transit" <?php selected( $status, 'in_transit' ); ?>><?php esc_html_e( 'En camino', 'andreani-shipping' ); ?></option>
				<option value="ready_pickup" <?php selected( $status, 'ready_pickup' ); ?>><?php esc_html_e( 'Listo para retirar', 'andreani-shipping' ); ?></option>
				<option value="delivered" <?php selected( $status, 'delivered' ); ?>><?php esc_html_e( 'Entregado', 'andreani-shipping' ); ?></option>
				<option value="not_delivered" <?php selected( $status, 'not_delivered' ); ?>><?php esc_html_e( 'No entregado', 'andreani-shipping' ); ?></option>
				<option value="error" <?php selected( $status, 'error' ); ?>><?php esc_html_e( 'Error', 'andreani-shipping' ); ?></option>
			</select>

			<?php submit_button( __( 'Filtrar', 'andreani-shipping' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function get_bulk_actions() {
		return array();
	}

	/**
	 * Imprime el `<tr>` principal y un `<tr.andreani-detail-row>` adyacente con el
	 * detalle eager-loaded — el toggle es 100% client-side, sin AJAX al expandir.
	 *
	 * `tabindex="0"` habilita Tab + Enter/Space sobre el row para accesibilidad.
	 */
	/**
	 * Banner cuando la API del back está caída y no pudimos hidratar la grilla.
	 * Los datos visibles son los del WC meta — pueden estar desactualizados.
	 * El AJAX handler lo concatena junto al fallback notice.
	 */
	public function get_api_failure_notice_html() {
		if ( ! $this->api_failure ) {
			return '';
		}

		$message = esc_html__( 'No pudimos conectar con la API de Andreani. Mostrando datos locales — algunos campos pueden estar desactualizados.', 'andreani-shipping' );

		return '<div class="andreani-api-notice" role="status" data-auto-dismiss="10000">'
			. '<svg class="andreani-api-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 1l22 22"/><path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/><path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/><path d="M10.71 5.05A16 16 0 0 1 22.58 9"/><path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/><path d="M8.53 16.11a6 6 0 0 1 6.95 0"/><line x1="12" y1="20" x2="12.01" y2="20"/></svg>'
			. '<span class="andreani-api-notice__text">' . $message . '</span>'
			. '<button type="button" class="andreani-api-notice__close" aria-label="' . esc_attr__( 'Cerrar', 'andreani-shipping' ) . '">'
			. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
			. '</button>'
			. '</div>';
	}

	/**
	 * Banner que se renderiza cuando la grilla cae al fallback de "últimos N"
	 * por falta de envíos hoy. El mismo HTML se usa en el render inicial del
	 * template y en la respuesta AJAX cuando el merchant pagina/filtra y vuelve
	 * a una vista limpia.
	 */
	public function get_fallback_notice_html() {
		if ( $this->fallback_recent_count <= 0 ) {
			return '';
		}

		$message = sprintf(
			/* translators: %d: cantidad de envíos mostrados en el fallback */
			esc_html__( 'Sin envíos hoy. Mostrando los últimos %d.', 'andreani-shipping' ),
			(int) $this->fallback_recent_count
		);

		return '<div class="andreani-fallback-notice" role="status" data-auto-dismiss="6000">'
			. '<svg class="andreani-fallback-notice__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>'
			. '<span class="andreani-fallback-notice__text">' . $message . '</span>'
			. '<button type="button" class="andreani-fallback-notice__close" aria-label="' . esc_attr__( 'Cerrar', 'andreani-shipping' ) . '">'
			. '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'
			. '</button>'
			. '</div>';
	}

	public function single_row( $item ) {
		$order_id  = $item['order_id'];
		$has_error = ! empty( $item['last_error'] ) ? '1' : '0';
		echo '<tr data-order-id="' . esc_attr( $order_id ) . '" data-has-error="' . esc_attr( $has_error ) . '" tabindex="0">';
		$this->single_row_columns( $item );
		echo '</tr>';

		$detail_html = Andreani_Shipment_Detail_View::render( $order_id, $item );
		if ( '' === $detail_html ) {
			// Si la orden desapareció entre la query y el render, evitamos emitir el
			// detail row para no dejar un `<tr>` huérfano que rompa el layout.
			return;
		}

		$col_count = count( $this->get_columns() );
		echo '<tr class="andreani-detail-row" data-detail-for="' . esc_attr( $order_id ) . '">';
		echo '<td colspan="' . absint( $col_count ) . '" class="andreani-detail-row__cell">';
		echo '<div class="andreani-detail-row__content">';
		echo $detail_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML interno escapado en Andreani_Shipment_Detail_View::render
		echo '</div>';
		echo '</td>';
		echo '</tr>';
	}

	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$per_page     = self::resolve_per_page();
		$current_page = $this->get_pagenum();

		$args = $this->build_query_args( $current_page, $per_page );
		$data = $this->get_shipments_data( $args );

		$this->items                 = $data['items'];
		$this->fallback_recent_count = isset( $data['fallback_recent_count'] ) ? (int) $data['fallback_recent_count'] : 0;
		$this->api_failure           = ! empty( $data['api_failure'] );

		$this->set_pagination_args( array(
			'total_items' => $data['total'],
			'per_page'    => $per_page,
			'total_pages' => ceil( $data['total'] / $per_page ),
		) );
	}

	private function build_query_args( $current_page, $per_page ) {
		$args = array(
			'limit'   => $per_page,
			'offset'  => ( $current_page - 1 ) * $per_page,
			'orderby' => 'date',
			'order'   => 'DESC',
		);

		if ( ! empty( $_REQUEST['orderby'] ) ) {
			$args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) );
		}
		if ( ! empty( $_REQUEST['order'] ) ) {
			$args['order'] = strtoupper( sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) ) === 'ASC' ? 'ASC' : 'DESC';
		}
		if ( ! empty( $_REQUEST['client_type'] ) ) {
			$args['client_type'] = sanitize_text_field( wp_unslash( $_REQUEST['client_type'] ) );
		}
		if ( ! empty( $_REQUEST['andreani_status'] ) ) {
			$args['andreani_status'] = sanitize_text_field( wp_unslash( $_REQUEST['andreani_status'] ) );
		}
		if ( ! empty( $_REQUEST['s'] ) ) {
			$args['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
		}
		if ( ! empty( $_REQUEST['andreani_date_from'] ) ) {
			$args['date_from'] = sanitize_text_field( wp_unslash( $_REQUEST['andreani_date_from'] ) );
		}
		if ( ! empty( $_REQUEST['andreani_date_to'] ) ) {
			$args['date_to'] = sanitize_text_field( wp_unslash( $_REQUEST['andreani_date_to'] ) );
		}

		// Default UX: si el merchant abre la grilla sin ningún filtro, mostramos
		// solo los envíos de hoy. Si no hay, get_shipments_data hace fallback a
		// los 15 más recientes. Solo se aplica a la página 1 para no sabotear
		// la paginación cuando el merchant ya está navegando.
		$has_user_filter = isset( $args['date_from'] )
			|| isset( $args['date_to'] )
			|| ! empty( $args['andreani_status'] )
			|| ! empty( $args['client_type'] )
			|| ! empty( $args['search'] );

		if ( ! $has_user_filter && 1 === $current_page ) {
			$args['date_from']        = wp_date( 'Y-m-d' );
			$args['is_default_today'] = true;
		}

		return $args;
	}

	private static function order_tables() {
		global $wpdb;

		// Identificadores de tabla/columna de este whitelist, nunca de input => interpolarlos es seguro.
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return array(
				'orders'     => "{$wpdb->prefix}wc_orders",
				'meta'       => "{$wpdb->prefix}wc_orders_meta",
				'id_col'     => 'id',
				'fk_col'     => 'order_id',
				'type_col'   => 'type',
				'status_col' => 'status',
			);
		}

		return array(
			'orders'     => $wpdb->posts,
			'meta'       => $wpdb->postmeta,
			'id_col'     => 'ID',
			'fk_col'     => 'post_id',
			'type_col'   => 'post_type',
			'status_col' => 'post_status',
		);
	}

	private static function andreani_exists_sql( $order_id_expr ) {
		global $wpdb;

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$wpdb->prefix}woocommerce_order_items oi
			 INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oim.order_item_id = oi.order_item_id AND oim.meta_key = 'method_id' AND ( oim.meta_value = %s OR oim.meta_value LIKE %s )
			 WHERE oi.order_id = {$order_id_expr} AND oi.order_item_type = 'shipping')",
			ANDREANI_SHIPPING_METHOD_ID,
			$wpdb->esc_like( ANDREANI_SHIPPING_METHOD_ID ) . ':%'
		);
	}

	private static function andreani_orders_from_sql() {
		global $wpdb;

		$t        = self::order_tables();
		$statuses = array_keys( wc_get_order_statuses() );
		$in       = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		return $wpdb->prepare(
			"FROM {$t['orders']} o WHERE o.{$t['type_col']} = 'shop_order' AND o.{$t['status_col']} IN ( {$in} ) AND ",
			$statuses
		) . self::andreani_exists_sql( "o.{$t['id_col']}" );
	}

	private static function meta_exists_sql( $meta_key, $value_sql = '', $value_args = array() ) {
		global $wpdb;

		$t = self::order_tables();

		return $wpdb->prepare(
			"EXISTS (SELECT 1 FROM {$t['meta']} m WHERE m.{$t['fk_col']} = o.{$t['id_col']} AND m.meta_key = %s{$value_sql})",
			array_merge( array( $meta_key ), $value_args )
		);
	}

	private static function count_andreani_orders_sql( $where_sql = '' ) {
		return 'SELECT COUNT(*) ' . self::andreani_orders_from_sql() . $where_sql;
	}

	public static function register_query_scope() {
		add_filter( 'posts_where', array( __CLASS__, 'scope_posts_where' ), 10, 2 );
		add_filter( 'woocommerce_orders_table_query_clauses', array( __CLASS__, 'scope_orders_table_clauses' ), 10, 3 );
	}

	public static function scope_posts_where( $where, $query ) {
		global $wpdb;

		if ( ! $query instanceof WP_Query || ! $query->get( 'andreani_only' ) ) {
			return $where;
		}

		return $where . ' AND ' . self::andreani_exists_sql( "{$wpdb->posts}.ID" );
	}

	public static function scope_orders_table_clauses( $clauses, $query, $args ) {
		if ( empty( $args['andreani_only'] ) || ! isset( $clauses['where'] ) ) {
			return $clauses;
		}

		$clauses['where'] .= ' AND ' . self::andreani_exists_sql( $query->get_table_name( 'orders' ) . '.id' );

		return $clauses;
	}

	/**
	 * Conteo agregado de envios por estado para la stats bar.
	 *
	 * Cachea el resultado en un transient (5 min) para evitar las queries por cada
	 * render de la pagina. La invalidacion se centraliza en `invalidate_stats_cache()`
	 * (hookeada a `updated_post_meta`/`added_post_meta`/`deleted_post_meta` para las
	 * meta keys relevantes), de modo que cualquier cambio sobre el estado de un
	 * envio purga el transient automaticamente sin requerir purgas explicitas en
	 * cada handler AJAX.
	 *
	 * @return array{
	 *     pending:int, awaiting:int, ready:int, shipped:int, failed:int, total:int
	 * }
	 */
	public static function get_shipment_stats() {
		$cache_key = 'andreani_shipment_stats';
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['total'] ) ) {
			return $cached;
		}

		global $wpdb;

		$stats = array(
			'pending'  => 0,
			'awaiting' => 0,
			'ready'    => 0,
			'shipped'  => 0,
			'failed'   => 0,
			'total'    => 0,
		);

		$stats['total'] = (int) $wpdb->get_var( self::count_andreani_orders_sql() );

		$shipped_conditions = array();
		foreach ( Andreani_Client_Type::all_shipped_meta_keys() as $shipped_key ) {
			$shipped_conditions[] = self::meta_exists_sql( $shipped_key, ' AND m.meta_value = %s', array( '1' ) );
		}
		$stats['shipped'] = (int) $wpdb->get_var(
			self::count_andreani_orders_sql( ' AND ( ' . implode( ' OR ', $shipped_conditions ) . ' )' )
		);

		$created_sql = self::meta_exists_sql( '_order_andreani_created', ' AND m.meta_value = %s', array( '1' ) );

		$awaiting_count = 0;
		$ready_count    = 0;
		foreach ( Andreani_Client_Type::all() as $type ) {
			$type_count = (int) $wpdb->get_var(
				self::count_andreani_orders_sql(
					' AND ' . $created_sql
					. ' AND ' . self::meta_exists_sql( '_order_andreani_client_type', ' AND m.meta_value = %s', array( $type->id() ) )
					. ' AND NOT ' . self::meta_exists_sql( $type->shipped_meta_key() )
				)
			);
			if ( $type->has_payment_step() ) {
				$awaiting_count += $type_count;
			} else {
				$ready_count += $type_count;
			}
		}
		$stats['awaiting'] = $awaiting_count;
		$stats['ready']    = $ready_count;

		$stats['failed'] = (int) $wpdb->get_var(
			self::count_andreani_orders_sql(
				' AND ' . self::meta_exists_sql( '_andreani_last_error', " AND m.meta_value <> ''" )
				. ' AND NOT ' . self::meta_exists_sql( '_order_andreani_created', " AND m.meta_value NOT IN ( '', '0' )" )
			)
		);

		$pending = $stats['total'] - ( $stats['awaiting'] + $stats['ready'] + $stats['shipped'] + $stats['failed'] );
		$stats['pending'] = max( 0, $pending );

		set_transient( $cache_key, $stats, 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Invalidar cache de stats. Hookeable a meta updates.
	 *
	 * @param int    $meta_id   ID del meta (no usado).
	 * @param int    $object_id ID de la orden (no usado).
	 * @param string $meta_key  Clave del meta cambiado.
	 */
	public static function invalidate_stats_cache( $meta_id = 0, $object_id = 0, $meta_key = '' ) {
		static $relevant_keys = null;
		if ( null === $relevant_keys ) {
			$relevant_keys = array(
				'_order_andreani_created'     => true,
				'_order_andreani_client_type' => true,
				'_andreani_last_error'        => true,
			);
			foreach ( Andreani_Client_Type::all_shipped_meta_keys() as $shipped_key ) {
				$relevant_keys[ $shipped_key ] = true;
			}
		}

		if ( empty( $meta_key ) || isset( $relevant_keys[ $meta_key ] ) ) {
			delete_transient( 'andreani_shipment_stats' );
		}
	}

	/**
	 * Invalidar cache del detalle del envío.
	 *
	 * Inerte: el detalle ahora se renderiza eager-loaded en `single_row()` y no usa
	 * transient. La función queda registrada para mantener el hook en
	 * `Andreani_Plugin::includes()` y purgar transients legacy si todavía existen.
	 */
	public static function invalidate_detail_cache( $meta_id = 0, $object_id = 0, $meta_key = '' ) {
		if ( empty( $object_id ) ) {
			return;
		}
		delete_transient( 'andreani_detail_' . absint( $object_id ) );
	}


	public static function build_filter_meta_query( $client_type = '', $andreani_status = '' ) {
		$conditions = array();

		if ( ! empty( $client_type ) ) {
			$conditions[] = array(
				'key'   => '_order_andreani_client_type',
				'value' => $client_type,
			);
		}

		$tracking_map = array(
			'pending_entry' => 'Listo para enviar',
			'in_transit'    => 'En camino',
			'ready_pickup'  => 'Listo para retirar',
			'delivered'     => 'Entregado',
			'not_delivered' => 'No entregado',
		);

		if ( 'not_packaged' === $andreani_status || 'error' === $andreani_status ) {
			// Sin generar en Andreani (incluye los que fallaron).
			$conditions[] = array(
				'relation' => 'OR',
				array( 'key' => '_order_andreani_created', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_order_andreani_created', 'value' => '1', 'compare' => '!=' ),
			);
			if ( 'error' === $andreani_status ) {
				$conditions[] = array( 'key' => '_andreani_last_error', 'compare' => 'EXISTS' );
				$conditions[] = array( 'key' => '_andreani_last_error', 'value' => '', 'compare' => '!=' );
			}
		} elseif ( isset( $tracking_map[ $andreani_status ] ) ) {
			$conditions[] = array(
				'key'   => self::META_TRACKING_STATUS,
				'value' => $tracking_map[ $andreani_status ],
			);
		}

		return $conditions;
	}

	private function get_shipments_data( $args ) {
		$orders_args = array(
			'type'     => 'shop_order',
			'orderby'       => 'date',
			'order'         => 'DESC',
			'andreani_only' => true,
		);

		$meta_conditions = array();

		if ( ! empty( $args['search'] ) ) {
			$search = trim( $args['search'] );

			if ( preg_match( '/^#?(\d+)-/', $search, $matches ) ) {
				$orders_args['post__in'] = array( absint( $matches[1] ) );
			} elseif ( ctype_digit( $search ) && strlen( $search ) <= 8 ) {
				$orders_args['post__in'] = array( absint( $search ) );
			} elseif ( ctype_digit( $search ) && strlen( $search ) > 8 ) {
				$tracking_or = array( 'relation' => 'OR' );
				foreach ( Andreani_Client_Type::all() as $type ) {
					if ( $type->supports_label_pdf() ) {
						$tracking_or[] = array( 'key' => $type->tracking_meta_key(), 'value' => $search, 'compare' => 'LIKE' );
					}
					if ( $type->supports_manual_tracking() && $type->manual_tracking_meta_key() ) {
						$tracking_or[] = array( 'key' => $type->manual_tracking_meta_key(), 'value' => $search, 'compare' => 'LIKE' );
					}
				}
				$meta_conditions[] = $tracking_or;
			} else {
				$meta_conditions[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_billing_first_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_billing_last_name',
						'value'   => $search,
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_order_andreani_pedido_id',
						'value'   => $search,
						'compare' => 'LIKE',
					),
				);
			}
		}

		$client_type     = isset( $args['client_type'] ) ? $args['client_type'] : '';
		$andreani_status = isset( $args['andreani_status'] ) ? $args['andreani_status'] : '';

		$status_tokens = array_values( array_filter( array_map( 'trim', explode( ',', $andreani_status ) ) ) );

		$runtime_status_map = array(
			'not_packaged' => self::SHIPPING_STATUS_NOT_PACKAGED,
			'packaged'     => self::SHIPPING_STATUS_PACKAGED,
			'ready'        => self::SHIPPING_STATUS_READY,
			'in_transit'   => self::SHIPPING_STATUS_IN_TRANSIT,
			'delivered'    => self::SHIPPING_STATUS_DELIVERED,
		);

		$runtime_targets = array();
		foreach ( $status_tokens as $token ) {
			if ( isset( $runtime_status_map[ $token ] ) ) {
				$runtime_targets[] = $runtime_status_map[ $token ];
			}
		}
		$runtime_targets = array_values( array_unique( $runtime_targets ) );

		// 'errors' es flag superpuesto (no estado): filtra por presencia de _andreani_last_error.
		$filter_has_error = in_array( 'errors', $status_tokens, true );

		// Modo legacy del select dropdown: 1 valor en {error, shipped, success} y SIN
		// chip runtime → meta_query optimizado con paginado en DB.
		$legacy_status = ( empty( $runtime_targets ) && ! $filter_has_error && 1 === count( $status_tokens ) )
			? $status_tokens[0]
			: '';

		$filter_conditions = self::build_filter_meta_query( $client_type, $legacy_status );
		foreach ( $filter_conditions as $condition ) {
			$meta_conditions[] = $condition;
		}

		if ( 1 === count( $meta_conditions ) ) {
			$orders_args['meta_query'] = $meta_conditions[0];
		} elseif ( count( $meta_conditions ) > 1 ) {
			$orders_args['meta_query'] = array_merge(
				array( 'relation' => 'AND' ),
				$meta_conditions
			);
		}

		// Filtros de fecha (quick chips Hoy / Esta semana / Últimos 15 días). Formato
		// Y-m-d H:i:s (UTC ya normalizado en el JS). wc_get_orders acepta date_after / date_before.
		if ( ! empty( $args['date_from'] ) ) {
			$orders_args['date_after'] = $args['date_from'];
		}
		if ( ! empty( $args['date_to'] ) ) {
			$orders_args['date_before'] = $args['date_to'];
		}

		$db_sortable_fields = array(
			'date'     => 'date',
			'order_id' => 'ID',
		);

		$sort_field = isset( $args['orderby'] ) ? $args['orderby'] : 'date';
		$sort_order = isset( $args['order'] ) ? $args['order'] : 'DESC';
		$can_db_sort = isset( $db_sortable_fields[ $sort_field ] );

		$needs_runtime_filter = ! empty( $runtime_targets ) || $filter_has_error;

		if ( $can_db_sort && ! $needs_runtime_filter ) {
			$orders_args['orderby'] = $db_sortable_fields[ $sort_field ];
			$orders_args['order']   = $sort_order;

			$count_args           = $orders_args;
			$count_args['limit']  = -1;
			$count_args['return'] = 'ids';
			$total_ids            = wc_get_orders( $count_args );
			$total                = count( $total_ids );

			$orders_args['limit']  = $args['limit'];
			$orders_args['offset'] = $args['offset'];
			$orders = wc_get_orders( $orders_args );

			$items = array();
			foreach ( $orders as $order ) {
				$items[] = $this->build_item_from_order( $order );
			}
		} else {
			$orders_args['limit'] = -1;
			$orders = wc_get_orders( $orders_args );

			$items = array();
			foreach ( $orders as $order ) {
				$items[] = $this->build_item_from_order( $order );
			}

			if ( $needs_runtime_filter ) {
				$items = array_values( array_filter( $items, function ( $i ) use ( $runtime_targets, $filter_has_error ) {
					$status_match = ! empty( $runtime_targets ) && in_array( isset( $i['shipping_status'] ) ? $i['shipping_status'] : '', $runtime_targets, true );
					$error_match  = $filter_has_error && ! empty( $i['last_error'] );
					return $status_match || $error_match;
				} ) );
			}

			$items = $this->sort_items( $items, $sort_field, $sort_order );
			$total = count( $items );
			$items = array_slice( $items, $args['offset'], $args['limit'] );
		}

		$fallback_recent_count = 0;

		// Si el default "hoy" no trajo nada, mostramos los 15 envíos más recientes
		// para que la grilla no quede vacía en clientes con poco volumen diario.
		// Aplica a AMBOS paths (DB-sort y runtime-filter), por eso vive después
		// del branch.
		if ( 0 === $total && ! empty( $args['is_default_today'] ) ) {
			unset( $orders_args['date_after'], $orders_args['date_before'], $orders_args['offset'] );
			$orders_args['limit']   = $args['limit'];
			$orders_args['orderby'] = 'date';
			$orders_args['order']   = 'DESC';
			unset( $orders_args['return'] );

			$orders = wc_get_orders( $orders_args );

			$items = array();
			foreach ( $orders as $order ) {
				$items[] = $this->build_item_from_order( $order );
			}

			$total                 = count( $items );
			$fallback_recent_count = $total;
		}

		$api_failure = false;

		if ( class_exists( 'Andreani_Shipments_Hydrator' ) ) {
			$hydrator    = Andreani_Shipments_Hydrator::get_instance();
			$items       = $hydrator->hydrate( $items );
			$api_failure = $hydrator->last_run_had_api_failure;
		}

		return array(
			'items'                 => $items,
			'total'                 => $total,
			'fallback_recent_count' => $fallback_recent_count,
			'api_failure'           => $api_failure,
		);
	}

	private function sort_items( $items, $orderby, $order ) {
		if ( empty( $items ) || empty( $orderby ) ) {
			return $items;
		}

		$field_map = array(
			'order_id'    => 'order_id',
			'date'        => 'date_created',
			'status'      => 'andreani_status',
			'client_type' => 'client_type',
		);

		$field = isset( $field_map[ $orderby ] ) ? $field_map[ $orderby ] : 'date_created';

		usort( $items, function( $a, $b ) use ( $field, $order ) {
			$val_a = isset( $a[ $field ] ) ? $a[ $field ] : '';
			$val_b = isset( $b[ $field ] ) ? $b[ $field ] : '';

			if ( 'order_id' === $field ) {
				$result = (int) $val_a - (int) $val_b;
			} elseif ( 'date_created' === $field ) {
				$result = strcmp( (string) $val_a, (string) $val_b );
			} else {
				$result = strcmp( strtolower( (string) $val_a ), strtolower( (string) $val_b ) );
			}

			return 'DESC' === $order ? -$result : $result;
		} );

		return $items;
	}

	public static function get_order_shipment_data( $order ) {
		$order_id      = $order->get_id();
		$created       = (bool) $order->get_meta( '_order_andreani_created', true );
		$client_type   = $order->get_meta( '_order_andreani_client_type', true );
		$tracking        = $order->get_meta( '_order_andreani_tracking_number', true );
		$pedido_id       = $order->get_meta( '_order_andreani_pedido_id', true );
		$internal_number = (string) $order->get_meta( '_order_andreani_numero_interno', true );
		$delivery_mode   = $order->get_meta( '_order_andreani_delivery_mode', true );

		if ( empty( $client_type ) ) {
			$client_type = Andreani_Api_Manager::get_client_type();
		}

		$type_info       = Andreani_Client_Type::from_id( $client_type );
		$manual_tracking = $type_info && $type_info->manual_tracking_meta_key()
			? $order->get_meta( $type_info->manual_tracking_meta_key(), true )
			: '';

		if ( empty( $delivery_mode ) && $type_info ) {
			$shipping_method = Andreani_Api_Utils::get_order_shipping_method( $order );
			if ( ! empty( $shipping_method ) ) {
				$delivery_mode = Andreani_Api_Utils::get_delivery_mode_from_shipping_method( $shipping_method, $type_info->prefix() );
			}
		}

		$shipped          = $type_info ? (bool) $order->get_meta( $type_info->shipped_meta_key(), true ) : false;
		$display_tracking = $tracking;

		if ( $created && $shipped ) {
			$status = 'shipped';
		} elseif ( $created ) {
			$status = 'success';
		} else {
			$status = 'error';
		}

		$shipping_status = self::compute_shipping_status( $order, $created, $shipped );
		$payment_status  = self::compute_payment_status( $client_type, $tracking );

		return array(
			'order_id'         => $order_id,
			'order_number'     => $order->get_order_number(),
			'created'          => $created,
			'client_type'      => $client_type,
			'tracking'         => $tracking,
			'tracking_number'  => $tracking,
			'pedido_id'        => $pedido_id,
			'internal_number'  => $internal_number,
			'delivery_mode'    => $delivery_mode,
			'manual_tracking'  => $manual_tracking,
			'is_pyme'          => $type_info && $type_info->can( 'id_orden_display' ),
			'shipped'          => $shipped,
			'andreani_status'  => $status,
			'shipping_status'  => $shipping_status,
			'tracking_status'  => (string) $order->get_meta( self::META_TRACKING_STATUS, true ),
			'payment_status'   => $payment_status,
			'display_tracking' => $display_tracking,
			'last_error'       => ! $created ? (string) $order->get_meta( '_andreani_last_error', true ) : '',
			'date_created'     => $order->get_date_created() ? $order->get_date_created()->date( 'Y-m-d H:i:s' ) : '',
		);
	}

	/**
	 * Resuelve el estado del envío. Lee el meta `_order_andreani_shipping_status`
	 * cuando existe; si no, deriva del flag `created` + `shipped`.
	 *
	 * Modelo del negocio:
	 *  - "Empaquetar" = generar el envío en Andreani.
	 *  - Pyme creado (con pedidoId) = ya está empaquetado. Falta que el cliente
	 *    pague desde el portal Pymes para despachar.
	 *  - Corpo creado (con trackingNumber) = ya está empaquetado. Falta que el
	 *    merchant lo despache.
	 *  - "Listo" = el merchant cargó manualmente el tracking (Pyme) o marcó
	 *    enviado (Corpo).
	 */
	public static function compute_shipping_status( $order, $created, $shipped ) {
		$explicit = (string) $order->get_meta( self::META_SHIPPING_STATUS, true );
		if ( '' !== $explicit ) {
			return $explicit;
		}

		$tracking_status = (string) $order->get_meta( self::META_TRACKING_STATUS, true );
		if ( '' !== $tracking_status ) {
			return self::map_tracking_status( $tracking_status );
		}

		if ( $shipped ) {
			return self::SHIPPING_STATUS_READY;
		}
		if ( $created ) {
			return self::SHIPPING_STATUS_PACKAGED;
		}

		// Las órdenes con _andreani_last_error caen en "Por empaquetar" igual que
		// las nuevas; la presencia del error se señala con un tinte sutil en la fila
		// y el mensaje completo aparece al expandir el detalle (decisión UX: evitar
		// el rojo agresivo en la grilla principal).
		return self::SHIPPING_STATUS_NOT_PACKAGED;
	}

	/**
	 * Mapea el TrackingStatus logístico de Andreani (forward-only) al estado visual.
	 * "Listo para enviar" se muestra como "Pendiente de ingreso" (terminología Pyme).
	 */
	public static function map_tracking_status( $tracking_status ) {
		switch ( strtolower( trim( (string) $tracking_status ) ) ) {
			case 'listo para enviar':  return self::SHIPPING_STATUS_PENDING_ENTRY;
			case 'en camino':          return self::SHIPPING_STATUS_IN_TRANSIT;
			case 'listo para retirar': return self::SHIPPING_STATUS_READY_PICKUP;
			case 'entregado':          return self::SHIPPING_STATUS_DELIVERED;
			case 'no entregado':       return self::SHIPPING_STATUS_NOT_DELIVERED;
			default:                   return self::SHIPPING_STATUS_PENDING_ENTRY;
		}
	}

	/**
	 * Estado de pago — solo aplica a Pyme. Pasa a 'paid' cuando el envío tiene número
	 * de seguimiento real (el alta vía el pipeline ya implica pago/ingreso). Devuelve '' para Corpo.
	 */
	public static function compute_payment_status( $client_type, $tracking_number = '' ) {
		$type_info = Andreani_Client_Type::from_id( $client_type );
		if ( ! $type_info || ! $type_info->has_payment_step() ) {
			return '';
		}
		return ! empty( $tracking_number )
			? self::PAYMENT_STATUS_PAID
			: self::PAYMENT_STATUS_PENDING;
	}

	/**
	 * Devuelve la configuración de display (label / hint / class / icono) para un
	 * estado de envío. El `is_pyme` solo cambia el hint de NOT_PACKAGED (Pyme
	 * necesita pagar antes de empaquetarse, Corpo no).
	 */
	public static function get_shipping_status_config( $status, $has_payment_step = false ) {
		$icon_package_open  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22V12"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M20.5 7.27 12 12 3.5 7.27"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/></svg>';
		$icon_package       = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
		$icon_truck         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
		$icon_check         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
		$icon_check_circle  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>';
		$icon_alert         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
		$icon_clock         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
		$icon_store         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 7 2-4h16l2 4"/><path d="M4 7v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7"/><path d="M2 7h20"/><path d="M9 21V12h6v9"/></svg>';
		$icon_x_circle      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

		$configs = array(
			self::SHIPPING_STATUS_NOT_PACKAGED => array(
				'label' => __( 'Por Empaquetar', 'andreani-shipping' ),
				'hint'  => __( 'El envío todavía no se generó en Andreani. Reintentá desde la columna de acciones.', 'andreani-shipping' ),
				'class' => 'andr-status--not-packaged',
				'icon'  => $icon_package_open,
			),
			self::SHIPPING_STATUS_PACKAGED => array(
				'label' => __( 'Empaquetado', 'andreani-shipping' ),
				'hint'  => $has_payment_step
					? __( 'Generado en Andreani. Esperando que el cliente pague el envío en el portal de Andreani Pymes.', 'andreani-shipping' )
					: __( 'Generado en Andreani. Listo para despachar.', 'andreani-shipping' ),
				'class' => 'andr-status--packaged',
				'icon'  => $icon_package,
			),
			self::SHIPPING_STATUS_PENDING_ENTRY => array(
				'label' => __( 'Pendiente de ingreso', 'andreani-shipping' ),
				'hint'  => __( 'El envío se generó y está pendiente de ingreso a la red de Andreani.', 'andreani-shipping' ),
				'class' => 'andr-status--pending-entry',
				'icon'  => $icon_clock,
			),
			self::SHIPPING_STATUS_READY => array(
				'label' => __( 'Listo', 'andreani-shipping' ),
				'hint'  => $has_payment_step
					? __( 'El cliente pagó el envío y está listo para despacharse.', 'andreani-shipping' )
					: __( 'Marcado como enviado. Listo para que Andreani lo retire.', 'andreani-shipping' ),
				'class' => 'andr-status--ready',
				'icon'  => $icon_check,
			),
			self::SHIPPING_STATUS_IN_TRANSIT => array(
				'label' => __( 'En camino', 'andreani-shipping' ),
				'hint'  => __( 'Despachado y en camino al destino.', 'andreani-shipping' ),
				'class' => 'andr-status--in-transit',
				'icon'  => $icon_truck,
			),
			self::SHIPPING_STATUS_READY_PICKUP => array(
				'label' => __( 'Listo para retirar', 'andreani-shipping' ),
				'hint'  => __( 'Disponible para retirar en la sucursal de destino.', 'andreani-shipping' ),
				'class' => 'andr-status--ready-pickup',
				'icon'  => $icon_store,
			),
			self::SHIPPING_STATUS_DELIVERED => array(
				'label' => __( 'Entregado', 'andreani-shipping' ),
				'hint'  => __( 'Entregado al destinatario.', 'andreani-shipping' ),
				'class' => 'andr-status--delivered',
				'icon'  => $icon_check_circle,
			),
			self::SHIPPING_STATUS_NOT_DELIVERED => array(
				'label' => __( 'No entregado', 'andreani-shipping' ),
				'hint'  => __( 'No se pudo entregar. Andreani puede reintentar la visita.', 'andreani-shipping' ),
				'class' => 'andr-status--not-delivered',
				'icon'  => $icon_x_circle,
			),
			self::SHIPPING_STATUS_ERROR => array(
				'label' => __( 'Error', 'andreani-shipping' ),
				'hint'  => __( 'Error al crear el envío. Podés reintentar.', 'andreani-shipping' ),
				'class' => 'andr-status--error',
				'icon'  => $icon_alert,
			),
		);

		return isset( $configs[ $status ] ) ? $configs[ $status ] : $configs[ self::SHIPPING_STATUS_ERROR ];
	}

	public static function get_payment_status_config( $status ) {
		$icon_clock  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
		$icon_wallet = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12V7H5a2 2 0 0 1 0-4h14v4"/><path d="M3 5v14a2 2 0 0 0 2 2h16v-5"/><path d="M18 12a2 2 0 0 0 0 4h4v-4Z"/></svg>';

		$configs = array(
			self::PAYMENT_STATUS_PENDING => array(
				'label' => __( 'Pendiente', 'andreani-shipping' ),
				'hint'  => __( 'Esperando confirmación de pago en Andreani Pymes.', 'andreani-shipping' ),
				'class' => 'andr-badge--warning',
				'icon'  => $icon_clock,
			),
			self::PAYMENT_STATUS_PAID => array(
				'label' => __( 'Pagado', 'andreani-shipping' ),
				'hint'  => __( 'Pago confirmado.', 'andreani-shipping' ),
				'class' => 'andr-badge--success',
				'icon'  => $icon_wallet,
			),
		);

		return isset( $configs[ $status ] ) ? $configs[ $status ] : $configs[ self::PAYMENT_STATUS_PENDING ];
	}

	private function build_item_from_order( $order ) {
		$data = self::get_order_shipment_data( $order );

		$products = array();
		foreach ( $order->get_items() as $item ) {
			$products[] = array(
				'name' => $item->get_name(),
				'qty'  => $item->get_quantity(),
			);
		}

		return array_merge( $data, array(
			'customer_name'       => $order->get_formatted_billing_full_name(),
			'customer_email'      => $order->get_billing_email(),
			'dni'                 => $order->get_meta( '_billing_dni', true ),
			'shipping_address_1'  => $order->get_shipping_address_1(),
			'shipping_address_2'  => $order->get_shipping_address_2(),
			'shipping_city'       => $order->get_shipping_city(),
			'shipping_state'      => $order->get_shipping_state(),
			'shipping_postcode'   => $order->get_shipping_postcode(),
			'products'            => $products,
			'andreani_pedido_id'  => $data['is_pyme'] ? $data['pedido_id'] : '',
			'can_retry'           => ! $data['created'],
			'last_error'          => $order->get_meta( '_andreani_last_error', true ),
			'last_error_body'     => $order->get_meta( '_andreani_last_error_body', true ),
		) );
	}

	private function get_order_edit_url( $order_id ) {
		if ( class_exists( 'Automattic\WooCommerce\Utilities\OrderUtil' )
			&& Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
			return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $order_id );
		}
		return admin_url( 'post.php?post=' . $order_id . '&action=edit' );
	}

	public function get_row_html( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$item = $this->build_item_from_order( $order );

		ob_start();
		$this->single_row( $item );
		return ob_get_clean();
	}
}
