<?php
/**
 * Cotizador de envío para páginas de producto
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Cotizador_Widget {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_shortcode( 'andreani_cotizador', array( $this, 'render_shortcode' ) );
		add_action( 'wp_ajax_andreani_cotizar_envio', array( $this, 'ajax_calculate_rates' ) );
		add_action( 'wp_ajax_nopriv_andreani_cotizar_envio', array( $this, 'ajax_calculate_rates' ) );

		if ( $this->is_enabled() && $this->is_auto_mode() ) {
			$this->register_position_hook();
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		}
	}

	private function is_enabled() {
		return 'yes' === Andreani_Settings_Service::get( 'cotizador_producto', 'no' );
	}

	private function is_auto_mode() {
		return 'auto' === Andreani_Settings_Service::get( 'cotizador_modo', 'auto' );
	}

	private function get_position() {
		return Andreani_Settings_Service::get( 'cotizador_posicion', 'after_add_to_cart' );
	}

	private function register_position_hook() {
		$position = $this->get_position();

		switch ( $position ) {
			case 'before_add_to_cart':
				add_action( 'woocommerce_before_add_to_cart_form', array( $this, 'render_on_product_page' ) );
				break;
			case 'after_price':
				add_action( 'woocommerce_single_product_summary', array( $this, 'render_on_product_page' ), 15 );
				break;
			case 'floating':
				add_action( 'wp_footer', array( $this, 'render_floating_widget' ) );
				break;
			case 'after_add_to_cart':
			default:
				add_action( 'woocommerce_after_add_to_cart_form', array( $this, 'render_on_product_page' ) );
				break;
		}
	}

	public function render_on_product_page() {
		if ( ! is_product() ) {
			return;
		}

		echo $this->render_widget(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_floating_widget() {
		if ( ! is_product() ) {
			return;
		}

		echo $this->render_widget( array( 'floating' => true ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function render_shortcode( $atts ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return '';
		}

		if ( ! is_product() ) {
			return '<!-- Andreani Cotizador: solo disponible en páginas de producto -->';
		}

		$this->enqueue_assets_for_shortcode();

		return $this->render_widget();
	}

	private function render_widget( $atts = array() ) {
		$is_floating = isset( $atts['floating'] ) && $atts['floating'];
		$product_id = $this->get_current_product_id();
		$tooltip = __( 'Calcular envío', 'andreani-shipping' );
		$logo_svg = $this->get_logo_svg();

		$theme = Andreani_Settings_Service::get( 'cotizador_tema', 'light' );
		$theme_class = 'andreani-calc-widget--theme-' . esc_attr( $theme );

		$floating_class = $is_floating ? 'andreani-calc-widget--floating' : '';

		ob_start();
		?>
		<div class="andreani-calc-widget <?php echo esc_attr( $theme_class ); ?> <?php echo esc_attr( $floating_class ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>">
			<div class="andreani-calc-logo-fixed"><?php echo $logo_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<button type="button" class="andreani-calc-bubble" data-tooltip="<?php echo esc_attr( $tooltip ); ?>"></button>
			<div class="andreani-calc-content">
				<div class="andreani-calc-header">
					<span class="andreani-calc-header__title"><?php esc_html_e( '¿Dónde querés recibir tu pedido?', 'andreani-shipping' ); ?></span>
				</div>
				<div class="andreani-calc-form">
					<div class="andreani-calc-input-wrapper">
						<input
							id="andreani-calc-postcode<?php echo $is_floating ? '-floating' : ''; ?>"
							type="text"
							class="andreani-calc-postcode"
							maxlength="8"
							required
						>
						<label for="andreani-calc-postcode<?php echo $is_floating ? '-floating' : ''; ?>" class="andreani-calc-label"><?php esc_html_e( 'Ingresá un código postal', 'andreani-shipping' ); ?></label>
					</div>
					<button type="button" class="andreani-calc-button">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<circle cx="10" cy="10" r="7"></circle>
							<path d="M21 21l-6-6"></path>
						</svg>
					</button>
				</div>
				<div class="andreani-calc-loading">
					<span class="andreani-calc-spinner"></span>
					<span><?php esc_html_e( 'Buscando...', 'andreani-shipping' ); ?></span>
				</div>
				<div class="andreani-calc-results"></div>
				<div class="andreani-calc-error"></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_logo_svg() {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" class="andreani-calc-logo">
			<g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor" stroke="none">
				<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
			</g>
		</svg>';
	}

	private function get_current_product_id() {
		global $product;

		if ( $product && is_a( $product, 'WC_Product' ) ) {
			return $product->get_id();
		}

		global $post;
		if ( $post && 'product' === $post->post_type ) {
			return $post->ID;
		}

		return 0;
	}

	public function enqueue_assets() {
		if ( ! is_product() ) {
			return;
		}

		$this->do_enqueue_assets();
	}

	private function enqueue_assets_for_shortcode() {
		$this->do_enqueue_assets();
	}

	private function do_enqueue_assets() {
		if ( wp_script_is( 'andreani-cotizador', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'andreani-cotizador',
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/frontend-cotizador.css',
			array( Andreani_Core_Assets::HANDLE_BASE ),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'andreani-cotizador',
			ANDREANI_PLUGIN_URL . 'includes/assets/js/cotizador.js',
			array( 'jquery' ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'andreani-cotizador',
			'andreaniCotizador',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'andreani_calc_nonce' ),
				'i18n'      => array(
					'invalidPostcode' => __( 'Ingresa un código postal válido.', 'andreani-shipping' ),
					'noRates'         => __( 'No hay opciones de envío para este código postal.', 'andreani-shipping' ),
					'error'           => __( 'Error al calcular. Intenta nuevamente.', 'andreani-shipping' ),
					'free'            => __( 'Gratis', 'andreani-shipping' ),
				),
				'cacheTime' => 15 * 60 * 1000,
			)
		);
	}

	public function ajax_calculate_rates() {
		check_ajax_referer( 'andreani_calc_nonce', 'nonce' );

		$postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

		if ( empty( $postcode ) || ! Andreani_Postcode::is_valid( $postcode ) ) {
			wp_send_json_error( array(
				'message' => __( 'Código postal inválido. Debe ser de 4 dígitos o formato CPA (ej: C1425ABC).', 'andreani-shipping' ),
			) );
			return;
		}

		if ( empty( $product_id ) ) {
			wp_send_json_error( array(
				'message' => __( 'Producto no especificado.', 'andreani-shipping' ),
			) );
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array(
				'message' => __( 'Producto no encontrado.', 'andreani-shipping' ),
			) );
			return;
		}

		if ( $quantity < 1 ) {
			$quantity = 1;
		}

		$rates = $this->calculate_shipping_rates( $product, $postcode, $quantity );

		if ( is_wp_error( $rates ) ) {
			wp_send_json_error( array(
				'message' => $rates->get_error_message(),
			) );
			return;
		}

		if ( empty( $rates ) ) {
			wp_send_json_error( array(
				'message' => __( 'No hay opciones de envío disponibles para este código postal.', 'andreani-shipping' ),
			) );
			return;
		}

		wp_send_json_success( array(
			'rates'    => $rates,
			'postcode' => $postcode,
		) );
	}

	private function calculate_shipping_rates( $product, $postcode, $quantity ) {
		if ( ! $product->needs_shipping() ) {
			return new WP_Error( 'no_shipping', __( 'Este producto no requiere envío.', 'andreani-shipping' ) );
		}

		WC()->shipping()->reset_shipping();

		$package = $this->build_package( $product, $postcode, $quantity );

		$shipping_zone = WC_Shipping_Zones::get_zone_matching_package( $package );
		$shipping_methods = $shipping_zone->get_shipping_methods( true );

		if ( empty( $shipping_methods ) ) {
			return array();
		}

		$calculated_rates = array();

		foreach ( $shipping_methods as $method ) {
			if ( ANDREANI_SHIPPING_METHOD_ID !== $method->id ) {
				continue;
			}

			$method->calculate_shipping( $package );
			$method_rates = $method->rates;

			if ( ! empty( $method_rates ) ) {
				foreach ( $method_rates as $rate ) {
					$label = $rate->get_label();
					$label = preg_replace( '/\s*[-:]\s*¡?Envío gratis!?\s*/i', '', $label );
					$label = trim( $label );

					$calculated_rates[] = array(
						'id'       => $rate->get_id(),
						'label'    => $label,
						'cost'     => wc_price( $rate->get_cost() ),
						'cost_raw' => floatval( $rate->get_cost() ),
					);
				}
			}
		}

		usort( $calculated_rates, function ( $a, $b ) {
			return $a['cost_raw'] <=> $b['cost_raw'];
		} );

		return $calculated_rates;
	}

	private function build_package( $product, $postcode, $quantity ) {
		$product_price = floatval( $product->get_price() );
		$line_total = $product_price * $quantity;

		$contents = array(
			$product->get_id() => array(
				'product_id'        => $product->get_id(),
				'variation_id'      => 0,
				'variation'         => array(),
				'quantity'          => $quantity,
				'data'              => $product,
				'line_total'        => $line_total,
				'line_tax'          => 0,
				'line_subtotal'     => $line_total,
				'line_subtotal_tax' => 0,
			),
		);

		$package = array(
			'contents'        => $contents,
			'contents_cost'   => $line_total,
			'applied_coupons' => array(),
			'user'            => array(
				'ID' => get_current_user_id(),
			),
			'destination'     => array(
				'country'   => 'AR',
				'state'     => '',
				'postcode'  => $postcode,
				'city'      => '',
				'address'   => '',
				'address_1' => '',
				'address_2' => '',
			),
			'cart_subtotal'   => $line_total,
		);

		return $package;
	}
}
