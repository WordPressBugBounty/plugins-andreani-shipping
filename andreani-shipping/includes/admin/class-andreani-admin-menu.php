<?php
/**
 * Registra el menu de administracion de Andreani
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Admin_Menu {

	private static $instance = null;

	const MENU_SLUG = 'andreani-shipping';
	const CAPABILITY = 'manage_woocommerce';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'fix_submenu_urls' ), 999 );
	}

	public function register_menu() {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode(
			'<svg xmlns="http://www.w3.org/2000/svg" version="1.0" width="341.000000pt" height="341.000000pt" viewBox="0 0 341.000000 341.000000" preserveAspectRatio="xMidYMid meet">
				<g transform="translate(0.000000,341.000000) scale(0.100000,-0.100000)" fill="#000000" stroke="none">
				<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
				</g>
			</svg>'
		);

		add_menu_page(
			__( 'Andreani', 'andreani-shipping' ),
			__( 'Andreani', 'andreani-shipping' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_shipments_page' ),
			$icon_svg,
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Ver mis envíos', 'andreani-shipping' ),
			__( 'Ver mis envíos', 'andreani-shipping' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_shipments_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Ver mis productos', 'andreani-shipping' ),
			__( 'Ver mis productos', 'andreani-shipping' ),
			self::CAPABILITY,
			'andreani-products',
			array( $this, 'render_products_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Configuración', 'andreani-shipping' ),
			__( 'Configuración', 'andreani-shipping' ),
			self::CAPABILITY,
			'andreani-config',
			'__return_null'
		);
	}

	public function fix_submenu_urls() {
		global $submenu;

		if ( ! isset( $submenu[ self::MENU_SLUG ] ) ) {
			return;
		}

		$settings_url = $this->get_andreani_settings_url();

		foreach ( $submenu[ self::MENU_SLUG ] as $key => $item ) {
			if ( 'andreani-config' === $item[2] ) {
				$submenu[ self::MENU_SLUG ][ $key ][2] = $settings_url;
				break;
			}
		}
	}

	/**
	 * Busca la instancia activa del shipping method en zonas para construir su URL de settings.
	 */
	private function get_andreani_settings_url() {
		$fallback_url = admin_url( 'admin.php?page=wc-settings&tab=shipping' );

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $fallback_url;
		}

		$zones = WC_Shipping_Zones::get_zones();

		foreach ( $zones as $zone_data ) {
			$zone = new WC_Shipping_Zone( $zone_data['id'] );
			$methods = $zone->get_shipping_methods();

			foreach ( $methods as $method ) {
				if ( ANDREANI_SHIPPING_METHOD_ID === $method->id ) {
					return admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $method->instance_id );
				}
			}
		}

		$zone_zero = new WC_Shipping_Zone( 0 );
		$methods = $zone_zero->get_shipping_methods();

		foreach ( $methods as $method ) {
			if ( ANDREANI_SHIPPING_METHOD_ID === $method->id ) {
				return admin_url( 'admin.php?page=wc-settings&tab=shipping&instance_id=' . $method->instance_id );
			}
		}

		return $fallback_url;
	}

	public function render_shipments_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenes permisos para acceder a esta pagina.', 'andreani-shipping' ) );
		}

		$list_table = new Andreani_Shipments_List();
		$list_table->prepare_items();

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/shipments-page.php';
	}

	public function render_products_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenes permisos para acceder a esta pagina.', 'andreani-shipping' ) );
		}

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/products-page.php';
	}
}
