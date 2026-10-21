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
	const BOXES_SLUG = 'andreani-boxes';
	const PROMOTIONS_SLUG = 'andreani-promotions';

	public static function boxes_url() {
		return admin_url( 'admin.php?page=' . self::BOXES_SLUG );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 20 );
		add_action( 'admin_menu', array( $this, 'fix_submenu_urls' ), 999 );
		add_filter( 'submenu_file', array( $this, 'highlight_settings_submenu' ), 999 );
		add_action( 'adminmenu', array( $this, 'print_settings_menu_highlight' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ANDREANI_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
	}

	/**
	 * @param string[] $links Links de la fila del plugin en Plugins.
	 * @return string[] Los mismos links con "Configuración" al principio.
	 */
	public function plugin_action_links( $links ) {
		$settings = '<a href="' . esc_url( $this->get_andreani_settings_url() ) . '">' . esc_html__( 'Configuración', 'andreani-shipping' ) . '</a>';

		return array_merge( array( 'settings' => $settings ), (array) $links );
	}

	public function is_andreani_settings_screen() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page        = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$tab         = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		$instance_id = isset( $_GET['instance_id'] ) ? absint( $_GET['instance_id'] ) : 0;
		// phpcs:enable

		if ( 'wc-settings' !== $page || 'shipping' !== $tab || ! $instance_id || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return false;
		}

		$method = WC_Shipping_Zones::get_shipping_method( $instance_id );

		return $method && ANDREANI_SHIPPING_METHOD_ID === $method->id;
	}

	public function highlight_settings_submenu( $submenu_file ) {
		return $this->is_andreani_settings_screen() ? $this->get_andreani_settings_url() : $submenu_file;
	}

	public function print_settings_menu_highlight() {
		if ( ! $this->is_andreani_settings_screen() ) {
			return;
		}

		$script = "(function(){var o=document.getElementById('toplevel_page_woocommerce'),a=document.getElementById('toplevel_page_" . self::MENU_SLUG . "');if(!a){return;}[o,a].forEach(function(li){if(!li){return;}var on=li===a;li.classList.toggle('wp-has-current-submenu',on);li.classList.toggle('wp-menu-open',on);li.classList.toggle('wp-not-current-submenu',!on);var l=li.querySelector(':scope > a');if(l){l.classList.toggle('wp-has-current-submenu',on);l.classList.toggle('wp-not-current-submenu',!on);}});})();";

		wp_print_inline_script_tag( $script );
	}

	public function register_menu() {
		$icon_svg = 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( ANDREANI_PLUGIN_DIR . 'includes/assets/img/andreani-isotipo.svg' ) );

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
			__( 'Mis productos', 'andreani-shipping' ),
			__( 'Mis productos', 'andreani-shipping' ),
			self::CAPABILITY,
			'andreani-products',
			array( $this, 'render_products_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Paquetes guardados', 'andreani-shipping' ),
			__( 'Paquetes guardados', 'andreani-shipping' ),
			self::CAPABILITY,
			self::BOXES_SLUG,
			array( $this, 'render_boxes_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Promociones de envío', 'andreani-shipping' ),
			__( 'Promociones de envío', 'andreani-shipping' ),
			self::CAPABILITY,
			self::PROMOTIONS_SLUG,
			array( $this, 'render_promotions_page' )
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
	public function get_andreani_settings_url() {
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

	public function render_boxes_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenes permisos para acceder a esta pagina.', 'andreani-shipping' ) );
		}

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/boxes-page.php';
	}

	public function render_promotions_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenes permisos para acceder a esta pagina.', 'andreani-shipping' ) );
		}

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/promotions-page.php';
	}
}
