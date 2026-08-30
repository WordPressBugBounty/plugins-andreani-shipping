<?php
/**
 * Panel de bultos adicionales en la ficha de producto.
 *
 * Cualquier producto puede configurar múltiples bultos (ej: un mueble que
 * viaja en 2 cajas). El badge Bigger es solo informativo — indica si el
 * producto supera los umbrales de Andreani para el servicio Bigger.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Product_Bultos {

	private static $instance = null;

	const META_KEY  = '_andreani_bultos_adicionales';
	const NONCE_KEY = 'andreani_bultos_nonce';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'render_panel' ), 100 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_bultos' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function render_panel() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$is_bigger  = self::is_bigger_product( $post->ID );
		$bultos     = self::get_bultos( $post->ID );
		$has_bultos = ! empty( $bultos );

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-bultos-panel.php';
	}

	/**
	 * Determinar si un producto califica como Bigger según los umbrales de Andreani.
	 *
	 * Combina principal + bultos adicionales: peso es la suma total; suma de lados y
	 * lado máximo se quedan con el mayor entre todos los bultos.
	 *
	 * @param int $product_id ID del producto (o variación).
	 * @return bool
	 */
	public static function is_bigger_product( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return false;
		}

		$weight = (float) $product->get_weight();
		$width  = (float) $product->get_width();
		$height = (float) $product->get_height();
		$length = (float) $product->get_length();

		// Fallback al padre si es variación sin dimensiones propias.
		if ( $product->is_type( 'variation' ) && ( 0 === $weight || 0 === $width || 0 === $height || 0 === $length ) ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$weight = $weight ?: (float) $parent->get_weight();
				$width  = $width  ?: (float) $parent->get_width();
				$height = $height ?: (float) $parent->get_height();
				$length = $length ?: (float) $parent->get_length();
			}
		}

		$weight = (float) Andreani_Order_Mapper::convert_weight_to_unit( $weight, 'kg' );
		$width  = Andreani_Order_Mapper::convert_dimension_to_cm( $width );
		$height = Andreani_Order_Mapper::convert_dimension_to_cm( $height );
		$length = Andreani_Order_Mapper::convert_dimension_to_cm( $length );

		$total_weight  = $weight;
		$max_sum_sides = $width + $height + $length;
		$max_side      = max( $width, $height, $length );

		$bultos = self::get_bultos( $product_id );
		foreach ( $bultos as $bulto ) {
			$bw = (float) Andreani_Order_Mapper::convert_weight_to_unit( $bulto['weight'], 'kg' );
			$bx = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['width'] );
			$by = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['height'] );
			$bz = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['length'] );

			$total_weight += $bw;

			$bulto_sum_sides = $bx + $by + $bz;
			if ( $bulto_sum_sides > $max_sum_sides ) {
				$max_sum_sides = $bulto_sum_sides;
			}

			$bulto_max_side = max( $bx, $by, $bz );
			if ( $bulto_max_side > $max_side ) {
				$max_side = $bulto_max_side;
			}
		}

		return $total_weight  > Andreani_Api_Config::BIGGER_WEIGHT_KG
			|| $max_sum_sides > Andreani_Api_Config::BIGGER_SUM_SIDES_CM
			|| $max_side      > Andreani_Api_Config::BIGGER_MAX_SIDE_CM;
	}

	/**
	 * Umbrales Bigger expuestos para el JS, convertidos a la unidad de la tienda:
	 * el JS los compara contra lo que el merchant tipea en el formulario, que está
	 * en esa unidad. Los umbrales canónicos siguen siendo kg/cm.
	 *
	 * @return array{weight:float,sum_sides:float,max_side:float}
	 */
	public static function get_bigger_thresholds() {
		return array(
			'weight'    => (float) Andreani_Order_Mapper::convert_kg_to_weight_unit( Andreani_Api_Config::BIGGER_WEIGHT_KG ),
			'sum_sides' => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( Andreani_Api_Config::BIGGER_SUM_SIDES_CM ),
			'max_side'  => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( Andreani_Api_Config::BIGGER_MAX_SIDE_CM ),
		);
	}

	public function save_bultos( $post_id ) {
		if ( ! isset( $_POST[ self::NONCE_KEY ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_KEY ] ) ), 'andreani_save_bultos' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		$bultos = array();

		if ( ! empty( $_POST['andreani_bulto_weight'] ) && is_array( $_POST['andreani_bulto_weight'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$weights = array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_weight'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$widths  = isset( $_POST['andreani_bulto_width'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_width'] ) ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$heights = isset( $_POST['andreani_bulto_height'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_height'] ) ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$lengths = isset( $_POST['andreani_bulto_length'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_length'] ) ) : array();

			foreach ( $weights as $i => $weight ) {
				$w      = floatval( $weight );
				$width  = isset( $widths[ $i ] ) ? floatval( $widths[ $i ] ) : 0;
				$height = isset( $heights[ $i ] ) ? floatval( $heights[ $i ] ) : 0;
				$length = isset( $lengths[ $i ] ) ? floatval( $lengths[ $i ] ) : 0;

				if ( $w > 0 && $width > 0 && $height > 0 && $length > 0 ) {
					$bultos[] = array(
						'weight' => $w,
						'width'  => $width,
						'height' => $height,
						'length' => $length,
					);
				}
			}
		}

		if ( ! empty( $bultos ) ) {
			update_post_meta( $post_id, self::META_KEY, wp_json_encode( $bultos ) );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	/**
	 * Encolar assets solo en la pantalla de edición de producto.
	 *
	 * @param string $hook Hook de la página actual.
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'product' !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'andreani-product-bultos',
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-product-bultos.css',
			array( 'andreani-core-base' ),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'andreani-product-bultos',
			ANDREANI_PLUGIN_URL . 'includes/assets/js/product-bultos.js',
			array( 'jquery' ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'andreani-product-bultos',
			'AndreaniBultosConfig',
			array(
				'thresholds' => self::get_bigger_thresholds(),
				'i18n'       => array(
					'badge_bigger'   => __( 'Bigger', 'andreani-shipping' ),
					'badge_paquete'  => __( 'Paquete común', 'andreani-shipping' ),
				),
			)
		);
	}

	public static function get_bultos( $product_id ) {
		$json = get_post_meta( $product_id, self::META_KEY, true );

		if ( empty( $json ) ) {
			return array();
		}

		$bultos = json_decode( $json, true );

		if ( ! is_array( $bultos ) ) {
			return array();
		}

		return $bultos;
	}
}
