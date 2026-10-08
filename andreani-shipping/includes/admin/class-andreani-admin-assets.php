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
					Andreani_Core_Assets::HANDLE_C_DISPATCH,
					Andreani_Core_Assets::HANDLE_C_LOADER,
					Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
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
					Andreani_Core_Assets::HANDLE_C_DISPATCH,
					Andreani_Core_Assets::HANDLE_C_LOADER,
					Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
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
					Andreani_Core_Assets::HANDLE_C_LOADER,
					Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
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
					Andreani_Core_Assets::HANDLE_C_LOADER,
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
			array( 'jquery', Andreani_Core_Assets::HANDLE_LOADER, Andreani_Core_Assets::HANDLE_BOX_PREVIEW ),
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
			'nonce_preview_bultos' => wp_create_nonce( 'andreani_preview_bultos' ),
			'nonce_order_packing'  => wp_create_nonce( Andreani_Order_Packing::NONCE ),
			'nonce_sim_search'     => wp_create_nonce( 'andreani_sim_search' ),
			'nonce_simulate_cart'  => wp_create_nonce( 'andreani_simulate_cart' ),
			'nonce_origen_sucursales' => wp_create_nonce( Andreani_Origen_Ajax::NONCE_SUCURSALES ),
			'nonce_origen_default'    => wp_create_nonce( Andreani_Origen_Ajax::NONCE_DEFAULT ),
			'pyme_historial_url'   => ANDREANI_PYME_HISTORIAL_URL,
			'box_preview'          => class_exists( 'Andreani_Product_Bultos' )
				? Andreani_Product_Bultos::get_preview_config()
				: array(),
			'services_icons_url'   => ANDREANI_PLUGIN_URL . 'includes/assets/img/services/',
			'units'                => array(
				'weight'    => get_option( 'woocommerce_weight_unit', 'kg' ),
				'dimension' => get_option( 'woocommerce_dimension_unit', 'cm' ),
			),
			'i18n'                => array(
				'retry_loading'      => __( 'Reintentando el alta…', 'andreani-shipping' ),
				'retry_success'      => __( 'Envio reintentado correctamente.', 'andreani-shipping' ),
				'retry_error'        => __( 'Error al reintentar. Intenta nuevamente.', 'andreani-shipping' ),
				'label_loading'      => __( 'Preparando tus etiquetas…', 'andreani-shipping' ),
				'label_success'      => __( 'Etiqueta descargada correctamente.', 'andreani-shipping' ),
				'label_error'        => __( 'Error al obtener la etiqueta.', 'andreani-shipping' ),
				'copy_success'       => __( 'Copiado!', 'andreani-shipping' ),
				'bulto_name_label'       => __( 'Referencia del bulto', 'andreani-shipping' ),
				'bulto_name_placeholder' => __( 'Ej. Base de somier', 'andreani-shipping' ),
				'network_error'      => __( 'Error de red. Intenta nuevamente.', 'andreani-shipping' ),
				'bulk_pay_label'           => __( 'Pagar', 'andreani-shipping' ),
				'bulk_labels_label'        => __( 'Descargar etiquetas', 'andreani-shipping' ),
				'bulk_labels_loading'      => __( 'Preparando tus etiquetas…', 'andreani-shipping' ),
				'bulk_labels_error'        => __( 'Error al descargar las etiquetas.', 'andreani-shipping' ),
				'bulk_labels_none'         => __( 'Ninguna de las órdenes seleccionadas tiene seguimiento todavía.', 'andreani-shipping' ),
				'export_loading'           => __( 'Preparando el archivo…', 'andreani-shipping' ),
				'export_success'           => __( 'Exportación completada.', 'andreani-shipping' ),
				'export_error'             => __( 'Error al exportar.', 'andreani-shipping' ),
				'table_error'              => __( 'Error al cargar los envíos. Intentá de nuevo.', 'andreani-shipping' ),
				'loader_shipments_phrases' => Andreani_Admin_Loader::shipments_phrases(),
				'loader_shipments_update'  => __( 'Actualizando tus envíos…', 'andreani-shipping' ),
				'loader_products_phrases'  => Andreani_Admin_Loader::products_phrases(),
				'loader_products_update'   => __( 'Buscando productos…', 'andreani-shipping' ),
				'loader_print'             => __( 'Cargando la configuración de impresión…', 'andreani-shipping' ),
				'loader_contracts'         => __( 'Actualizando tus contratos…', 'andreani-shipping' ),
				'table_retry'              => __( 'Reintentar', 'andreani-shipping' ),
				'print_load_error'         => __( 'No se pudo cargar la configuración de impresión.', 'andreani-shipping' ),
				'print_save_loading'       => __( 'Guardando…', 'andreani-shipping' ),
				'print_save_success'       => __( 'Configuración de impresión guardada.', 'andreani-shipping' ),
				'print_save_error'         => __( 'No se pudo guardar la configuración de impresión.', 'andreani-shipping' ),
				'products_error'           => __( 'Error al cargar los productos. Intentá de nuevo.', 'andreani-shipping' ),
				'save_dims_loading'        => __( 'Guardando…', 'andreani-shipping' ),
				'save_dims_success'        => __( 'Dimensiones guardadas.', 'andreani-shipping' ),
				'save_dims_error'          => __( 'Error al guardar las dimensiones.', 'andreani-shipping' ),
				'quote_loading'            => __( 'Consultando tarifas de Andreani…', 'andreani-shipping' ),
				'quote_error'              => __( 'Error al cotizar.', 'andreani-shipping' ),
				'rate_home'                => __( 'A domicilio', 'andreani-shipping' ),
				'rate_branch'              => __( 'A sucursal', 'andreani-shipping' ),
				'rate_today'               => __( 'Llega hoy', 'andreani-shipping' ),
				'rate_bigger'              => __( 'Bigger', 'andreani-shipping' ),
				'rate_cheapest'            => __( 'Más económico', 'andreani-shipping' ),
				'editor_saved'             => __( 'Producto actualizado.', 'andreani-shipping' ),
				'sim_remove'               => __( 'Restar una unidad', 'andreani-shipping' ),
				'sim_add'                  => __( 'Sumar una unidad', 'andreani-shipping' ),
				'sim_remove_line'          => __( 'Quitar del carrito', 'andreani-shipping' ),
				'sim_missing'              => __( 'Faltan medidas', 'andreani-shipping' ),
				'sim_no_results'           => __( 'No encontramos productos.', 'andreani-shipping' ),
				/* translators: %d: máximo de resultados */
				'sim_limit'                => __( 'Mostrando los primeros %d — refiná la búsqueda', 'andreani-shipping' ),
				'sim_skipped'              => __( 'No se incluyen en la simulación por no tener medidas completas:', 'andreani-shipping' ),
				'sim_empty_preview'        => __( 'Agregá productos para ver cómo viajan.', 'andreani-shipping' ),
				'packing_loading'          => __( 'Armando la caja…', 'andreani-shipping' ),
				'packing_error'            => __( 'No pudimos armar la sugerencia. Probá de nuevo.', 'andreani-shipping' ),
				'packing_stage'            => __( 'Cómo se acomoda el envío', 'andreani-shipping' ),
				/* translators: 1: largo, 2: ancho, 3: alto, en cm */
				'packing_use_box'          => __( 'Usá una caja de aprox. %1$s × %2$s × %3$s cm', 'andreani-shipping' ),
				/* translators: %s: peso total */
				'packing_fits_one'         => __( 'Entra 1 producto · Peso total %s', 'andreani-shipping' ),
				/* translators: 1: cantidad de productos, 2: peso total */
				'packing_fits_many'        => __( 'Entran %1$s productos · Peso total %2$s', 'andreani-shipping' ),
				'packing_big_one'          => __( 'Este envío es 1 caja, con su etiqueta', 'andreani-shipping' ),
				/* translators: %s: cantidad de cajas */
				'packing_big_title'        => __( 'Este envío son %s cajas, cada una con su etiqueta', 'andreani-shipping' ),
				'packing_box'              => __( 'Caja', 'andreani-shipping' ),
				/* translators: %s: unidades en la pila */
				'packing_units'            => __( '%s unidades', 'andreani-shipping' ),
				'packing_hint'             => __( 'Es una sugerencia según las medidas que cargaste en tus productos.', 'andreani-shipping' ),
				'packing_empty'            => __( 'Este pedido no tiene productos para enviar.', 'andreani-shipping' ),
				/* translators: %s: nombre del producto */
				'packing_missing'          => __( 'No podemos sugerir una caja: %s no tiene medidas', 'andreani-shipping' ),
				'packing_complete'         => __( 'Completar', 'andreani-shipping' ),
				'origen_vacio'             => __( 'Cargá tu código postal de origen para ver las sucursales disponibles.', 'andreani-shipping' ),
				'origen_cp_invalido'       => __( 'El código postal no tiene un formato válido (ej: 1425 o C1425ABC).', 'andreani-shipping' ),
				'origen_cargando'          => __( 'Buscando sucursales…', 'andreani-shipping' ),
				'origen_sin_resultados'    => __( 'No encontramos sucursales habilitadas como origen para ese código postal.', 'andreani-shipping' ),
				'origen_error'             => __( 'No pudimos traer las sucursales. Probá de nuevo en unos minutos.', 'andreani-shipping' ),
				'origen_auto'              => __( 'Por defecto — la asigna Andreani por tu código postal', 'andreani-shipping' ),
				'origen_auto_tag'          => __( 'Por defecto', 'andreani-shipping' ),
				'origen_auto_desc'         => __( 'Es la que Andreani asigna para tu código postal. Si cambia, se actualiza sola.', 'andreani-shipping' ),
				/* translators: %s: nombre de la sucursal que Andreani asigna hoy. */
				'origen_default'           => __( 'hoy tus envíos salen desde %s', 'andreani-shipping' ),
				/* translators: %s: código postal cargado en la tienda. */
				'origen_cp_traido'         => __( 'También actualizamos tu código postal de origen con el de la tienda (%s). Si despachás desde otro lugar, corregilo antes de guardar.', 'andreani-shipping' ),
				'dispatch'                 => class_exists( 'Andreani_Product_Bultos' )
					? Andreani_Product_Bultos::get_ui_strings()
					: array(),
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
