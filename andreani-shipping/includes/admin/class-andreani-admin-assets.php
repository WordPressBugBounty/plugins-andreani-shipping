<?php
/**
 * Maneja los assets de admin para Andreani
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Admin_Assets {

	private static $instance = null;

	const HANDLE = 'andreani-admin';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'sync_post_save_state' ), 99 );
	}

	public function enqueue_assets( $hook ) {
		if ( ! $this->user_can_manage_orders() ) {
			return;
		}

		if ( ! $this->should_load_assets( $hook ) ) {
			return;
		}

		$this->enqueue_styles( $hook );
		$this->enqueue_scripts();
	}

	private function should_load_assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $this->is_andreani_page( $hook ) ) {
			return true;
		}

		if ( $this->is_andreani_products_page( $hook ) ) {
			return true;
		}

		if ( $this->is_wc_settings_page( $screen ) ) {
			return true;
		}

		if ( $this->is_order_page( $hook, $screen ) ) {
			return true;
		}

		return false;
	}

	private function is_andreani_page( $hook ) {
		return 'toplevel_page_andreani-shipping' === $hook;
	}

	private function is_andreani_products_page( $hook ) {
		return 'andreani_page_andreani-products' === $hook;
	}

	private function is_wc_settings_page( $screen ) {
		return $screen && 'woocommerce_page_wc-settings' === $screen->id;
	}

	private function is_order_page( $hook, $screen ) {
		if ( in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			if ( $screen && isset( $screen->post_type ) && 'shop_order' === $screen->post_type ) {
				return true;
			}
		}

		if ( $screen ) {
			if ( strpos( $screen->id, 'woocommerce_page_wc-orders' ) !== false ) {
				return true;
			}
			if ( strpos( $screen->id, 'shop_order' ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Encolar el CSS de la vista que corresponde al hook/screen actual.
	 *
	 * Cada pantalla del admin tiene su propio archivo CSS sobre la capa core/components
	 * compartida. Solo una vista por request:
	 *   - toplevel del plugin   -> views/admin-shipments.css
	 *   - WC Settings           -> views/admin-settings.css
	 *   - Pantalla de pedido    -> views/admin-metabox.css
	 */
	private function enqueue_styles( $hook = '' ) {
		if ( $this->is_andreani_page( $hook ) ) {
			wp_enqueue_style(
				'andreani-shipments-table',
				ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-shipments.css',
				array(
					'andreani-core-base',
					Andreani_Core_Assets::HANDLE_C_BADGE,
					Andreani_Core_Assets::HANDLE_C_BUTTON,
					Andreani_Core_Assets::HANDLE_C_STATUS,
					Andreani_Core_Assets::HANDLE_C_MODAL,
					Andreani_Core_Assets::HANDLE_C_TABS,
				),
				ANDREANI_PLUGIN_VERSION
			);
			return;
		}

		if ( $this->is_andreani_products_page( $hook ) ) {
			// Base: reusa la grilla de envíos (toolbar, loader, per-page, chips, tabla).
			wp_enqueue_style(
				'andreani-shipments-table',
				ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-shipments.css',
				array(
					'andreani-core-base',
					Andreani_Core_Assets::HANDLE_C_BADGE,
					Andreani_Core_Assets::HANDLE_C_BUTTON,
					Andreani_Core_Assets::HANDLE_C_STATUS,
					Andreani_Core_Assets::HANDLE_C_MODAL,
					Andreani_Core_Assets::HANDLE_C_TABS,
				),
				ANDREANI_PLUGIN_VERSION
			);
			// Específico de productos (sobre la base de envíos).
			wp_enqueue_style(
				'andreani-products-table',
				ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-products.css',
				array( 'andreani-shipments-table' ),
				ANDREANI_PLUGIN_VERSION
			);
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( $this->is_wc_settings_page( $screen ) ) {
			wp_enqueue_style(
				'andreani-admin-settings',
				ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-settings.css',
				array(
					'andreani-core-base',
					Andreani_Core_Assets::HANDLE_C_BUTTON,
					Andreani_Core_Assets::HANDLE_C_CARD,
					Andreani_Core_Assets::HANDLE_C_TABS,
					Andreani_Core_Assets::HANDLE_C_MODAL,
				),
				ANDREANI_PLUGIN_VERSION
			);
			return;
		}

		if ( $this->is_order_page( $hook, $screen ) ) {
			wp_enqueue_style(
				'andreani-admin-metabox',
				ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-metabox.css',
				array(
					'andreani-core-base',
					Andreani_Core_Assets::HANDLE_C_BADGE,
					Andreani_Core_Assets::HANDLE_C_BUTTON,
					Andreani_Core_Assets::HANDLE_C_STATUS,
				),
				ANDREANI_PLUGIN_VERSION
			);
			return;
		}
	}

	private function enqueue_scripts() {
		if ( wp_script_is( self::HANDLE, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			ANDREANI_PLUGIN_URL . 'includes/assets/js/admin.js',
			array( 'jquery' ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script( self::HANDLE, 'andreani_admin', array_merge(
			$this->get_post_save_state(),
			array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'nonce_retry'         => wp_create_nonce( 'andreani_retry_order' ),
			'nonce_etiqueta'      => wp_create_nonce( 'andreani_get_etiqueta' ),
			'nonce_bulk_etiquetas' => wp_create_nonce( 'andreani_bulk_etiquetas' ),
			'nonce_export'        => wp_create_nonce( 'andreani_export_shipments' ),
			'nonce_load_table'    => wp_create_nonce( 'andreani_load_shipments' ),
			'nonce_load_detail'   => wp_create_nonce( 'andreani_load_detail' ),
			'nonce_print_get'     => wp_create_nonce( 'andreani_get_print_settings' ),
			'nonce_print_save'    => wp_create_nonce( 'andreani_save_print_settings' ),
			'nonce_products_table' => wp_create_nonce( 'andreani_products_table' ),
			'nonce_save_dims'      => wp_create_nonce( 'andreani_save_product_dims' ),
			'nonce_test_quote'     => wp_create_nonce( 'andreani_test_quote' ),
			'nonce_toggle_sync'    => wp_create_nonce( 'andreani_toggle_tracking_sync' ),
			'nonce_origen_sucursales' => wp_create_nonce( Andreani_Origen_Ajax::NONCE_SUCURSALES ),
			'nonce_origen_default'    => wp_create_nonce( Andreani_Origen_Ajax::NONCE_DEFAULT ),
			'tracking_sync_enabled' => class_exists( 'Andreani_Tracking_Sync' ) ? Andreani_Tracking_Sync::is_enabled() : true,
			'pyme_historial_url'   => ANDREANI_PYME_HISTORIAL_URL,
			'bigger_thresholds'    => class_exists( 'Andreani_Product_Bultos' )
				? Andreani_Product_Bultos::get_bigger_thresholds()
				: array( 'weight' => 50, 'sum_sides' => 300, 'max_side' => 165 ),
			'logo_path'           => 'M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z',
			'i18n'                => array(
				'retry_loading'      => __( 'Reintentando...', 'andreani-shipping' ),
				'retry_success'      => __( 'Envio reintentado correctamente.', 'andreani-shipping' ),
				'retry_error'        => __( 'Error al reintentar. Intenta nuevamente.', 'andreani-shipping' ),
				'label_loading'      => __( 'Generando etiqueta...', 'andreani-shipping' ),
				'label_success'      => __( 'Etiqueta descargada correctamente.', 'andreani-shipping' ),
				'label_error'        => __( 'Error al obtener la etiqueta.', 'andreani-shipping' ),
				'copy_success'       => __( 'Copiado!', 'andreani-shipping' ),
				'network_error'      => __( 'Error de red. Intenta nuevamente.', 'andreani-shipping' ),
				'bulk_pay_label'           => __( 'Pagar', 'andreani-shipping' ),
				'bulk_labels_label'        => __( 'Descargar etiquetas', 'andreani-shipping' ),
				'bulk_labels_loading'      => __( 'Generando etiquetas...', 'andreani-shipping' ),
				'bulk_labels_error'        => __( 'Error al descargar las etiquetas.', 'andreani-shipping' ),
				'bulk_labels_none'         => __( 'Ninguna de las órdenes seleccionadas tiene seguimiento todavía.', 'andreani-shipping' ),
				'export_loading'           => __( 'Exportando...', 'andreani-shipping' ),
				'export_success'           => __( 'Exportación completada.', 'andreani-shipping' ),
				'export_error'             => __( 'Error al exportar.', 'andreani-shipping' ),
				'table_loading'            => __( 'Cargando envíos...', 'andreani-shipping' ),
				'table_error'              => __( 'Error al cargar los envíos. Intentá de nuevo.', 'andreani-shipping' ),
				'table_refreshing'         => __( 'Actualizando...', 'andreani-shipping' ),
				'table_retry'              => __( 'Reintentar', 'andreani-shipping' ),
				'print_load_error'         => __( 'No se pudo cargar la configuración de impresión.', 'andreani-shipping' ),
				'print_save_loading'       => __( 'Guardando...', 'andreani-shipping' ),
				'print_save_success'       => __( 'Configuración de impresión guardada.', 'andreani-shipping' ),
				'print_save_error'         => __( 'No se pudo guardar la configuración de impresión.', 'andreani-shipping' ),
				'products_loading'         => __( 'Cargando productos...', 'andreani-shipping' ),
				'products_error'           => __( 'Error al cargar los productos. Intentá de nuevo.', 'andreani-shipping' ),
				'save_dims_loading'        => __( 'Guardando...', 'andreani-shipping' ),
				'save_dims_success'        => __( 'Dimensiones guardadas.', 'andreani-shipping' ),
				'save_dims_error'          => __( 'Error al guardar las dimensiones.', 'andreani-shipping' ),
				'quote_loading'            => __( 'Cotizando...', 'andreani-shipping' ),
				'quote_error'              => __( 'Error al cotizar.', 'andreani-shipping' ),
				'sync_on'                  => __( 'Seguimiento automático activado.', 'andreani-shipping' ),
				'sync_off'                 => __( 'Seguimiento automático desactivado.', 'andreani-shipping' ),
				'sync_toggle_error'        => __( 'No se pudo cambiar la sincronización. Intentá de nuevo.', 'andreani-shipping' ),
				'origen_vacio'             => __( 'Cargá tu código postal de origen para ver las sucursales disponibles.', 'andreani-shipping' ),
				'origen_cp_invalido'       => __( 'El código postal no tiene un formato válido (ej: 1425 o C1425ABC).', 'andreani-shipping' ),
				'origen_cargando'          => __( 'Buscando sucursales...', 'andreani-shipping' ),
				'origen_sin_resultados'    => __( 'No encontramos sucursales habilitadas como origen para ese código postal.', 'andreani-shipping' ),
				'origen_error'             => __( 'No pudimos traer las sucursales. Probá de nuevo en unos minutos.', 'andreani-shipping' ),
				'origen_auto'              => __( 'Por defecto — la asigna Andreani por tu código postal', 'andreani-shipping' ),
				'origen_auto_tag'          => __( 'Por defecto', 'andreani-shipping' ),
				'origen_auto_desc'         => __( 'Es la que Andreani asigna para tu código postal. Si cambia, se actualiza sola.', 'andreani-shipping' ),
				/* translators: %s: nombre de la sucursal que Andreani asigna hoy. */
				'origen_default'           => __( 'hoy tus envíos salen desde %s', 'andreani-shipping' ),
				/* translators: %s: código postal cargado en la tienda. */
				'origen_cp_traido'         => __( 'También actualizamos tu código postal de origen con el de la tienda (%s). Si despachás desde otro lugar, corregilo antes de guardar.', 'andreani-shipping' ),
			),
		) ) );
	}

	/**
	 * Estado dinámico que puede cambiar durante process_admin_options().
	 * Para agregar validaciones post-save, sumar entradas al array devuelto.
	 *
	 * @return array
	 */
	private function get_post_save_state() {
		return array(
			'cp_origen_valid' => get_option( 'andreani_cp_origen_valid', '' ),
			'cp_origen_saved' => Andreani_Settings_Service::get( 'cp_origen', '' ),
		);
	}

	/**
	 * Re-leer el estado dinámico después del save de WooCommerce.
	 *
	 * `wp_localize_script` corre en `admin_enqueue_scripts`, antes de que WC ejecute
	 * `process_admin_options()`. Sin este sync, el JS recibe valores pre-save. Este
	 * footer-script sobreescribe los valores antes de `$(document).ready()`.
	 */
	public function sync_post_save_state() {
		if ( ! wp_script_is( self::HANDLE, 'done' ) ) {
			return;
		}

		$state = $this->get_post_save_state();
		?>
		<script>
		if (typeof andreani_admin !== 'undefined') {
			<?php foreach ( $state as $key => $value ) : ?>
			andreani_admin[<?php echo wp_json_encode( $key ); ?>] = <?php echo wp_json_encode( $value ); ?>;
			<?php endforeach; ?>
		}
		</script>
		<?php
	}

	private function user_can_manage_orders() {
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}
}
