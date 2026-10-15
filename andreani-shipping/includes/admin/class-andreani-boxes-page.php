<?php
/**
 * Pantalla "Paquetes guardados": alta, edición y baja de las cajas de la tienda, y
 * cuántos productos del catálogo eligen cada una.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Boxes_Page {

	private static $instance = null;

	const CAPABILITY    = 'manage_woocommerce';
	const NONCE         = 'andreani_boxes';
	const ACTION_SAVE   = 'andreani_save_boxes';
	const ACTION_FIT    = 'andreani_boxes_fit';
	const ACTION_ADD    = 'andreani_add_package';
	const SCRIPT        = 'andreani-boxes-page';
	const STYLE         = 'andreani-boxes-page';
	const FIT_TRANSIENT = 'andreani_boxes_fit_counts';
	const FIT_TTL       = DAY_IN_SECONDS;
	const FIT_PRODUCTS  = 500;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'wp_ajax_' . self::ACTION_FIT, array( $this, 'handle_fit' ) );
		add_action( 'wp_ajax_' . self::ACTION_ADD, array( $this, 'handle_add' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		foreach ( array( 'woocommerce_new_product', 'woocommerce_update_product', 'woocommerce_new_product_variation', 'woocommerce_update_product_variation', 'trashed_post', 'untrashed_post', 'deleted_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'forget_fit' ) );
		}

		foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'forget_fit_on_meta' ), 10, 3 );
		}
	}

	public static function forget_fit() {
		delete_transient( self::FIT_TRANSIENT );
	}

	public static function forget_fit_on_meta( $meta_id, $post_id, $meta_key ) {
		if ( in_array( $meta_key, Andreani_Products_Stats::watched_meta_keys(), true ) ) {
			self::forget_fit();
		}
	}

	/**
	 * @param int[] $product_ids Productos y variaciones a evaluar.
	 * @return int[] Por posición de caja guardada, cuántos productos de 1 unidad la eligen.
	 */
	public static function count_fits( array $product_ids ) {
		$boxes  = Andreani_Store_Boxes::all();
		$counts = array_fill( 0, count( $boxes ), 0 );

		if ( empty( $boxes ) ) {
			return array();
		}

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$advice = Andreani_Package_Builder::store_box_advice( $product, 1 );
			$index  = ( $advice && $advice['box'] ) ? array_search( $advice['box'], $boxes, true ) : false;

			if ( false !== $index ) {
				$counts[ $index ]++;
			}
		}

		return $counts;
	}

	/**
	 * @return array{counts:int[],evaluated:int}
	 */
	public static function fit_summary() {
		$hash   = Andreani_Store_Boxes::hash();
		$cached = get_transient( self::FIT_TRANSIENT );

		if ( is_array( $cached ) && isset( $cached['hash'], $cached['summary'] ) && $cached['hash'] === $hash ) {
			return $cached['summary'];
		}

		$ids     = '' === $hash ? array() : Andreani_Products_List::query_ids( array( 'filter' => Andreani_Products_Stats::FILTER_READY, 'limit' => self::FIT_PRODUCTS ) );
		$summary = array(
			'counts'    => self::count_fits( $ids ),
			'evaluated' => count( $ids ),
		);

		set_transient( self::FIT_TRANSIENT, array( 'hash' => $hash, 'summary' => $summary ), self::FIT_TTL );

		return $summary;
	}

	public function handle_save() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$json = isset( $_POST['boxes'] ) ? wp_unslash( $_POST['boxes'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( json_decode( (string) $json, true ) ) ) {
			wp_send_json_error( array( 'message' => __( 'No pudimos guardar las cajas. Probá de nuevo.', 'andreani-shipping' ) ), 400 );
		}

		$duplicate = Andreani_Store_Boxes::duplicate_name( $json );

		if ( '' !== $duplicate ) {
			/* translators: %s: nombre de la caja ya guardada */
			wp_send_json_error( array( 'message' => sprintf( __( 'Ya tenés «%s» con estas medidas.', 'andreani-shipping' ), $duplicate ) ), 409 );
		}

		if ( Andreani_Store_Boxes::save_from_json( $json ) ) {
			Andreani_Cache_Service::clear_cotizaciones();
			self::forget_fit();
			do_action( 'andreani_store_boxes_changed' );
		}

		wp_send_json_success( array( 'boxes' => Andreani_Store_Boxes::for_display() ) );
	}

	public function handle_add() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$json   = isset( $_POST['package'] ) ? wp_unslash( $_POST['package'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$result = Andreani_Store_Boxes::add_from_json( $json );

		if ( 'ok' !== $result['status'] ) {
			$messages = array(
				'invalid'   => __( 'No pudimos guardar el paquete. Probá de nuevo.', 'andreani-shipping' ),
				/* translators: %d: máximo de paquetes */
				'max'       => sprintf( __( 'Llegaste al máximo de %d paquetes.', 'andreani-shipping' ), Andreani_Store_Boxes::MAX_BOXES ),
				/* translators: %s: nombre del paquete ya guardado */
				'duplicate' => sprintf( __( 'Ya tenés «%s» con estas medidas.', 'andreani-shipping' ), $result['name'] ),
			);

			wp_send_json_error( array( 'message' => $messages[ $result['status'] ], 'existing' => $result['name'] ), 'invalid' === $result['status'] ? 400 : 409 );
		}

		Andreani_Cache_Service::clear_cotizaciones();
		self::forget_fit();

		wp_send_json_success( array(
			'id'       => $result['package']['id'],
			'packages' => Andreani_Store_Boxes::for_display(),
		) );
	}

	public function handle_fit() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		wp_send_json_success( array_merge( self::fit_summary(), array( 'assigned' => self::assigned_counts() ) ) );
	}

	/**
	 * @return array<string,int> Por id de paquete, cuántos productos lo tienen asignado.
	 */
	public static function assigned_counts() {
		global $wpdb;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_value AS id, COUNT(*) AS n FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value <> '' GROUP BY meta_value", Andreani_Product_Bultos::BOX_REF_META ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$counts = array();

		foreach ( (array) $rows as $row ) {
			$counts[ (string) $row->id ] = (int) $row->n;
		}

		return $counts;
	}

	private function is_boxes_page( $hook ) {
		return 'andreani_page_' . Andreani_Admin_Menu::BOXES_SLUG === $hook;
	}

	public function enqueue_assets( $hook ) {
		if ( ! $this->is_boxes_page( $hook ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-boxes.css',
			array(
				'andreani-core-base',
				Andreani_Core_Assets::HANDLE_C_BUTTON,
				Andreani_Core_Assets::HANDLE_C_MODAL,
				Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
			),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			self::SCRIPT,
			ANDREANI_PLUGIN_URL . 'includes/assets/js/andreani-boxes-page.js',
			array( 'jquery', Andreani_Core_Assets::HANDLE_BOX_PREVIEW ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			self::SCRIPT,
			'AndreaniBoxesPage',
			array(
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( self::NONCE ),
				'action'     => self::ACTION_SAVE,
				'fit_action' => self::ACTION_FIT,
				'max'        => Andreani_Store_Boxes::MAX_BOXES,
				'max_set'    => Andreani_Store_Boxes::SET_MAX_BOXES,
				'unit'       => get_option( 'woocommerce_dimension_unit', 'cm' ),
				'cm_factor'  => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( 1 ),
				'boxes'      => Andreani_Store_Boxes::for_display(),
				'i18n'       => $this->strings(),
			)
		);
	}

	private function strings() {
		return array(
			'type_box'      => __( 'Caja', 'andreani-shipping' ),
			'type_bag'      => __( 'Sobre o bolsa', 'andreani-shipping' ),
			'name'          => __( 'Nombre', 'andreani-shipping' ),
			'name_hint'     => __( 'Ej. Caja grande', 'andreani-shipping' ),
			'dims'          => __( 'Medidas', 'andreani-shipping' ),
			'type'          => __( 'Tipo', 'andreani-shipping' ),
			'fit'           => __( 'Productos', 'andreani-shipping' ),
			'type_set'      => __( 'Juego de cajas', 'andreani-shipping' ),
			'set_name_hint' => __( 'Ej. Aire acondicionado', 'andreani-shipping' ),
			'set_box'       => __( 'Caja', 'andreani-shipping' ),
			'set_ref'       => __( 'Referencia', 'andreani-shipping' ),
			'set_ref_hint'  => __( 'Ej. Exterior', 'andreani-shipping' ),
			'set_weight'    => __( 'Peso', 'andreani-shipping' ),
			'set_add'       => __( '+ Agregar caja', 'andreani-shipping' ),
			'set_remove'    => __( 'Quitar caja', 'andreani-shipping' ),
			'set_hint'      => __( 'Para productos que vienen en varias cajas: se cargan una vez y se asignan a todos los que sean iguales.', 'andreani-shipping' ),
			/* translators: %d: máximo de cajas por juego */
			'set_max'       => __( 'Un juego puede tener hasta %d cajas.', 'andreani-shipping' ),
			'set_min'       => __( 'Un juego necesita al menos 2 cajas con medidas y peso.', 'andreani-shipping' ),
			/* translators: %s: cantidad de cajas */
			'set_count'     => __( '%s cajas', 'andreani-shipping' ),
			'weight_unit'   => get_option( 'woocommerce_weight_unit', 'kg' ),
			'modal_new_set' => __( 'Agregar juego de cajas', 'andreani-shipping' ),
			'modal_edit_set' => __( 'Editar juego de cajas', 'andreani-shipping' ),
			'products_none' => __( 'Sin productos', 'andreani-shipping' ),
			/* translators: %s: cantidad de productos con el paquete asignado */
			'assigned_one'  => __( '1 asignado', 'andreani-shipping' ),
			'assigned_many' => __( '%s asignados', 'andreani-shipping' ),
			/* translators: %s: cantidad de productos que entran sin tener el paquete asignado */
			'enter_many'    => __( '%s entran sin asignar', 'andreani-shipping' ),
			'enter_one'     => __( '1 entra sin asignar', 'andreani-shipping' ),
			'remove_assigned' => __( 'Este paquete está asignado a productos: vuelven a sus medidas de WooCommerce. ¿Quitarlo?', 'andreani-shipping' ),
			'length'        => __( 'Largo', 'andreani-shipping' ),
			'width'         => __( 'Ancho', 'andreani-shipping' ),
			'height'        => __( 'Alto', 'andreani-shipping' ),
			'thickness'     => __( 'Grosor máx.', 'andreani-shipping' ),
			/* translators: %s: grosor máximo de la bolsa */
			'thickness_txt' => __( 'grosor %s', 'andreani-shipping' ),
			/* translators: %s: nombre de la caja ya guardada */
			'duplicate'     => __( 'Ya tenés «%s» con estas medidas.', 'andreani-shipping' ),
			'save'          => __( 'Guardar', 'andreani-shipping' ),
			'modal_new'     => __( 'Agregar paquete', 'andreani-shipping' ),
			'modal_edit'    => __( 'Editar paquete', 'andreani-shipping' ),
			'cota_l'        => __( 'L', 'andreani-shipping' ),
			'cota_w'        => __( 'A', 'andreani-shipping' ),
			'cota_h'        => __( 'H', 'andreani-shipping' ),
			'cota_t'        => __( 'Grosor', 'andreani-shipping' ),
			'search_none'   => __( 'No encontramos paquetes con ese nombre.', 'andreani-shipping' ),
			'cancel'        => __( 'Cancelar', 'andreani-shipping' ),
			'edit'          => __( 'Editar', 'andreani-shipping' ),
			'remove'        => __( 'Quitar', 'andreani-shipping' ),
			'confirm'       => __( '¿Quitar esta caja?', 'andreani-shipping' ),
			'quick_small'   => __( 'Caja chica', 'andreani-shipping' ),
			'quick_medium'  => __( 'Caja mediana', 'andreani-shipping' ),
			'quick_bag'     => __( 'Sobre o bolsa', 'andreani-shipping' ),
			'invalid'       => __( 'Completá el largo, el ancho y el alto.', 'andreani-shipping' ),
			/* translators: %s: máximo de cajas */
			'max'           => __( 'Llegaste al máximo de %s cajas.', 'andreani-shipping' ),
			'save_error'    => __( 'No pudimos guardar las cajas. Probá de nuevo.', 'andreani-shipping' ),
			'fit_none'      => __( 'Ninguno', 'andreani-shipping' ),
			'fit_one'       => __( '1 producto', 'andreani-shipping' ),
			/* translators: %s: cantidad de productos */
			'fit_many'      => __( '%s productos', 'andreani-shipping' ),
			'fit_unknown'   => __( 'No se pudo calcular', 'andreani-shipping' ),
			/* translators: %s: cantidad de productos evaluados */
			'fit_note'      => __( 'Calculado sobre %s productos con medidas, de 1 unidad cada uno.', 'andreani-shipping' ),
			/* translators: 1: cajas cargadas, 2: máximo de cajas */
			'count'         => __( '%1$s de %2$s cajas', 'andreani-shipping' ),
		);
	}
}
