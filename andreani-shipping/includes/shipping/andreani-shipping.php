<?php
defined( 'ABSPATH' ) || exit;

class Andreani_Shipping extends WC_Shipping_Method {
	public $init_form_fields = array();
	public $instance_form_fields = array();
	public $tipo_cliente;
	public $cp_origen;
	public $mostrar_sin_decimales;
	public $hash_andreani;
	private $resolved_client_type = null;

	public function __construct( $instance_id = 0 ) {
		$this->id = ANDREANI_SHIPPING_METHOD_ID;
		$this->instance_id = absint( $instance_id );
		$this->method_title = __( 'Andreani Envios', 'andreani-shipping' );
		$this->method_description = __( 'Obtiene las tasas de envio de andreani.', 'andreani-shipping' );
		$this->title = $this->method_title;
		$this->supports = array(
			'shipping-zones',
			'instance-settings',
		);

		$this->init();

		add_action('woocommerce_after_shipping_calculator', array($this, 'process_after_shipping_calculator'), 10);
		add_action('woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	public function init() {
		$form_fields = include ANDREANI_PLUGIN_DIR . 'includes/shipping/andreani-form-fields-shipping-config.php';
		$this->init_form_fields = is_array( $form_fields ) ? $form_fields : array();
		$this->init_settings();
		$this->instance_form_fields = $this->init_form_fields;
		$this->tipo_cliente = $this->get_option( 'tipo_cliente', 'corporativo' );
		$cp_origen_value = $this->get_option( 'cp_origen', '' );
		$this->cp_origen = apply_filters( 'andreani_origin_postal_code', str_replace( ' ', '', strtoupper( $cp_origen_value ) ) );
		$this->mostrar_sin_decimales = $this->get_option( 'mostrar_sin_decimales', 'no' ) === 'yes';
		$this->hash_andreani = $this->get_option( 'hash_andreani', '' );
	}

	public function generate_hidden_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$value = $this->get_option( $key );

		return '<input type="hidden" name="' . esc_attr( $field_key ) . '" id="' . esc_attr( $field_key ) . '" value="' . esc_attr( $value ) . '" />';
	}

	public function generate_cotizador_subfield_html( $key, $data ) {
		return '';
	}

	public function validate_cotizador_subfield_field( $key, $value ) {
		return sanitize_text_field( $value );
	}

	public function generate_cotizador_config_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$tema_field_key = $this->get_field_key( 'cotizador_tema' );
		$modo_field_key = $this->get_field_key( 'cotizador_modo' );
		$posicion_field_key = $this->get_field_key( 'cotizador_posicion' );

		$current_value   = $this->get_option( $key, 'no' );
		$is_enabled      = 'yes' === $current_value;
		$tema_actual     = $this->get_option( 'cotizador_tema', 'light' );
		$modo_actual     = $this->get_option( 'cotizador_modo', 'auto' );
		$posicion_actual = $this->get_option( 'cotizador_posicion', 'after_add_to_cart' );

		$info_svg    = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';
		$chevron_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>';

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php esc_html_e( 'Cotizador en producto', 'andreani-shipping' ); ?></label>
				<?php echo wc_help_tip( __( 'Muestra un widget para cotizar el costo de envío en cada página de producto.', 'andreani-shipping' ) ); ?>
			</th>
			<td class="forminp">
				<select name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" class="andreani-cotizador-enabled">
					<option value="no" <?php selected( $current_value, 'no' ); ?>><?php esc_html_e( 'Desactivado', 'andreani-shipping' ); ?></option>
					<option value="yes" <?php selected( $current_value, 'yes' ); ?>><?php esc_html_e( 'Activado', 'andreani-shipping' ); ?></option>
				</select>
			</td>
		</tr>
		<?php
		$hidden_attr = $is_enabled ? '' : ' data-hidden="true"';
		?>
		<tr class="andreani-info-box-wrapper andreani-info-box-wrapper--attached" data-show-when-field="cotizador_producto" data-show-when-value="yes"<?php echo esc_attr( $hidden_attr ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<th scope="row" class="andreani-info-box-wrapper__spacer"></th>
			<td class="forminp">
				<div class="andreani-info-box" data-open="false" data-initial-open="false">
					<button type="button" class="andreani-info-box__header" aria-expanded="false">
					<span class="andreani-info-box__icon"><?php echo $info_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<span class="andreani-info-box__title"><?php esc_html_e( 'Configuración del cotizador', 'andreani-shipping' ); ?></span>
					<span class="andreani-info-box__chevron"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
				</button>
				<div class="andreani-info-box__body">
					<div class="andreani-info-box__fields">
						<div class="andreani-info-box__field">
							<label for="<?php echo esc_attr( $modo_field_key ); ?>"><?php esc_html_e( 'Modo de inserción', 'andreani-shipping' ); ?></label>
							<select name="<?php echo esc_attr( $modo_field_key ); ?>" id="<?php echo esc_attr( $modo_field_key ); ?>" class="andreani-cotizador-modo">
								<option value="auto" <?php selected( $modo_actual, 'auto' ); ?>><?php esc_html_e( 'Automático', 'andreani-shipping' ); ?></option>
								<option value="shortcode" <?php selected( $modo_actual, 'shortcode' ); ?>><?php esc_html_e( 'Solo shortcode', 'andreani-shipping' ); ?></option>
							</select>
						</div>

						<div class="andreani-info-box__field">
							<label for="<?php echo esc_attr( $tema_field_key ); ?>"><?php esc_html_e( 'Tema', 'andreani-shipping' ); ?></label>
							<select name="<?php echo esc_attr( $tema_field_key ); ?>" id="<?php echo esc_attr( $tema_field_key ); ?>" class="andreani-cotizador-tema">
								<option value="auto" <?php selected( $tema_actual, 'auto' ); ?>><?php esc_html_e( 'Automático', 'andreani-shipping' ); ?></option>
								<option value="light" <?php selected( $tema_actual, 'light' ); ?>><?php esc_html_e( 'Claro', 'andreani-shipping' ); ?></option>
								<option value="dark" <?php selected( $tema_actual, 'dark' ); ?>><?php esc_html_e( 'Oscuro', 'andreani-shipping' ); ?></option>
							</select>
						</div>

						<div class="andreani-info-box__field andreani-cotizador-config__posicion <?php echo 'auto' !== $modo_actual ? 'andreani-hidden' : ''; ?>">
							<label for="<?php echo esc_attr( $posicion_field_key ); ?>"><?php esc_html_e( 'Posición', 'andreani-shipping' ); ?></label>
							<select name="<?php echo esc_attr( $posicion_field_key ); ?>" id="<?php echo esc_attr( $posicion_field_key ); ?>" class="andreani-cotizador-posicion">
								<option value="after_add_to_cart" <?php selected( $posicion_actual, 'after_add_to_cart' ); ?>><?php esc_html_e( 'Después del botón agregar al carrito', 'andreani-shipping' ); ?></option>
								<option value="before_add_to_cart" <?php selected( $posicion_actual, 'before_add_to_cart' ); ?>><?php esc_html_e( 'Antes del botón agregar al carrito', 'andreani-shipping' ); ?></option>
								<option value="after_price" <?php selected( $posicion_actual, 'after_price' ); ?>><?php esc_html_e( 'Después del precio', 'andreani-shipping' ); ?></option>
								<option value="floating" <?php selected( $posicion_actual, 'floating' ); ?>><?php esc_html_e( 'Flotante (esquina inferior derecha)', 'andreani-shipping' ); ?></option>
							</select>
						</div>
					</div>

					<div class="andreani-info-box__divider"></div>

					<p class="andreani-shortcodes-intro"><?php esc_html_e( 'Si elegís el modo "Solo shortcode" insertá esto en cualquier página. Hacé click para copiarlo.', 'andreani-shipping' ); ?></p>

					<div class="andreani-shortcodes-list">
						<div class="andreani-code-card andreani-copy-click" role="button" tabindex="0" data-copy-text="[andreani_cotizador]" aria-label="<?php esc_attr_e( 'Copiar shortcode [andreani_cotizador]', 'andreani-shipping' ); ?>">
							<div class="andreani-code-card__main">
								<code class="andreani-code-card__code">[andreani_cotizador]</code>
								<span class="andreani-code-card__desc"><?php esc_html_e( 'Widget de cotización en cualquier página', 'andreani-shipping' ); ?></span>
							</div>
							<div class="andreani-code-card__copy">
								<span class="andreani-code-card__copy-icon andreani-code-card__copy-icon--default" title="<?php esc_attr_e( 'Copiar', 'andreani-shipping' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
								</span>
								<span class="andreani-code-card__copy-label andreani-code-card__copy-label--default"><?php esc_html_e( 'Copiar', 'andreani-shipping' ); ?></span>
								<span class="andreani-code-card__copy-label andreani-code-card__copy-label--success"><?php esc_html_e( 'Copiado', 'andreani-shipping' ); ?></span>
							</div>
						</div>
					</div>

					<details class="andreani-info-box__advanced andreani-code-reference">
						<summary><?php esc_html_e( 'Personalización con CSS', 'andreani-shipping' ); ?></summary>
						<div class="andreani-code-reference__inner">
							<div class="andreani-code-reference__table" role="table">
								<div class="andreani-code-reference__head" role="row">
									<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="columnheader"><?php esc_html_e( 'Selector', 'andreani-shipping' ); ?></span>
									<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="columnheader"><?php esc_html_e( 'Descripción', 'andreani-shipping' ); ?></span>
								</div>
								<div class="andreani-code-reference__row" role="row">
									<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="cell"><code>.andreani-calc-widget</code></span>
									<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="cell"><?php esc_html_e( 'Contenedor del widget', 'andreani-shipping' ); ?></span>
								</div>
								<div class="andreani-code-reference__row" role="row">
									<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="cell"><code>.andreani-calc-postcode</code></span>
									<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="cell"><?php esc_html_e( 'Input de código postal', 'andreani-shipping' ); ?></span>
								</div>
								<div class="andreani-code-reference__row" role="row">
									<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="cell"><code>.andreani-calc-results</code></span>
									<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="cell"><?php esc_html_e( 'Bloque de resultados', 'andreani-shipping' ); ?></span>
								</div>
								<div class="andreani-code-reference__row" role="row">
									<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="cell"><code>.andreani-calc-rate</code></span>
									<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="cell"><?php esc_html_e( 'Fila individual de tarifa', 'andreani-shipping' ); ?></span>
								</div>
							</div>
							<p class="andreani-code-reference__footer">
								<span class="andreani-code-reference__footer-icon" aria-hidden="true">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>
								</span>
								<?php echo wp_kses( sprintf(
									/* translators: %s: ruta de personalización en WP admin */
									__( 'Editá desde %s. Los colores y tipografía se heredan de tu tema.', 'andreani-shipping' ),
									'<strong>' . esc_html__( 'Apariencia → Personalizar → CSS adicional', 'andreani-shipping' ) . '</strong>'
								), array( 'strong' => array() ) ); ?>
							</p>
						</div>
					</details>
				</div>
			</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	public function generate_andreani_info_box_html( $key, $data ) {
		$title            = isset( $data['title'] ) ? $data['title'] : '';
		$content          = isset( $data['content'] ) ? $data['content'] : '';
		$show_when_field  = isset( $data['show_when_field'] ) ? $data['show_when_field'] : '';
		$show_when_value  = isset( $data['show_when_value'] ) ? $data['show_when_value'] : '';
		$show_when_cb     = isset( $data['show_when_checkbox'] ) ? $data['show_when_checkbox'] : '';
		$default_open     = ! empty( $data['default_open'] );
		$icon_key         = isset( $data['icon'] ) ? $data['icon'] : 'info';
		$attached         = ! empty( $data['attached'] );

		$icons = array(
			'info'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>',
			'code'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4zm5.2 0l4.6-4.6-4.6-4.6L16 6l6 6-6 6-1.4-1.4z"/></svg>',
			'bulb'    => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7z"/></svg>',
			'warning' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>',
		);
		$chevron_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>';

		$icon_svg      = isset( $icons[ $icon_key ] ) ? $icons[ $icon_key ] : $icons['info'];
		$open_attr     = $default_open ? 'true' : 'false';
		$aria_expanded = $default_open ? 'true' : 'false';

		$row_data_attrs = '';
		if ( $show_when_field ) {
			$row_data_attrs .= ' data-show-when-field="' . esc_attr( $show_when_field ) . '"';
			$row_data_attrs .= ' data-show-when-value="' . esc_attr( $show_when_value ) . '"';
		}
		if ( $show_when_cb ) {
			$row_data_attrs .= ' data-show-when-checkbox="' . esc_attr( $show_when_cb ) . '"';
		}

		$row_hidden = '';
		if ( $show_when_field ) {
			$row_hidden = ' data-hidden="true"';
		} elseif ( $show_when_cb ) {
			$cotizador_activo = $this->get_option( 'cotizador_producto', 'no' ) === 'yes';
			$row_hidden = $cotizador_activo ? '' : ' data-hidden="true"';
		}

		$content_html = $this->kses_info_box_content( $content );

		ob_start();
		?>
		<tr class="andreani-info-box-wrapper<?php echo $attached ? ' andreani-info-box-wrapper--attached' : ''; ?>"<?php echo $row_data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $row_hidden; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<th scope="row" class="andreani-info-box-wrapper__spacer"></th>
			<td class="forminp">
				<div class="andreani-info-box" data-open="<?php echo esc_attr( $open_attr ); ?>" data-initial-open="<?php echo esc_attr( $open_attr ); ?>">
					<button type="button" class="andreani-info-box__header" aria-expanded="<?php echo esc_attr( $aria_expanded ); ?>">
						<span class="andreani-info-box__icon"><?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="andreani-info-box__title"><?php echo esc_html( $title ); ?></span>
						<span class="andreani-info-box__chevron"><?php echo $chevron_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					</button>
					<div class="andreani-info-box__body">
						<?php echo $content_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitized by kses_info_box_content() above. ?>
					</div>
				</div>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	protected function kses_info_box_content( $content ) {
		$base = wp_kses_allowed_html( 'post' );

		$global_attrs = array(
			'class'         => true,
			'id'            => true,
			'style'         => true,
			'title'         => true,
			'role'          => true,
			'tabindex'      => true,
			'aria-hidden'   => true,
			'aria-label'    => true,
			'aria-expanded' => true,
			'data-copy-text' => true,
			'data-open'     => true,
		);

		$base['div']     = isset( $base['div'] ) ? array_merge( $base['div'], $global_attrs ) : $global_attrs;
		$base['span']    = isset( $base['span'] ) ? array_merge( $base['span'], $global_attrs ) : $global_attrs;
		$base['p']       = isset( $base['p'] ) ? array_merge( $base['p'], $global_attrs ) : $global_attrs;
		$base['code']    = isset( $base['code'] ) ? array_merge( $base['code'], $global_attrs ) : $global_attrs;
		$base['strong']  = isset( $base['strong'] ) ? array_merge( $base['strong'], $global_attrs ) : $global_attrs;
		$base['em']      = isset( $base['em'] ) ? array_merge( $base['em'], $global_attrs ) : $global_attrs;
		$base['ul']      = isset( $base['ul'] ) ? array_merge( $base['ul'], $global_attrs ) : $global_attrs;
		$base['li']      = isset( $base['li'] ) ? array_merge( $base['li'], $global_attrs ) : $global_attrs;
		$base['details'] = array_merge( $global_attrs, array( 'open' => true ) );
		$base['summary'] = $global_attrs;

		$svg_attrs = array_merge(
			$global_attrs,
			array(
				'xmlns'              => true,
				'viewbox'            => true,
				'fill'               => true,
				'stroke'             => true,
				'stroke-width'       => true,
				'stroke-linecap'     => true,
				'stroke-linejoin'    => true,
				'width'              => true,
				'height'             => true,
				'focusable'          => true,
			)
		);
		$base['svg']      = $svg_attrs;
		$base['path']     = array( 'd' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true );
		$base['circle']   = array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true );
		$base['rect']     = array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true );
		$base['line']     = array( 'x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true );
		$base['polyline'] = array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true );
		$base['polygon']  = array( 'points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true );

		return wp_kses( $content, $base );
	}

	public function validate_andreani_info_box_field( $key, $value ) {
		return $value;
	}

	public function admin_options() {
		$ctx = $this->build_admin_options_context();

		$this->render_products_warning();
		?>
		<div class="andreani-settings-wrapper">
			<?php $this->render_settings_loader(); ?>
			<?php $this->render_settings_header( $ctx['logo_url'] ); ?>

			<table class="form-table andreani-settings-hidden-fields" aria-hidden="true">
				<?php $this->generate_settings_html( $ctx['hidden_fields'] ); ?>
			</table>

			<div class="andr-tabs" data-tabs="andreani-settings">
				<?php $this->render_tabs_nav(); ?>
				<?php $this->render_panel_cuenta( $ctx ); ?>
				<?php $this->render_panel_origen( $ctx ); ?>
				<?php $this->render_panel_modos( $ctx ); ?>
				<?php $this->render_panel_checkout( $ctx['checkout_fields'], $ctx['cotizador_fields'] ); ?>
				<?php $this->render_panel_avanzado( $ctx['avanzado_fields'] ); ?>
			</div>

			<?php $this->render_settings_footer(); ?>
		</div>
		<?php
	}

	private function build_admin_options_context() {
		$tipo_cliente  = $this->get_option( 'tipo_cliente', '' );
		$hash_andreani = $this->get_option( 'hash_andreani', '' );
		$tipo_label    = '';
		$tipo_class    = '';
		$cliente_email = '';

		$info_cliente = array();

		$active_type_info = ! empty( $hash_andreani ) ? Andreani_Client_Type::from_id( $tipo_cliente ) : null;
		if ( $active_type_info ) {
			$tipo_label    = $active_type_info->label();
			$tipo_class    = $active_type_info->badge_css_class();
			$info_cliente  = get_option( $active_type_info->info_option_name(), array() );
			$info_cliente  = is_array( $info_cliente ) ? $info_cliente : array();
			$cliente_email = isset( $info_cliente['email'] ) ? $info_cliente['email'] : '';
		}

		$config_por_modo_json = $this->get_option( 'config_por_modo', '{}' );
		$config_por_modo      = json_decode( $config_por_modo_json, true ) ?: array();

		$all_fields = $this->get_instance_form_fields();

		return array(
			'logo_url'         => ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani.png',
			'tipo_cliente'     => $tipo_cliente,
			'hash_andreani'    => $hash_andreani,
			'tipo_label'       => $tipo_label,
			'tipo_class'       => $tipo_class,
			'cliente_email'    => $cliente_email,
			'info_cliente'     => $info_cliente,
			'origen'           => Andreani_Origen::get(),
			'cp_origen'        => $this->get_option( 'cp_origen', '' ),
			'config_por_modo'  => $config_por_modo,
			'hidden_fields'    => array_intersect_key( $all_fields, array_flip( array( 'tipo_cliente', 'config_por_modo' ) ) ),
			'credencial_fields' => array_intersect_key( $all_fields, array_flip( array( 'hash_andreani' ) ) ),
			'checkout_fields'  => array_intersect_key( $all_fields, array_flip( array( 'checkout_modo', 'checkout_shortcodes_info', 'checkout_force_enqueue' ) ) ),
			'cotizador_fields' => array_intersect_key( $all_fields, array_flip( array( 'cotizador_producto', 'cotizador_tema', 'cotizador_modo', 'cotizador_posicion' ) ) ),
			'avanzado_fields'  => array_intersect_key( $all_fields, array_flip( array( 'mostrar_sin_decimales', 'modo_debug' ) ) ),
		);
	}

	private function render_settings_loader() {
		?>
		<div class="andreani-settings-loader" aria-hidden="true">
			<div class="andreani-settings-loader__spinner">
				<svg class="andreani-settings-loader__logo andreani-settings-loader__logo--bg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341">
					<g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor">
						<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
					</g>
				</svg>
				<svg class="andreani-settings-loader__logo andreani-settings-loader__logo--fill" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341">
					<g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor">
						<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
					</g>
				</svg>
			</div>
		</div>
		<?php
	}

	private function render_settings_header( $logo_url ) {
		?>
		<div class="andreani-settings-header">
			<h2 class="andreani-settings-header__title"><?php esc_html_e( 'Configuración de envíos', 'andreani-shipping' ); ?></h2>
			<img class="andreani-settings-header__logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Andreani" />
		</div>
		<?php
	}

	private function render_tabs_nav() {
		?>
		<nav class="andr-tabs__list" role="tablist">
			<button type="button" class="andr-tabs__item andr-tabs__item--active" data-tab="cuenta" role="tab" aria-controls="andr-panel-cuenta" aria-selected="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/></svg>
				<span><?php esc_html_e( 'Mi cuenta', 'andreani-shipping' ); ?></span>
			</button>
			<button type="button" class="andr-tabs__item" data-tab="origen" role="tab" aria-controls="andr-panel-origen" aria-selected="false">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-6h6v6"/></svg>
				<span><?php esc_html_e( 'Origen', 'andreani-shipping' ); ?></span>
			</button>
			<button type="button" class="andr-tabs__item" data-tab="modos" role="tab" aria-controls="andr-panel-modos" aria-selected="false">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9 4-9-4 9-4 9 4z"/><path d="M3 8v8l9 4 9-4V8"/></svg>
				<span><?php esc_html_e( 'Servicios', 'andreani-shipping' ); ?></span>
			</button>
			<button type="button" class="andr-tabs__item" data-tab="checkout" role="tab" aria-controls="andr-panel-checkout" aria-selected="false">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
				<span><?php esc_html_e( 'Checkout', 'andreani-shipping' ); ?></span>
			</button>
			<button type="button" class="andr-tabs__item" data-tab="avanzado" role="tab" aria-controls="andr-panel-avanzado" aria-selected="false">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-2 2 2 2 0 01-2-2v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83 0 2 2 0 010-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 01-2-2 2 2 0 012-2h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 010-2.83 2 2 0 012.83 0l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 012-2 2 2 0 012 2v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 0 2 2 0 010 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 012 2 2 2 0 01-2 2h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
				<span><?php esc_html_e( 'Avanzado', 'andreani-shipping' ); ?></span>
			</button>
		</nav>
		<?php
	}

	private function render_panel_cuenta( $ctx ) {
		?>
		<div class="andr-tabs__panel andr-tabs__panel--active" data-panel="cuenta" role="tabpanel" id="andr-panel-cuenta">
			<?php
			if ( ! empty( $ctx['hash_andreani'] ) ) :
				$identidad = $this->split_identidad(
					$this->build_identidad_rows( $ctx ),
					'corporativo' === $ctx['tipo_cliente']
				);

				$apoyo = $identidad['apoyo'];
				if ( ! empty( $ctx['cliente_email'] ) ) {
					array_unshift( $apoyo, $ctx['cliente_email'] );
				}
				?>
				<div class="andreani-cliente-summary andreani-cliente-info" role="status">
					<div class="andreani-cliente-summary__check" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
					</div>
					<div class="andreani-cliente-summary__content">
						<span class="andreani-cliente-summary__label"><?php esc_html_e( 'Titular de la cuenta', 'andreani-shipping' ); ?></span>
						<?php if ( '' !== $identidad['titular'] ) : ?>
							<span class="andreani-cliente-summary__titular"><?php echo esc_html( $identidad['titular'] ); ?></span>
						<?php endif; ?>
						<?php if ( ! empty( $apoyo ) ) : ?>
							<span class="andreani-cliente-summary__apoyo"><?php echo esc_html( implode( ' · ', $apoyo ) ); ?></span>
						<?php endif; ?>
					</div>
					<?php if ( ! empty( $ctx['tipo_label'] ) ) : ?>
						<span class="andr-badge <?php echo esc_attr( $ctx['tipo_class'] ); ?>"><?php echo esc_html( $ctx['tipo_label'] ); ?></span>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<?php $this->render_bloque_credencial( $ctx ); ?>
		</div>
		<?php
	}

	private function render_panel_origen( $ctx ) {
		// El upgrader no corre en instalaciones nuevas (run_pending_upgrades corta con from_version '0'),
		// asi que sin este sembrado perezoso los campos de origen quedarian vacios para siempre.
		if ( Andreani_Origen::seed_from_woocommerce() ) {
			$ctx['origen'] = Andreani_Origen::get();
		}
		?>
		<div class="andr-tabs__panel" data-panel="origen" role="tabpanel" id="andr-panel-origen">
			<?php if ( empty( $ctx['hash_andreani'] ) ) : ?>
				<div class="andreani-empty-state">
					<div class="andreani-empty-state__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 56V24L32 8l24 16v32"/><path d="M24 56V38h16v18"/></svg>
					</div>
					<h3 class="andreani-empty-state__title"><?php esc_html_e( 'Configurá tu credencial primero', 'andreani-shipping' ); ?></h3>
					<p class="andreani-empty-state__desc"><?php echo wp_kses( __( 'Para elegir desde dónde despachás, primero ingresá tu Credencial ID en la pestaña <strong>Cuenta</strong>.', 'andreani-shipping' ), array( 'strong' => array() ) ); ?></p>
					<button type="button" class="andr-btn andr-btn--primary andr-btn--sm andreani-empty-state__cta" data-goto-tab="cuenta"><?php esc_html_e( 'Ir a Cuenta', 'andreani-shipping' ); ?></button>
				</div>
			<?php else :
				$this->render_bloque_direccion_origen( $ctx );
				$this->render_bloque_sucursal_origen( $ctx );
			endif; ?>
		</div>
		<?php
	}

	private function render_bloque_impresion() {
		?>
		<div class="andreani-section">
			<h3 class="andreani-section-heading andreani-section-heading--with-icon">
				<span class="andreani-section-heading__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
				</span>
				<span class="andreani-section-heading__title"><?php esc_html_e( 'Impresión de etiquetas', 'andreani-shipping' ); ?></span>
			</h3>
			<p class="andreani-section-description"><?php esc_html_e( 'Elegí el formato en el que se imprimen las etiquetas de tus envíos. Por defecto es A4 con 1 etiqueta por hoja.', 'andreani-shipping' ); ?></p>
			<p>
				<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-print-settings-trigger">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
					<?php esc_html_e( 'Configuración de impresión', 'andreani-shipping' ); ?>
				</button>
			</p>
		</div>
		<?php
	}

	private function render_bloque_credencial( $ctx ) {
		$tiene_credencial = ! empty( $ctx['hash_andreani'] );
		?>
		<div class="andreani-section">
			<h3 class="andreani-section-heading andreani-section-heading--with-icon">
				<span class="andreani-section-heading__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
				</span>
				<span class="andreani-section-heading__title"><?php esc_html_e( 'Credencial e identidad', 'andreani-shipping' ); ?></span>
			</h3>
			<p class="andreani-section-description"><?php esc_html_e( 'La credencial con la que tu tienda se conecta a Andreani.', 'andreani-shipping' ); ?></p>

			<table class="form-table andreani-credencial-fields">
				<?php $this->generate_settings_html( $ctx['credencial_fields'] ); ?>
			</table>

			<?php
			$type_info = $tiene_credencial ? Andreani_Client_Type::from_id( $ctx['tipo_cliente'] ) : null;
			if ( $type_info && $type_info->supports_contract_refresh() ) :
				?>
				<div class="andreani-cuenta-card__acciones andreani-cliente-info">
					<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-refresh-contratos" data-nonce="<?php echo esc_attr( wp_create_nonce( 'andreani_refresh_contratos' ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
						<?php esc_html_e( 'Actualizar contratos', 'andreani-shipping' ); ?>
					</button>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param array $ctx Contexto de la pantalla.
	 * @return array Filas { label, value } con los datos de la cuenta que Andreani devolvió.
	 */
	private function build_identidad_rows( $ctx ) {
		$info = isset( $ctx['info_cliente'] ) && is_array( $ctx['info_cliente'] ) ? $ctx['info_cliente'] : array();

		$campos = array(
			array(
				'id'    => 'titular',
				'label' => __( 'Titular de la cuenta', 'andreani-shipping' ),
				'keys'  => array( 'name', 'razonSocial', 'nombre' ),
			),
			array(
				'id'    => 'cuit',
				'label' => __( 'CUIT', 'andreani-shipping' ),
				'keys'  => array( 'cuit', 'cuil', 'documento' ),
			),
			array(
				'id'    => 'telefono',
				'label' => __( 'Teléfono', 'andreani-shipping' ),
				'keys'  => array( 'phoneNumber', 'telefono', 'phone' ),
			),
			array(
				'id'    => 'cliente',
				'label' => __( 'Número de cliente', 'andreani-shipping' ),
				// `cl` es el número con el que Andreani identifica al comerciante; `clienteId` es un GUID interno.
				'keys'  => array( 'numeroDeCliente', 'cl', 'clientId', 'clienteId' ),
			),
		);

		$rows = array();

		foreach ( $campos as $campo ) {
			foreach ( $campo['keys'] as $key ) {
				if ( ! isset( $info[ $key ] ) || ! is_scalar( $info[ $key ] ) ) {
					continue;
				}
				$value = trim( (string) $info[ $key ] );
				if ( '' === $value || '-' === $value ) {
					continue;
				}
				$rows[] = array(
					'id'    => $campo['id'],
					'label' => $campo['label'],
					'value' => $value,
				);
				break;
			}
		}

		return $rows;
	}

	/**
	 * Separa la identidad en el titular y la línea de apoyo que confirma la cuenta.
	 *
	 * @param array $rows     Filas de build_identidad_rows().
	 * @param bool  $es_corpo Si la cuenta es corporativa (el número de cliente sólo aplica ahí).
	 * @return array { titular, apoyo }
	 */
	private function split_identidad( $rows, $es_corpo ) {
		$titular = '';
		$apoyo   = array();

		foreach ( $rows as $row ) {
			if ( 'titular' === $row['id'] ) {
				$titular = $row['value'];
				continue;
			}

			if ( 'cuit' === $row['id'] ) {
				/* translators: %s: número de CUIT de la cuenta. */
				$apoyo[] = sprintf( __( 'CUIT %s', 'andreani-shipping' ), $row['value'] );
				continue;
			}

			if ( 'cliente' === $row['id'] && $es_corpo ) {
				/* translators: %s: número de cliente de Andreani. */
				$apoyo[] = sprintf( __( 'Cliente %s', 'andreani-shipping' ), $row['value'] );
				continue;
			}

			if ( 'telefono' === $row['id'] ) {
				/* translators: %s: teléfono de contacto de la cuenta. */
				$apoyo[] = sprintf( __( 'Tel. %s', 'andreani-shipping' ), $row['value'] );
			}
		}

		return array(
			'titular' => $titular,
			'apoyo'   => $apoyo,
		);
	}

	private function render_bloque_direccion_origen( $ctx ) {
		$origen = $ctx['origen'];
		$aviso  = Andreani_Origen::FUENTE_WOOCOMMERCE === $origen['direccion_fuente'] && 'yes' !== $origen['direccion_confirmada'];

		$campos = array(
			'calle'     => array( __( 'Calle', 'andreani-shipping' ), __( 'Ej: Av. Corrientes', 'andreani-shipping' ) ),
			'numero'    => array( __( 'Número', 'andreani-shipping' ), __( 'Ej: 1234', 'andreani-shipping' ) ),
			'piso'      => array( __( 'Piso / Depto', 'andreani-shipping' ), __( 'Opcional', 'andreani-shipping' ) ),
			'localidad' => array( __( 'Localidad', 'andreani-shipping' ), __( 'Ej: San Isidro', 'andreani-shipping' ) ),
			'provincia' => array( __( 'Provincia', 'andreani-shipping' ), __( 'Ej: Buenos Aires', 'andreani-shipping' ) ),
		);
		?>
		<div class="andreani-section">
			<h3 class="andreani-section-heading andreani-section-heading--with-icon">
				<span class="andreani-section-heading__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
				</span>
				<span class="andreani-section-heading__title"><?php esc_html_e( 'Dirección de origen', 'andreani-shipping' ); ?></span>
			</h3>
			<p class="andreani-section-description"><?php esc_html_e( 'La dirección desde la que despachás. Andreani la usa para cotizar y para dar de alta tus envíos.', 'andreani-shipping' ); ?></p>

			<?php if ( $aviso ) : ?>
				<div class="andreani-origen-aviso" role="status">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
					<span><?php esc_html_e( 'Tomamos estos datos de la dirección de tu tienda. Revisalos y guardá para confirmarlos.', 'andreani-shipping' ); ?></span>
				</div>
			<?php endif; ?>

			<?php
			$tienda = Andreani_Origen::valores_de_woocommerce();
			if ( '' !== trim( implode( '', $tienda ) ) ) :
				$cp_tienda = Andreani_Postcode::normalize( get_option( 'woocommerce_store_postcode', '' ) );
				if ( '' !== $cp_tienda ) {
					$tienda['cp_tienda'] = $cp_tienda;
				}
				?>
				<p class="andreani-origen-traer-fila">
					<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-origen-traer" data-andreani-origen-tienda="<?php echo esc_attr( wp_json_encode( $tienda ) ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 0 1 15-6.7L21 8"/></svg>
						<?php esc_html_e( 'Traer de mi tienda', 'andreani-shipping' ); ?>
					</button>
					<span class="andreani-origen-traer__aviso" role="status" hidden><?php esc_html_e( 'Listo: revisá los datos y guardá para confirmarlos.', 'andreani-shipping' ); ?></span>
					<span class="andreani-origen-traer__aviso" role="status" hidden data-andreani-origen-aviso-cp></span>
					<input type="hidden" name="andreani_origen[desde_tienda]" value="" data-andreani-origen-desde-tienda />
				</p>
			<?php endif; ?>

			<div class="andreani-origen-grid">
				<?php $cp_key = $this->get_field_key( 'cp_origen' ); ?>
				<p class="andreani-origen-campo andreani-origen-campo--cp">
					<label for="<?php echo esc_attr( $cp_key ); ?>"><?php esc_html_e( 'Código postal *', 'andreani-shipping' ); ?></label>
					<input
						type="text"
						id="<?php echo esc_attr( $cp_key ); ?>"
						name="<?php echo esc_attr( $cp_key ); ?>"
						value="<?php echo esc_attr( $ctx['cp_origen'] ); ?>"
						placeholder="<?php esc_attr_e( 'Ej: 1425 o C1425ABC', 'andreani-shipping' ); ?>"
					/>
				</p>
				<?php foreach ( $campos as $key => $campo ) : ?>
					<p class="andreani-origen-campo andreani-origen-campo--<?php echo esc_attr( $key ); ?>">
						<label for="<?php echo esc_attr( 'andreani_origen_' . $key ); ?>"><?php echo esc_html( $campo[0] ); ?></label>
						<input
							type="text"
							id="<?php echo esc_attr( 'andreani_origen_' . $key ); ?>"
							name="<?php echo esc_attr( 'andreani_origen[' . $key . ']' ); ?>"
							value="<?php echo esc_attr( $origen[ $key ] ); ?>"
							placeholder="<?php echo esc_attr( $campo[1] ); ?>"
						/>
					</p>
				<?php endforeach; ?>
			</div>

			<p class="andreani-origen-campo andreani-origen-campo--remitente">
				<label for="andreani_origen_remitente_nombre"><?php esc_html_e( 'Nombre del remitente', 'andreani-shipping' ); ?></label>
				<input
					type="text"
					id="andreani_origen_remitente_nombre"
					name="andreani_origen[remitente_nombre]"
					value="<?php echo esc_attr( $origen['remitente_nombre'] ); ?>"
					maxlength="<?php echo esc_attr( Andreani_Origen::REMITENTE_NOMBRE_MAX ); ?>"
					placeholder="<?php esc_attr_e( 'Ej: el nombre de tu tienda', 'andreani-shipping' ); ?>"
				/>
				<span class="andreani-origen-campo__ayuda"><?php esc_html_e( 'Es el nombre que se imprime como remitente en la etiqueta de tus envíos. Si el de tu tienda es distinto al de tu cuenta de Andreani, editalo.', 'andreani-shipping' ); ?></span>
			</p>
		</div>
		<?php
	}

	/**
	 * @param string $direccion Dirección de la sucursal, tal como la devuelve Andreani.
	 * @return string URL de búsqueda en Google Maps.
	 */
	private function build_maps_url( $direccion ) {
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $direccion . ', Argentina' );
	}

	/**
	 * Manda a Andreani desde dónde despacha la tienda. Best-effort: si la API no
	 * responde, la configuración local ya quedó guardada igual.
	 *
	 * @param string $cp_origen Código postal de origen ya normalizado.
	 */
	private function push_origen_a_andreani( $cp_origen ) {
		if ( '' === (string) $this->get_option( 'hash_andreani', '' ) ) {
			return;
		}

		if ( ! Andreani_Origen::push_a_andreani( $cp_origen ) ) {
			Andreani_Origen::marcar_push_pendiente();
		}
	}

	private function render_bloque_sucursal_origen( $ctx ) {
		$vista = $this->resolve_origen_view( $ctx );
		?>
		<div class="andreani-section">
			<fieldset class="andreani-origen-sucursales">
				<legend class="andreani-section-heading andreani-section-heading--with-icon">
					<span class="andreani-section-heading__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21V9l9-6 9 6v12"/><path d="M9 21v-6h6v6"/></svg>
					</span>
					<span class="andreani-section-heading__title"><?php esc_html_e( 'Sucursal de origen', 'andreani-shipping' ); ?></span>
				</legend>
				<p class="andreani-section-description"><?php esc_html_e( 'Elegí la sucursal desde la que despachás. Si no elegís ninguna, Andreani la asigna por tu código postal.', 'andreani-shipping' ); ?></p>

				<div class="andreani-origen-buscador"<?php echo $vista['disponible'] ? '' : ' hidden'; ?> data-andreani-origen-buscador>
					<span class="andreani-origen-buscador__icono" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
					</span>
					<input
						type="search"
						class="andreani-origen-buscador__input"
						id="andreani_origen_buscador"
						placeholder="<?php esc_attr_e( 'Buscá por nombre, dirección o localidad', 'andreani-shipping' ); ?>"
						aria-label="<?php esc_attr_e( 'Buscar sucursal de origen', 'andreani-shipping' ); ?>"
						autocomplete="off"
						data-andreani-origen-filtro
					/>
					<span class="andreani-origen-buscador__contador" aria-live="polite" data-andreani-origen-contador></span>
				</div>

				<div class="andreani-origen-sucursales__lista" aria-live="polite" data-andreani-origen-lista>
					<?php $this->render_origen_opciones( $ctx['origen'], $vista ); ?>
				</div>
			</fieldset>
		</div>
		<?php
	}

	/**
	 * @param array $origen Origen persistido.
	 * @param array $vista  Resultado de resolve_origen_view().
	 */
	private function render_origen_opciones( $origen, $vista ) {
		if ( ! $vista['disponible'] ) {
			?>
			<p class="andreani-origen-estado andreani-origen-estado--vacio"><?php echo esc_html( $vista['mensaje'] ); ?></p>
			<?php
			return;
		}

		$seleccionada   = Andreani_Origen::MODO_FIJADA === $origen['modo'] ? (string) $origen['sucursal_codigo'] : '';
		$default_codigo = isset( $vista['default']['codigo'] ) ? (string) $vista['default']['codigo'] : '';

		$fijadas = array();
		$resto   = array();
		foreach ( $vista['sucursales'] as $sucursal ) {
			if ( '' !== $seleccionada && $sucursal['codigo'] === $seleccionada ) {
				$fijadas[] = $sucursal;
				continue;
			}
			if ( '' !== $default_codigo && $sucursal['codigo'] === $default_codigo ) {
				continue;
			}
			$resto[] = $sucursal;
		}
		?>
		<input type="hidden" name="andreani_origen[sucursal_presente]" value="1" />

		<?php foreach ( $fijadas as $sucursal ) : ?>
			<?php $this->render_origen_opcion_sucursal( $sucursal, $seleccionada ); ?>
		<?php endforeach; ?>

		<label class="andr-card andreani-origen-opcion andreani-origen-opcion--auto">
			<input type="radio" name="andreani_origen[sucursal_codigo]" value="" <?php checked( '', $seleccionada ); ?> />
			<span class="andreani-origen-opcion__cuerpo">
				<?php if ( ! empty( $vista['default']['nombre'] ) ) : ?>
					<span class="andreani-origen-opcion__titulo">
						<?php echo esc_html( $vista['default']['nombre'] ); ?>
						<span class="andreani-origen-opcion__tag"><?php esc_html_e( 'Por defecto', 'andreani-shipping' ); ?></span>
					</span>
					<?php if ( ! empty( $vista['default']['direccion'] ) ) : ?>
						<span class="andreani-origen-opcion__detalle"><?php echo esc_html( $vista['default']['direccion'] ); ?></span>
					<?php endif; ?>
					<span class="andreani-origen-opcion__detalle"><?php esc_html_e( 'Es la que Andreani asigna para tu código postal. Si cambia, se actualiza sola.', 'andreani-shipping' ); ?></span>
				<?php else : ?>
					<span class="andreani-origen-opcion__titulo"><?php esc_html_e( 'Por defecto — la asigna Andreani por tu código postal', 'andreani-shipping' ); ?></span>
				<?php endif; ?>
			</span>
		</label>

		<?php foreach ( $resto as $sucursal ) : ?>
			<?php $this->render_origen_opcion_sucursal( $sucursal, $seleccionada ); ?>
		<?php endforeach; ?>
		<?php
	}

	private function render_origen_opcion_sucursal( $sucursal, $seleccionada ) {
		?>
		<label class="andr-card andreani-origen-opcion">
			<input type="radio" name="andreani_origen[sucursal_codigo]" value="<?php echo esc_attr( $sucursal['codigo'] ); ?>" <?php checked( $sucursal['codigo'], $seleccionada ); ?> />
			<span class="andreani-origen-opcion__cuerpo">
				<span class="andreani-origen-opcion__titulo"><?php echo esc_html( $sucursal['nombre'] ); ?></span>
				<?php if ( '' !== $sucursal['direccion'] ) : ?>
					<span class="andreani-origen-opcion__detalle"><?php echo esc_html( $sucursal['direccion'] ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( '' !== $sucursal['direccion'] ) : ?>
				<a class="andreani-origen-opcion__mapa" href="<?php echo esc_url( $this->build_maps_url( $sucursal['direccion'] ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Ver en el mapa', 'andreani-shipping' ); ?>
				</a>
			<?php endif; ?>
		</label>
		<input type="hidden" name="<?php echo esc_attr( 'andreani_origen[sucursales][' . $sucursal['codigo'] . '][nombre]' ); ?>" value="<?php echo esc_attr( $sucursal['nombre'] ); ?>" />
		<input type="hidden" name="<?php echo esc_attr( 'andreani_origen[sucursales][' . $sucursal['codigo'] . '][direccion]' ); ?>" value="<?php echo esc_attr( $sucursal['direccion'] ); ?>" />
		<?php
	}

	/**
	 * Resuelve, del lado del server, qué mostrar en el bloque de sucursal de origen.
	 *
	 * @param array $ctx Contexto de la pantalla.
	 * @return array { disponible, mensaje, sucursales, default }
	 */
	private function resolve_origen_view( $ctx ) {
		$vista = array(
			'disponible' => false,
			'mensaje'    => '',
			'sucursales' => array(),
			'default'    => array(),
		);

		if ( empty( $ctx['hash_andreani'] ) ) {
			$vista['mensaje'] = __( 'Cargá tu Credencial ID y guardá para poder elegir la sucursal desde la que despachás.', 'andreani-shipping' );
			return $vista;
		}

		$cp = (string) $ctx['cp_origen'];
		if ( '' === $cp || ! Andreani_Postcode::is_valid( $cp ) ) {
			$vista['mensaje'] = __( 'Cargá un código postal de origen válido y guardá para ver las sucursales disponibles.', 'andreani-shipping' );
			return $vista;
		}

		if ( ! Andreani_Api_Manager::is_api_available() ) {
			$vista['mensaje'] = __( 'No pudimos conectarnos con Andreani para traer las sucursales. Probá de nuevo en unos minutos.', 'andreani-shipping' );
			return $vista;
		}

		$sucursales = Andreani_Api_Manager::get_sucursales_origen( $cp );

		if ( null === $sucursales || is_wp_error( $sucursales ) || empty( $sucursales['details'] ) ) {
			$vista['mensaje'] = __( 'No encontramos sucursales habilitadas como origen para tu código postal. Tus envíos van a salir con la sucursal que asigne Andreani.', 'andreani-shipping' );
			return $vista;
		}

		foreach ( $sucursales['details'] as $codigo => $detalle ) {
			$vista['sucursales'][] = array(
				'codigo'    => (string) $codigo,
				'nombre'    => isset( $detalle['descripcion'] ) ? (string) $detalle['descripcion'] : (string) $codigo,
				'direccion' => isset( $detalle['direccion'] ) ? (string) $detalle['direccion'] : '',
			);
		}

		$predeterminada = Andreani_Api_Manager::get_origen_predeterminado( $cp );
		if ( is_array( $predeterminada ) && ! empty( $predeterminada['descripcion'] ) ) {
			$vista['default'] = array(
				'codigo'    => isset( $predeterminada['codigo'] ) ? (string) $predeterminada['codigo'] : '',
				'nombre'    => (string) $predeterminada['descripcion'],
				'direccion' => isset( $predeterminada['direccion'] ) ? (string) $predeterminada['direccion'] : '',
			);
		}

		$vista['disponible'] = true;

		return $vista;
	}

	private function render_panel_modos( $ctx ) {
		?>
		<div class="andr-tabs__panel" data-panel="modos" role="tabpanel" id="andr-panel-modos">
			<?php if ( empty( $ctx['hash_andreani'] ) ) : ?>
				<div class="andreani-empty-state">
					<div class="andreani-empty-state__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M32 6L8 16v14c0 13.3 10.2 24.6 24 28 13.8-3.4 24-14.7 24-28V16L32 6z"/><path d="M22 32l8 8 14-14"/></svg>
					</div>
					<h3 class="andreani-empty-state__title"><?php esc_html_e( 'Configurá tu credencial primero', 'andreani-shipping' ); ?></h3>
					<p class="andreani-empty-state__desc"><?php echo wp_kses( __( 'Para ver tus modos de entrega, primero ingresá tu Credencial ID en la pestaña <strong>Cuenta</strong>.', 'andreani-shipping' ), array( 'strong' => array() ) ); ?></p>
					<button type="button" class="andr-btn andr-btn--primary andr-btn--sm andreani-empty-state__cta" data-goto-tab="cuenta"><?php esc_html_e( 'Ir a Cuenta', 'andreani-shipping' ); ?></button>
				</div>
			<?php else :
				$type_info = Andreani_Client_Type::from_id( $ctx['tipo_cliente'] );
				$contratos = $this->resolve_contratos_for_type( $type_info );

				if ( ! empty( $contratos ) ) {
					$this->render_modos_list( $type_info, $contratos, $ctx['config_por_modo'] );
				} else {
					$this->render_modos_empty_state( $type_info );
				}
			endif; ?>
		</div>
		<?php
	}

	private function render_modos_list( $type_info, $contratos, $config_por_modo ) {
		$total           = count( $contratos );
		$habilitados     = $this->count_enabled_modos( $contratos, $config_por_modo );
		$stats_state     = $habilitados === $total ? 'all' : ( $habilitados > 0 ? 'partial' : 'none' );
		$panel_data_card = 'contratos-' . ( $type_info ? $type_info->id() : 'desconocido' );
		$heading_title   = $type_info && $type_info->can( 'contract_details' )
			? __( 'Contratos disponibles', 'andreani-shipping' )
			: __( 'Modos de entrega', 'andreani-shipping' );
		?>
		<h3 class="andreani-section-heading andreani-section-heading--with-action andreani-section-heading--with-icon">
			<span class="andreani-section-heading__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9 4-9-4 9-4 9 4z"/><path d="M3 8v8l9 4 9-4V8"/></svg>
			</span>
			<span class="andreani-section-heading__title"><?php echo esc_html( $heading_title ); ?></span>
			<span class="andreani-contratos-stats andreani-contratos-stats--<?php echo esc_attr( $stats_state ); ?>" data-total="<?php echo esc_attr( $total ); ?>"><?php esc_html_e( 'Habilitados', 'andreani-shipping' ); ?> <span class="andreani-contratos-stats__count"><?php echo esc_html( $habilitados ); ?>/<?php echo esc_html( $total ); ?></span></span>
			<?php if ( $type_info && $type_info->supports_contract_refresh() ) : ?>
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-refresh-contratos" data-nonce="<?php echo esc_attr( wp_create_nonce( 'andreani_refresh_contratos' ) ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
				<?php esc_html_e( 'Actualizar', 'andreani-shipping' ); ?>
			</button>
			<?php endif; ?>
		</h3>
		<p class="andreani-section-description"><?php esc_html_e( 'Habilitá los servicios de Andreani que querés ofrecer y configurá costos adicionales o envío gratis por modo.', 'andreani-shipping' ); ?></p>
		<div class="andreani-modos-panel andreani-modos-panel--contratos andreani-modos-panel--seamless" data-card="<?php echo esc_attr( $panel_data_card ); ?>">
			<div class="andreani-modos-panel__body">
				<p class="andreani-contratos-description"><?php esc_html_e( 'Configurá cada modo de entrega según tus necesidades.', 'andreani-shipping' ); ?></p>
				<div class="andreani-modos-list">
					<?php
					$show_detail = $type_info && $type_info->shows_contract_details();
					foreach ( $contratos as $contrato ) {
						$this->render_modo_card( $contrato, $config_por_modo, $show_detail );
					}
					?>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_modos_empty_state( $type_info ) {
		$has_refresh = $type_info && $type_info->supports_contract_refresh();
		$empty_title = $has_refresh
			? __( 'No hay contratos cargados todavía', 'andreani-shipping' )
			: __( 'No hay modos de entrega cargados', 'andreani-shipping' );
		$empty_desc = $has_refresh
			? __( 'Probá presionar Actualizar o revisá tu credencial en la pestaña Cuenta.', 'andreani-shipping' )
			: __( 'Revisá tu credencial en la pestaña Cuenta.', 'andreani-shipping' );
		?>
		<div class="andreani-empty-state">
			<div class="andreani-empty-state__icon" aria-hidden="true">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 18l24-12 24 12-24 12L8 18z"/><path d="M8 18v22l24 12 24-12V18"/><line x1="32" y1="30" x2="32" y2="52"/></svg>
			</div>
			<h3 class="andreani-empty-state__title"><?php echo esc_html( $empty_title ); ?></h3>
			<p class="andreani-empty-state__desc"><?php echo esc_html( $empty_desc ); ?></p>
			<?php if ( $has_refresh ) : ?>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm andreani-refresh-contratos andreani-empty-state__cta" data-nonce="<?php echo esc_attr( wp_create_nonce( 'andreani_refresh_contratos' ) ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
				<?php esc_html_e( 'Actualizar', 'andreani-shipping' ); ?>
			</button>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_panel_checkout( $checkout_fields, $cotizador_fields ) {
		?>
		<div class="andr-tabs__panel" data-panel="checkout" role="tabpanel" id="andr-panel-checkout">
			<div class="andreani-section">
				<h3 class="andreani-section-heading andreani-section-heading--with-icon">
					<span class="andreani-section-heading__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
					</span>
					<span class="andreani-section-heading__title"><?php esc_html_e( 'Renderizado del checkout', 'andreani-shipping' ); ?></span>
				</h3>
				<p class="andreani-section-description"><?php esc_html_e( 'Cómo aparecen los campos de Andreani en el checkout. Usá el modo Manual con shortcodes si tu tema o page builder no muestra los campos automáticamente.', 'andreani-shipping' ); ?></p>
				<?php if ( class_exists( 'Andreani_Blocks' ) && Andreani_Blocks::is_block_checkout() ) : ?>
					<p class="andreani-section-description"><?php esc_html_e( 'Tu tienda usa el checkout por bloques de WooCommerce: el selector de sucursal y el DNI se integran solos (soporte nativo).', 'andreani-shipping' ); ?></p>
				<?php endif; ?>
				<table class="form-table">
					<?php $this->generate_settings_html( $checkout_fields ); ?>
				</table>
			</div>

			<div class="andreani-section">
				<h3 class="andreani-section-heading andreani-section-heading--with-icon">
					<span class="andreani-section-heading__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
					</span>
					<span class="andreani-section-heading__title"><?php esc_html_e( 'Cotizador en producto', 'andreani-shipping' ); ?></span>
				</h3>
				<p class="andreani-section-description"><?php esc_html_e( 'Widget que permite a tus clientes calcular el costo de envío directo desde la página de producto, sin tener que llegar al checkout.', 'andreani-shipping' ); ?></p>
				<table class="form-table">
					<?php $this->generate_settings_html( $cotizador_fields ); ?>
				</table>
			</div>

		</div>
		<?php
	}

	private function render_panel_avanzado( $avanzado_fields ) {
		?>
		<div class="andr-tabs__panel" data-panel="avanzado" role="tabpanel" id="andr-panel-avanzado">
			<?php $this->render_bloque_impresion(); ?>

			<div class="andreani-section">
				<h3 class="andreani-section-heading andreani-section-heading--with-icon">
					<span class="andreani-section-heading__icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
					</span>
					<span class="andreani-section-heading__title"><?php esc_html_e( 'Opciones técnicas', 'andreani-shipping' ); ?></span>
				</h3>
				<p class="andreani-section-description"><?php esc_html_e( 'Configuración para diagnóstico y formato de costos. Solo activá el modo debug si estás resolviendo un problema concreto.', 'andreani-shipping' ); ?></p>
				<table class="form-table">
					<?php $this->generate_settings_html( $avanzado_fields ); ?>
				</table>
			</div>
		</div>
		<?php
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/print-settings-modal.php';
	}

	private function render_settings_footer() {
		?>
		<div class="andreani-settings-footer">
			<div class="andreani-settings-footer__icon">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M11 7h2v2h-2zm0 4h2v6h-2zm1-9C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z"/></svg>
			</div>
			<p class="andreani-settings-footer__text">
				<?php
				printf(
					/* translators: %s: link to Andreani support */
					esc_html__( '¿Necesitás ayuda? Visitá %s o contactanos a través del centro de soporte.', 'andreani-shipping' ),
					'<a href="https://www.andreani.com" target="_blank" rel="noopener noreferrer">andreani.com</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	private function render_products_warning() {
		$productos_sin_datos = $this->get_productos_sin_datos();

		if ( empty( $productos_sin_datos ) ) {
			return;
		}

		$total_productos   = count( $productos_sin_datos );
		$productos_mostrar = array_slice( $productos_sin_datos, 0, 20 );
		?>
		<div class="andreani-products-warning andreani-products-warning--collapsed">
			<div class="andreani-products-warning__header">
				<div class="andreani-products-warning__icon">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
				</div>
				<div class="andreani-products-warning__title">
					<?php
					printf(
						/* translators: %d: number of products */
						esc_html__( 'Productos sin peso o dimensiones (%d)', 'andreani-shipping' ),
						$total_productos
					);
					?>
				</div>
				<button type="button" class="andreani-products-warning__toggle">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>
				</button>
			</div>
			<div class="andreani-products-warning__body">
				<p class="andreani-products-warning__desc">
					<?php esc_html_e( 'Los siguientes productos no tienen peso o dimensiones configuradas. Sin estos datos, Andreani no puede cotizar el envío correctamente.', 'andreani-shipping' ); ?>
				</p>
				<ul class="andreani-products-warning__list">
					<?php foreach ( $productos_mostrar as $product_id ) :
						$product = wc_get_product( $product_id );
						if ( ! $product ) {
							continue;
						}
					?>
						<li class="andreani-products-warning__item">
							<span class="andreani-products-warning__product-name"><?php echo esc_html( $product->get_name() ); ?></span>
							<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>" class="andreani-products-warning__edit-link" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Editar', 'andreani-shipping' ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( $total_productos > 20 ) : ?>
					<p class="andreani-products-warning__more">
						<?php
						printf(
							/* translators: %d: number of additional products */
							esc_html__( 'y %d más...', 'andreani-shipping' ),
							$total_productos - 20
						);
						?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private function render_modo_card( $contrato, $config_por_modo, $show_detail = false ) {
		$modo_id                 = $contrato['modoDeEntregaNombre'];
		$modo_key                = Andreani_Api_Response::normalize_modo_key( $modo_id );
		$modo_config             = $this->get_modo_config( $config_por_modo, $modo_id );
		$is_enabled              = isset( $modo_config['enabled'] ) ? $modo_config['enabled'] : true;
		$costo_adicional_enabled = isset( $modo_config['costo_adicional_enabled'] ) ? $modo_config['costo_adicional_enabled'] : false;
		$costo_adicional         = isset( $modo_config['costo_adicional'] ) ? $modo_config['costo_adicional'] : '';
		$motivo                  = isset( $modo_config['motivo'] ) ? $modo_config['motivo'] : '';
		$envio_gratis            = isset( $modo_config['envio_gratis'] ) ? $modo_config['envio_gratis'] : false;
		$envio_gratis_monto      = isset( $modo_config['envio_gratis_monto'] ) ? $modo_config['envio_gratis_monto'] : '0';
		?>
		<div class="andreani-modo-card andreani-modo-card--collapsed <?php echo ! $is_enabled ? 'andreani-modo-card--disabled' : ''; ?>" data-modo="<?php echo esc_attr( $modo_key ); ?>">
			<div class="andreani-modo-card__header">
				<label class="andreani-modo-card__toggle" onclick="event.stopPropagation();">
					<input type="checkbox" class="andreani-modo-enabled" <?php checked( $is_enabled ); ?> />
					<span class="andreani-modo-card__slider"></span>
				</label>
				<div class="andreani-modo-card__info">
					<span class="andreani-modo-card__name"><?php echo esc_html( ucfirst( $contrato['modoDeEntregaNombre'] ) ); ?></span>
					<?php if ( $show_detail ) : ?>
					<span class="andreani-modo-card__detail"><?php echo esc_html( $contrato['tipoDeEnvioNombre'] ); ?> - <?php echo esc_html( $contrato['numeroDeContrato'] ); ?></span>
					<?php endif; ?>
				</div>
				<button type="button" class="andreani-modo-card__expand" aria-label="<?php esc_attr_e( 'Expandir/colapsar', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z"/></svg>
				</button>
			</div>
			<div class="andreani-modo-card__body">
				<div class="andreani-modo-card__form">
					<div class="andreani-modo-card__field andreani-modo-card__field--checkbox">
						<label>
							<input type="checkbox" class="andreani-modo-costo-enabled" <?php checked( $costo_adicional_enabled ); ?> />
							<?php esc_html_e( 'Agregar costo adicional', 'andreani-shipping' ); ?>
						</label>
					</div>
					<div class="andreani-modo-card__field andreani-modo-card__field--costo <?php echo ! $costo_adicional_enabled ? 'andreani-hidden' : ''; ?>">
						<label><?php esc_html_e( 'Costo adicional', 'andreani-shipping' ); ?> <span class="andreani-modo-card__label-hint">(<?php esc_html_e( 'Motivo', 'andreani-shipping' ); ?>)</span></label>
						<div class="andreani-modo-card__inputs-row">
							<div class="andreani-input-prefix">
								<span>$</span>
								<input type="number" class="andreani-modo-costo" value="<?php echo esc_attr( $costo_adicional ); ?>" min="0" step="1" placeholder="0" />
							</div>
							<input type="text" class="andreani-modo-motivo" value="<?php echo esc_attr( $motivo ); ?>" placeholder="<?php esc_attr_e( 'Ej: Embalaje especial', 'andreani-shipping' ); ?>" />
						</div>
					</div>
					<div class="andreani-modo-card__field andreani-modo-card__field--checkbox">
						<label>
							<input type="checkbox" class="andreani-modo-gratis" <?php checked( $envio_gratis ); ?> />
							<?php esc_html_e( 'Envío gratis para el cliente', 'andreani-shipping' ); ?>
						</label>
					</div>
					<div class="andreani-modo-card__field andreani-modo-card__field--monto <?php echo ! $envio_gratis ? 'andreani-hidden' : ''; ?>">
						<label><?php esc_html_e( 'Monto mínimo para envío gratis', 'andreani-shipping' ); ?></label>
						<div class="andreani-input-prefix">
							<span>$</span>
							<input type="number" class="andreani-modo-monto" value="<?php echo esc_attr( $envio_gratis_monto ); ?>" min="0" step="1" placeholder="0" />
						</div>
						<span class="andreani-modo-card__hint"><?php esc_html_e( 'Dejar en 0 para que siempre sea gratis', 'andreani-shipping' ); ?></span>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function process_after_shipping_calculator() {
		if ( empty( $_POST ) || ( ! is_checkout() && ! is_cart() ) ) {
			return;
		}

		$nonce_action = is_checkout() ? 'update-order-review' : 'woocommerce-cart';
		$nonce_field = is_checkout() ? 'security' : 'woocommerce-cart-nonce';
		if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
			return;
		}

		if ( is_checkout() && isset( $_POST['sucursales_andreani'] ) ) {
			Andreani_Utils::set_session_data('codigo_sucursal', sanitize_text_field( wp_unslash( $_POST['sucursales_andreani'] ) ) );
		}

		if ( is_cart() && isset( $_POST['calc_shipping_postcode'] ) ) {
			Andreani_Utils::set_session_data('cp_destino', sanitize_text_field( wp_unslash( $_POST['calc_shipping_postcode'] ) ) );
		}
	}

	public function process_admin_options() {
		$post_data = $this->get_post_data();
		$prefix = 'woocommerce_' . $this->id . '_';

		$errors = $this->validate_settings( $post_data, $prefix );

		if ( ! empty( $errors ) ) {
			foreach ( $errors as $error ) {
				Andreani_Utils::show_error_message( $error, 'error' );
			}
			return false;
		}

		$hash_andreani = $this->get_post_field( $post_data, $prefix . 'hash_andreani' );

		if ( ! empty( $this->resolved_client_type ) ) {
			$tipo_cliente = $this->resolved_client_type;
		} else {
			$tipo_cliente = $this->get_option( 'tipo_cliente', '' );
			if ( empty( $tipo_cliente ) ) {
				$tipo_cliente = Andreani_Utils::detect_client_type_from_hash( $hash_andreani );
			}
		}

		if ( $tipo_cliente ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado por WooCommerce en el formulario de configuración
			$_POST[ $prefix . 'tipo_cliente' ] = $tipo_cliente;
		}

		$cp_origen = Andreani_Postcode::normalize( $this->get_post_field( $post_data, $prefix . 'cp_origen' ) );

		if ( ! array_key_exists( $prefix . 'cp_origen', $post_data ) ) {
			$cp_origen = Andreani_Postcode::normalize( $this->get_option( 'cp_origen', '' ) );

			if ( '' === $cp_origen ) {
				$cp_origen = Andreani_Postcode::normalize( get_option( 'woocommerce_store_postcode', '' ) );
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado por WooCommerce en el formulario de configuración
		$_POST[ $prefix . 'cp_origen' ] = $cp_origen;

		$hash_previo = $this->get_option( 'hash_andreani', '' );
		$cp_previo   = Andreani_Postcode::normalize( $this->get_option( 'cp_origen', '' ) );

		$this->sanitize_config_por_modo( $post_data, $prefix );

		Andreani_Api_Manager::clear_settings_cache();

		$saved = parent::process_admin_options();

		// Fuera del if: process_admin_options() devuelve false cuando ningun campo de
		// WooCommerce cambio, y los datos de origen son inputs propios. Adentro, editar
		// solo la direccion no guardaba nada.
		Andreani_Origen::save_from_post();

		if ( $saved ) {
			$cambio_credencial = $hash_andreani !== $hash_previo;
			$cambio_cp         = $cp_origen !== $cp_previo;

			if ( $cambio_credencial || $cambio_cp ) {
				$motivo = $cambio_credencial
					? __( 'cambió la credencial', 'andreani-shipping' )
					: __( 'cambió el código postal de origen', 'andreani-shipping' );

				if ( Andreani_Origen::reset_pin( $motivo ) ) {
					Andreani_Utils::show_warning_message(
						$cambio_credencial
							? __( 'Cambiaste la credencial, así que la sucursal de origen volvió a Automática. Si querés fijar una, elegila de nuevo.', 'andreani-shipping' )
							: __( 'Cambiaste el código postal de origen, así que la sucursal de origen volvió a Automática. Si querés fijar una, elegila de nuevo.', 'andreani-shipping' )
					);
				}

				Andreani_Cache_Service::clear_origen();
			}

			Andreani_Cache_Service::clear_cotizaciones();

			$this->validate_cp_origen_post_save( $cp_origen );

			if ( ! empty( $hash_andreani ) && class_exists( 'Andreani_Upgrader' ) ) {
				$option_name = 'woocommerce_' . $this->id . '_' . $this->instance_id . '_settings';
				Andreani_Upgrader::clear_expired_credential_flag( $option_name );
			}
		}

		$this->push_origen_a_andreani( $cp_origen );

		return $saved;
	}

	private function validate_cp_origen_post_save( $cp_origen ) {
		if ( empty( $cp_origen ) || ! Andreani_Postcode::is_valid( $cp_origen ) ) {
			update_option( 'andreani_cp_origen_valid', 'no', false );
			return;
		}

		if ( ! Andreani_Api_Manager::is_api_available() ) {
			delete_option( 'andreani_cp_origen_valid' );
			return;
		}

		$sucursales = Andreani_Api_Manager::get_sucursales( $cp_origen );

		if ( null === $sucursales ) {
			delete_option( 'andreani_cp_origen_valid' );
		} elseif ( is_wp_error( $sucursales ) || empty( $sucursales['options'] ) ) {
			update_option( 'andreani_cp_origen_valid', 'no', false );
		} else {
			update_option( 'andreani_cp_origen_valid', 'yes', false );
		}
	}

	private function sanitize_config_por_modo( $post_data, $prefix ) {
		$raw_json = $this->get_post_field( $post_data, $prefix . 'config_por_modo', '{}' );
		$config = json_decode( $raw_json, true );

		if ( ! is_array( $config ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST[ $prefix . 'config_por_modo' ] = '{}';
			return;
		}

		$sanitized = array();
		$allowed_keys = array( 'enabled', 'costo_adicional_enabled', 'costo_adicional', 'motivo', 'envio_gratis', 'envio_gratis_monto' );

		foreach ( $config as $modo => $modo_config ) {
			$modo_key = Andreani_Api_Response::normalize_modo_key( $modo );
			if ( '' === $modo_key || ! is_array( $modo_config ) ) {
				continue;
			}

			$entry = array();
			foreach ( $allowed_keys as $key ) {
				if ( ! isset( $modo_config[ $key ] ) ) {
					continue;
				}

				switch ( $key ) {
					case 'enabled':
					case 'costo_adicional_enabled':
					case 'envio_gratis':
						$entry[ $key ] = (bool) $modo_config[ $key ];
						break;

					case 'costo_adicional':
					case 'envio_gratis_monto':
						$entry[ $key ] = max( 0, floatval( $modo_config[ $key ] ) );
						break;

					case 'motivo':
						$entry[ $key ] = sanitize_text_field( $modo_config[ $key ] );
						break;
				}
			}

			if ( isset( $sanitized[ $modo_key ] ) ) {
				$sanitized[ $modo_key ] = array_merge( $entry, $sanitized[ $modo_key ] );
			} else {
				$sanitized[ $modo_key ] = $entry;
			}
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$_POST[ $prefix . 'config_por_modo' ] = wp_json_encode( $sanitized );
	}

	private function validate_settings( $post_data, $prefix ) {
		$errors = array();

		$hash_andreani = $this->get_post_field( $post_data, $prefix . 'hash_andreani' );
		$cp_origen = $this->get_post_field( $post_data, $prefix . 'cp_origen' );

		if ( empty( $hash_andreani ) ) {
			$errors[] = __( 'Error: El campo Credencial ID (*) es obligatorio.', 'andreani-shipping' );
		} else {
			$tipo_cliente = Andreani_Utils::detect_client_type_from_hash( $hash_andreani );

			if ( null === $tipo_cliente ) {
				$errors[] = __( 'Error: La Credencial ID no tiene un formato válido. No se pudo detectar el tipo de cliente.', 'andreani-shipping' );
			} else {
				$hash_guardado = $this->get_option( 'hash_andreani', '' );
				$hash_cambio = $hash_andreani !== $hash_guardado;

				if ( $hash_cambio ) {
					$resolved = Andreani_Api_Manager::validate_hash( $hash_andreani, $tipo_cliente );
					if ( false === $resolved ) {
						$motivo = Andreani_Api_Manager::get_last_validation_error();
						$errors[] = 'Error: ' . ( is_wp_error( $motivo )
							? $motivo->get_error_message()
							: __( 'la Credencial ID ingresada no es válida.', 'andreani-shipping' ) );
					} else {
						$this->resolved_client_type = $resolved;
					}
				}
			}
		}

		// La pestaña Origen no renderiza el campo hasta que hay credencial guardada, asi que en la
		// primera configuracion el CP no viaja en el POST: exigirlo ahi traba la instalacion entera.
		if ( array_key_exists( $prefix . 'cp_origen', $post_data ) ) {
			if ( empty( $cp_origen ) ) {
				$errors[] = __( 'Error: El campo Código Postal Origen (*) es obligatorio.', 'andreani-shipping' );
			} elseif ( ! Andreani_Postcode::is_valid( $cp_origen ) ) {
				$errors[] = __( 'Error: El Código Postal Origen no tiene un formato válido. Debe ser 4 dígitos (ej: 1425) o formato CPA (ej: C1425ABC).', 'andreani-shipping' );
			}
		}

		return $errors;
	}

	private function get_post_field( $post_data, $field_name, $default = '' ) {
		return isset( $post_data[ $field_name ] )
			? trim( sanitize_text_field( wp_unslash( $post_data[ $field_name ] ) ) )
			: $default;
	}

	private function get_productos_sin_datos() {
		global $wpdb;

		return $wpdb->get_col(
			"SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			WHERE p.post_type = 'product'
			AND p.post_status = 'publish'
			AND (
				NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_weight' AND pm.meta_value != '')
				OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_length' AND pm.meta_value != '')
				OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_width' AND pm.meta_value != '')
				OR NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} pm WHERE pm.post_id = p.ID AND pm.meta_key = '_height' AND pm.meta_value != '')
			)"
		);
	}

	private function filter_unique_contratos( $contratos_raw, $tipos_permitidos = null ) {
		$contratos_filtrados = $contratos_raw;

		if ( null !== $tipos_permitidos ) {
			$contratos_filtrados = array_filter( $contratos_raw, function( $contrato ) use ( $tipos_permitidos ) {
				$tipo = isset( $contrato['tipoDeEnvioNombre'] ) ? strtolower( $contrato['tipoDeEnvioNombre'] ) : '';
				return in_array( $tipo, $tipos_permitidos, true );
			} );
		}

		$contratos = array();
		$modos_vistos = array();
		foreach ( $contratos_filtrados as $contrato ) {
			$modo = $contrato['modoDeEntregaNombre'];
			if ( ! in_array( $modo, $modos_vistos, true ) ) {
				$modos_vistos[] = $modo;
				$contratos[] = $contrato;
			}
		}

		return $contratos;
	}

	private function get_modo_config( $config_por_modo, $modo_id ) {
		$modo_key = Andreani_Api_Response::normalize_modo_key( $modo_id );
		if ( '' !== $modo_key && isset( $config_por_modo[ $modo_key ] ) ) {
			return $config_por_modo[ $modo_key ];
		}
		if ( isset( $config_por_modo[ $modo_id ] ) ) {
			return $config_por_modo[ $modo_id ];
		}
		return array();
	}

	private function resolve_contratos_for_type( $type_info ) {
		if ( ! $type_info ) {
			return array();
		}

		$info_cliente  = get_option( $type_info->info_option_name(), array() );
		$contratos_raw = isset( $info_cliente['contratos'] ) ? $info_cliente['contratos'] : array();

		$filter_types = $type_info->contract_filter_types();
		$contratos    = $this->filter_unique_contratos(
			$contratos_raw,
			! empty( $filter_types ) ? $filter_types : null
		);

		$display_order = $type_info->contract_display_order();
		if ( ! empty( $display_order ) && ! empty( $contratos ) ) {
			usort( $contratos, function( $a, $b ) use ( $display_order ) {
				$pos_a = array_search( strtolower( $a['modoDeEntregaNombre'] ), $display_order, true );
				$pos_b = array_search( strtolower( $b['modoDeEntregaNombre'] ), $display_order, true );
				$pos_a = false === $pos_a ? 999 : $pos_a;
				$pos_b = false === $pos_b ? 999 : $pos_b;
				return $pos_a - $pos_b;
			} );
		}

		return $contratos;
	}

	private function count_enabled_modos( $contratos, $config_por_modo ) {
		$habilitados = 0;
		foreach ( $contratos as $c ) {
			$modo_config = $this->get_modo_config( $config_por_modo, $c['modoDeEntregaNombre'] );
			$is_enabled = isset( $modo_config['enabled'] ) ? $modo_config['enabled'] : true;
			if ( $is_enabled ) {
				$habilitados++;
			}
		}
		return $habilitados;
	}

	private function is_mixed_cart( $package ) {
		if ( empty( $package['contents'] ) ) {
			return false;
		}

		$has_bigger   = false;
		$has_standard = false;

		foreach ( $package['contents'] as $values ) {
			$product = $values['data'];

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$product_id = $product->get_id();
			if ( Andreani_Product_Bultos::is_bigger_product( $product_id ) ) {
				$has_bigger = true;
			} else {
				$has_standard = true;
			}

			if ( $has_bigger && $has_standard ) {
				return true;
			}
		}

		return false;
	}

	public function calculate_shipping( $package = array() ) {
		if ( $this->is_mixed_cart( $package ) ) {
			Andreani_Utils::andreani_log( '[COTIZACION] Carrito mixto (Bigger + paquetería estándar) — no se cotiza.', 'warning' );
			return;
		}

		if ( isset( $package['destination']['postcode'] ) ) {
			$package['destination']['postcode'] = Andreani_Postcode::normalize( $package['destination']['postcode'] );
		}
		if ( $codigo_sucursal = Andreani_Utils::get_session_data( 'codigo_sucursal', '' ) ) {
			$package['codigo_sucursal'] = $codigo_sucursal;
		}
		$result = Andreani_Api_Manager::get_cotizacion( $package );

		if ( is_wp_error( $result ) ) {
			$cp_destino = isset( $package['destination']['postcode'] ) ? $package['destination']['postcode'] : 'N/A';
			Andreani_Utils::andreani_log( "[COTIZACION] Error para CP destino {$cp_destino}: " . $result->get_error_message(), 'error' );
		}

		if ( empty( $result['rates'] ) && empty( $result['errors'] ) ) {
			return;
		}

		foreach ( $result['rates'] as $rate ) {
			$this->add_rate( $rate );
		}

		foreach ( $result['errors'] as $error ) {
			Andreani_Utils::andreani_log( '[COTIZACION] ' . $error, 'warning' );
		}
	}
}
