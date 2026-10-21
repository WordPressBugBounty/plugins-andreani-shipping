<?php
/**
 * Pantalla "Promociones de envío": alta, edición, pausa y baja de los descuentos y
 * recargos que la tienda aplica al precio de las tarifas de Andreani.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Promotions_Page {

	private static $instance = null;

	const CAPABILITY    = 'manage_woocommerce';
	const NONCE         = 'andreani_promotions';
	const ACTION_SAVE   = 'andreani_save_promotion';
	const ACTION_TOGGLE = 'andreani_toggle_promotion';
	const ACTION_DELETE = 'andreani_delete_promotion';
	const SCRIPT        = 'andreani-promotions-page';
	const STYLE         = 'andreani-promotions-page';
	const LEAFLET_STYLE = 'andreani-leaflet';
	const LEAFLET       = 'andreani-leaflet';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_' . self::ACTION_TOGGLE, array( $this, 'handle_toggle' ) );
		add_action( 'wp_ajax_' . self::ACTION_DELETE, array( $this, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( 'Andreani_Shipping_Promotions', 'hide_internal_item_meta' ) );
	}

	private function authorize() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}
	}

	private function reply( array $result ) {
		if ( null !== $result['error'] ) {
			wp_send_json_error( array( 'message' => $result['error'] ), 422 );
		}

		wp_send_json_success( array( 'promotions' => Andreani_Shipping_Promotions::for_display() ) );
	}

	public function handle_save() {
		$this->authorize();

		$json  = isset( $_POST['promotion'] ) ? wp_unslash( $_POST['promotion'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$input = json_decode( (string) $json, true );

		if ( ! is_array( $input ) ) {
			wp_send_json_error( array( 'message' => __( 'No pudimos guardar la promoción. Probá de nuevo.', 'andreani-shipping' ) ), 400 );
		}

		$this->reply( Andreani_Shipping_Promotions::save( $input ) );
	}

	public function handle_toggle() {
		$this->authorize();

		$id     = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
		$active = isset( $_POST['active'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['active'] ) );

		$this->reply( Andreani_Shipping_Promotions::set_active( $id, $active ) );
	}

	public function handle_delete() {
		$this->authorize();

		$id = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';

		if ( ! Andreani_Shipping_Promotions::delete( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'No encontramos esa promoción.', 'andreani-shipping' ) ), 404 );
		}

		wp_send_json_success( array( 'promotions' => Andreani_Shipping_Promotions::for_display() ) );
	}

	private function is_promotions_page( $hook ) {
		return 'andreani_page_' . Andreani_Admin_Menu::PROMOTIONS_SLUG === $hook;
	}

	public function enqueue_assets( $hook ) {
		if ( ! $this->is_promotions_page( $hook ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_register_style(
			self::LEAFLET_STYLE,
			ANDREANI_PLUGIN_URL . 'includes/assets/vendor/leaflet/leaflet.css',
			array(),
			'1.9.4'
		);

		wp_register_script(
			self::LEAFLET,
			ANDREANI_PLUGIN_URL . 'includes/assets/vendor/leaflet/leaflet.js',
			array(),
			'1.9.4',
			true
		);

		wp_enqueue_style(
			self::STYLE,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-promotions.css',
			array(
				'andreani-core-base',
				Andreani_Core_Assets::HANDLE_C_BADGE,
				Andreani_Core_Assets::HANDLE_C_BUTTON,
				Andreani_Core_Assets::HANDLE_C_MODAL,
				Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
				self::LEAFLET_STYLE,
			),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT,
			ANDREANI_PLUGIN_URL . 'includes/assets/js/andreani-promotions-page.js',
			array( 'jquery', self::LEAFLET ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT,
			'AndreaniPromotionsPage',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( self::NONCE ),
				'save_action'    => self::ACTION_SAVE,
				'toggle_action'  => self::ACTION_TOGGLE,
				'delete_action'  => self::ACTION_DELETE,
				'name_max'       => Andreani_Shipping_Promotions::NAME_MAX_LENGTH,
				'max'            => Andreani_Shipping_Promotions::MAX,
				'province_count' => count( Andreani_Shipping_Promotions::provinces() ),
				'provinces'      => Andreani_Shipping_Promotions::provinces(),
				'regions'        => self::regions(),
				'geojson_url'    => ANDREANI_PLUGIN_URL . 'includes/assets/data/ar-provincias.geojson',
				'promotions'     => Andreani_Shipping_Promotions::for_display(),
				'i18n'           => $this->strings(),
			)
		);
	}

	/**
	 * @param string $service Servicio de las promociones (estandar, sucursal, llega-hoy, bigger).
	 * @return array{off:string,on:string} Ícono del servicio y su variante roja para el estado elegido ('' si no existe).
	 */
	public static function service_icons( $service ) {
		$file = 'sucursal' === $service ? 'en-sucursal' : $service;
		$red  = $file . '-rojo';

		return array(
			'off' => Andreani_Product_Bultos::service_icon_url( $file ),
			'on'  => is_readable( ANDREANI_PLUGIN_DIR . 'includes/assets/img/services/' . $red . '.svg' ) ? Andreani_Product_Bultos::service_icon_url( $red ) : '',
		);
	}

	/**
	 * @return array<int,array{name:string,codes:string[]}> Regiones en el orden de la lista.
	 */
	public static function regions() {
		$regions = array();

		foreach ( Andreani_Shipping_Promotions::province_groups() as $name => $codes ) {
			$regions[] = array(
				'name'  => $name,
				'codes' => $codes,
			);
		}

		return $regions;
	}

	private function strings() {
		return array(
			'modal_new'      => __( 'Nueva promoción', 'andreani-shipping' ),
			'modal_edit'     => __( 'Editar promoción', 'andreani-shipping' ),
			'edit'           => __( 'Editar', 'andreani-shipping' ),
			'pause'          => __( 'Pausar', 'andreani-shipping' ),
			'resume'         => __( 'Activar', 'andreani-shipping' ),
			'remove'         => __( 'Borrar', 'andreani-shipping' ),
			'cancel'         => __( 'Cancelar', 'andreani-shipping' ),
			'confirm'        => __( '¿Borrar esta promoción? Los envíos que ya se vendieron no cambian.', 'andreani-shipping' ),
			'count_one'      => __( '1 promoción', 'andreani-shipping' ),
			/* translators: %s: cantidad de promociones */
			'count_many'     => __( '%s promociones', 'andreani-shipping' ),
			/* translators: %s: máximo de promociones */
			'max'            => __( 'Llegaste al máximo de %s promociones.', 'andreani-shipping' ),
			'save_error'     => __( 'No pudimos guardar la promoción. Probá de nuevo.', 'andreani-shipping' ),
			'name_required'  => __( 'Poné un nombre para la promoción.', 'andreani-shipping' ),
			'value_required' => __( 'El valor tiene que ser mayor a 0.', 'andreani-shipping' ),
			'percent_max'    => __( 'Un descuento no puede ser de más del 100 %.', 'andreani-shipping' ),
			'services_none'  => __( 'Elegí al menos un servicio.', 'andreani-shipping' ),
			'provinces_none' => __( 'Pintá en el mapa al menos una provincia, o elegí «Todo el país».', 'andreani-shipping' ),
			'dates_order'    => __( 'La fecha de fin no puede ser anterior a la de inicio.', 'andreani-shipping' ),
			'all_country'    => __( 'Todo el país', 'andreani-shipping' ),
			'provinces_help' => __( 'Pintá en el mapa las provincias donde aplica.', 'andreani-shipping' ),
			'none'           => __( 'Ninguna provincia', 'andreani-shipping' ),
			'all_detail'     => __( 'Todas las provincias, incluida CABA.', 'andreani-shipping' ),
			'province_one'   => __( '1 provincia', 'andreani-shipping' ),
			/* translators: %s: cantidad de provincias */
			'province_many'  => __( '%s provincias', 'andreani-shipping' ),
			'map_label'      => __( 'Mapa de provincias: pintá para elegir', 'andreani-shipping' ),
			'caba'           => __( 'CABA', 'andreani-shipping' ),
			'example_empty'  => __( 'Poné un valor para ver un ejemplo.', 'andreani-shipping' ),
			/* translators: 1: precio original, 2: precio con la promoción */
			'example'        => __( 'Un envío de %1$s queda en %2$s.', 'andreani-shipping' ),
		);
	}
}
