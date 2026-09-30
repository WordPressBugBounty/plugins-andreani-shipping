<?php
/**
 * Configuración de apilado en la ficha de producto.
 *
 * Un producto apilable (ej: sillas que se encastran) viaja en pilas de hasta
 * maxStackableUnits unidades: la primera unidad ocupa las dimensiones del producto y
 * cada unidad extra suma los incrementos configurados.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Product_Apilado {

	private static $instance = null;

	const META_KEY  = '_andreani_apilado';
	const NONCE_KEY = 'andreani_apilado_nonce';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Prioridad 20: tiene que correr DESPUÉS de que Andreani_Product_Bultos
		// guarde, porque las dos metas son excluyentes y el modo elegido manda.
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_apilado' ), 20 );
	}

	/**
	 * @param int $product_id ID del producto (o variación).
	 */
	public static function render_fields( $product_id ) {
		$apilado = self::get_apilado( $product_id );

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-apilado-panel.php';
	}

	/**
	 * @return string
	 */
	public static function invalid_message() {
		return __( 'Para apilar necesitás un límite de 2 unidades o más y que al menos una de las tres medidas aumente por unidad. Si las unidades entran en la misma caja sin que crezca, escribinos: hoy no se puede declarar así.', 'andreani-shipping' );
	}

	/**
	 * Configuración de apilado de un producto, con fallback al padre si es una
	 * variación sin configuración propia.
	 *
	 * @param int $product_id ID del producto (o variación).
	 * @return array Config cruda, o array vacío si no hay o el JSON es inválido.
	 */
	public static function get_apilado( $product_id ) {
		$config = self::read_config( $product_id );

		if ( empty( $config ) ) {
			$product = wc_get_product( $product_id );
			if ( $product && $product->is_type( 'variation' ) ) {
				$config = self::read_config( $product->get_parent_id() );
			}
		}

		return $config;
	}

	/**
	 * Una config sirve si arma pilas de al menos 2 unidades y la unidad extra
	 * crece en algún eje: sin incremento la pila no cambia de volumen.
	 *
	 * @param array $config Config de apilado.
	 * @return bool
	 */
	public static function is_valid( array $config ) {
		$keys = array( 'maxStackableUnits', 'unitIncrementHeight', 'unitIncrementWidth', 'unitIncrementDepth' );

		foreach ( $keys as $key ) {
			if ( ! isset( $config[ $key ] ) || ! is_numeric( $config[ $key ] ) ) {
				return false;
			}
		}

		if ( (int) $config['maxStackableUnits'] < 2 ) {
			return false;
		}

		$unit_increment_height = (float) $config['unitIncrementHeight'];
		$unit_increment_width  = (float) $config['unitIncrementWidth'];
		$unit_increment_depth  = (float) $config['unitIncrementDepth'];

		if ( $unit_increment_height < 0 || $unit_increment_width < 0 || $unit_increment_depth < 0 ) {
			return false;
		}

		return $unit_increment_height > 0 || $unit_increment_width > 0 || $unit_increment_depth > 0;
	}

	public function save_apilado( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_KEY ] ) ), 'andreani_save_apilado' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$mode = class_exists( 'Andreani_Product_Bultos' )
			? Andreani_Product_Bultos::sanitize_dispatch_mode(
				isset( $_POST[ Andreani_Product_Bultos::MODE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ Andreani_Product_Bultos::MODE_FIELD ] ) ) : ''
			)
			: '';

		if ( ! class_exists( 'Andreani_Product_Bultos' ) || Andreani_Product_Bultos::MODE_APILADO !== $mode ) {
			if ( class_exists( 'Andreani_Product_Bultos' ) && Andreani_Product_Bultos::MODE_MULTIBULTO === $mode && empty( Andreani_Product_Bultos::posted_pieces() ) ) {
				return;
			}

			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$config = self::posted_config();

		if ( ! self::is_valid( $config ) ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( self::invalid_message() );
			}

			return;
		}

		update_post_meta( $post_id, self::META_KEY, wp_json_encode( $config ) );
	}

	/**
	 * @return array{maxStackableUnits:int,unitIncrementHeight:float,unitIncrementWidth:float,unitIncrementDepth:float}
	 */
	public static function posted_config() {
		return array(
			'maxStackableUnits'   => isset( $_POST['andreani_apilado_maxStackableUnits'] ) ? absint( wp_unslash( $_POST['andreani_apilado_maxStackableUnits'] ) ) : 0,
			'unitIncrementHeight' => isset( $_POST['andreani_apilado_unitIncrementHeight'] ) ? floatval( wp_unslash( $_POST['andreani_apilado_unitIncrementHeight'] ) ) : 0,
			'unitIncrementWidth'  => isset( $_POST['andreani_apilado_unitIncrementWidth'] ) ? floatval( wp_unslash( $_POST['andreani_apilado_unitIncrementWidth'] ) ) : 0,
			'unitIncrementDepth'  => isset( $_POST['andreani_apilado_unitIncrementDepth'] ) ? floatval( wp_unslash( $_POST['andreani_apilado_unitIncrementDepth'] ) ) : 0,
		);
	}

	private static function read_config( $product_id ) {
		$json = get_post_meta( $product_id, self::META_KEY, true );

		if ( empty( $json ) ) {
			return array();
		}

		$config = json_decode( $json, true );

		if ( ! is_array( $config ) ) {
			return array();
		}

		return $config;
	}
}
