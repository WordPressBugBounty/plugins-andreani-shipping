<?php
/**
 * Panel de modo de despacho en la ficha de producto: un solo paquete, varias
 * unidades apiladas en un bulto, o una unidad repartida en varias piezas.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Product_Bultos {

	private static $instance = null;

	const META_KEY  = '_andreani_bultos_adicionales';
	const NONCE_KEY = 'andreani_bultos_nonce';

	const MODE_FIELD      = 'andreani_dispatch_mode';
	const MODE_SINGLE     = 'single';
	const MODE_APILADO    = 'apilado';
	const MODE_MULTIBULTO = 'multibulto';

	const PREVIEW_NONCE      = 'andreani_preview_bultos';
	const PREVIEW_QUANTITIES = array( 1, 10, 50, 200 );

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

		$evaluation  = self::evaluate_bigger( $post->ID );
		$bigger_text = self::bigger_status_text( $evaluation );
		$is_bigger   = $evaluation['is_bigger'];
		$bultos      = self::to_store_units( self::get_bultos( $post->ID ) );
		$mode        = self::resolve_dispatch_mode( $post->ID );

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-bultos-panel.php';
	}

	/**
	 * @param int $product_id ID del producto (o variación).
	 * @return string
	 */
	public static function resolve_dispatch_mode( $product_id ) {
		if ( ! empty( self::get_bultos( $product_id ) ) ) {
			return self::MODE_MULTIBULTO;
		}

		if ( class_exists( 'Andreani_Product_Apilado' )
			&& Andreani_Product_Apilado::is_valid( Andreani_Product_Apilado::get_apilado( $product_id ) ) ) {
			return self::MODE_APILADO;
		}

		return self::MODE_SINGLE;
	}

	/**
	 * @param string $mode Modo tal como llegó del formulario.
	 * @return string
	 */
	public static function sanitize_dispatch_mode( $mode ) {
		$mode = is_string( $mode ) ? $mode : '';

		return in_array( $mode, array( self::MODE_APILADO, self::MODE_MULTIBULTO ), true )
			? $mode
			: self::MODE_SINGLE;
	}

	/**
	 * Determinar si un producto califica como Bigger según los umbrales de Andreani.
	 *
	 * @param int $product_id ID del producto (o variación).
	 * @return bool
	 */
	public static function is_bigger_product( $product_id ) {
		$evaluation = self::evaluate_bigger( $product_id );

		return $evaluation['is_bigger'];
	}

	/**
	 * Combina principal + bultos adicionales: peso es la suma total; suma de lados y
	 * lado máximo se quedan con el mayor entre todos los bultos.
	 *
	 * Sin bultos adicionales y con apilado válido evalúa la pila al tope (maxStackableUnits),
	 * que es el bulto más grande que el producto llega a declarar.
	 *
	 * @param int $product_id ID del producto (o variación).
	 * @return array{is_bigger:bool,reason:string,value:float}
	 */
	public static function evaluate_bigger( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return self::bigger_evaluation( false, '', 0.0 );
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

		if ( empty( $bultos ) && class_exists( 'Andreani_Product_Apilado' ) ) {
			$apilado = Andreani_Product_Apilado::get_apilado( $product_id );

			if ( Andreani_Product_Apilado::is_valid( $apilado ) ) {
				$max_stackable_units = (int) $apilado['maxStackableUnits'];
				$extra               = $max_stackable_units - 1;

				$width  += (float) $apilado['unitIncrementWidth'] * $extra;
				$height += (float) $apilado['unitIncrementHeight'] * $extra;
				$length += (float) $apilado['unitIncrementDepth'] * $extra;

				$total_weight  = $weight * $max_stackable_units;
				$max_sum_sides = $width + $height + $length;
				$max_side      = max( $width, $height, $length );
			}
		}

		foreach ( $bultos as $bulto ) {
			$bw = (float) $bulto['weight'] / 1000;
			$bx = (float) $bulto['width'];
			$by = (float) $bulto['height'];
			$bz = (float) $bulto['depth'];

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

		if ( $total_weight > Andreani_Api_Config::BIGGER_WEIGHT_KG ) {
			return self::bigger_evaluation( true, 'weight', $total_weight );
		}

		if ( $max_sum_sides > Andreani_Api_Config::BIGGER_SUM_SIDES_CM ) {
			return self::bigger_evaluation( true, 'sum_sides', $max_sum_sides );
		}

		if ( $max_side > Andreani_Api_Config::BIGGER_MAX_SIDE_CM ) {
			return self::bigger_evaluation( true, 'max_side', $max_side );
		}

		return self::bigger_evaluation( false, '', 0.0 );
	}

	/**
	 * @param bool   $is_bigger Si califica como Bigger.
	 * @param string $reason    Motivo: weight, sum_sides o max_side.
	 * @param float  $value     Magnitud medida que disparó el motivo, en kg o cm.
	 * @return array{is_bigger:bool,reason:string,value:float}
	 */
	private static function bigger_evaluation( $is_bigger, $reason, $value ) {
		return array(
			'is_bigger' => (bool) $is_bigger,
			'reason'    => (string) $reason,
			'value'     => (float) $value,
		);
	}

	/**
	 * @param array $evaluation Evaluación devuelta por evaluate_bigger().
	 * @return string
	 */
	public static function bigger_status_text( array $evaluation ) {
		$strings = self::get_ui_strings();

		if ( empty( $evaluation['is_bigger'] ) ) {
			return $strings['bigger_regular'];
		}

		$reasons = array(
			'weight'    => array( $strings['bigger_reason_weight'], Andreani_Api_Config::BIGGER_WEIGHT_KG ),
			'sum_sides' => array( $strings['bigger_reason_sum_sides'], Andreani_Api_Config::BIGGER_SUM_SIDES_CM ),
			'max_side'  => array( $strings['bigger_reason_max_side'], Andreani_Api_Config::BIGGER_MAX_SIDE_CM ),
		);

		$reason = isset( $evaluation['reason'] ) ? $evaluation['reason'] : '';

		if ( ! isset( $reasons[ $reason ] ) ) {
			return $strings['bigger_regular'];
		}

		return sprintf(
			$strings['bigger_prefix'],
			sprintf(
				$reasons[ $reason ][0],
				self::format_measure( isset( $evaluation['value'] ) ? $evaluation['value'] : 0 ),
				self::format_measure( $reasons[ $reason ][1] )
			)
		);
	}

	/**
	 * @param mixed $value Magnitud numérica.
	 * @return string
	 */
	public static function format_measure( $value ) {
		$formatted = number_format( (float) $value, 2, '.', '' );
		$formatted = rtrim( rtrim( $formatted, '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}

	public static function preview_rows_from_draft( array $draft ) {
		return Andreani_Package_Builder::preview(
			array(
				'width'  => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['width'] ) ? $draft['width'] : 0 ),
				'height' => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['height'] ) ? $draft['height'] : 0 ),
				'depth'  => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['length'] ) ? $draft['length'] : 0 ),
			),
			Andreani_Order_Mapper::convert_weight_to_unit( isset( $draft['weight'] ) ? $draft['weight'] : 0, 'kg' ),
			isset( $draft['apilado'] ) && is_array( $draft['apilado'] ) ? $draft['apilado'] : array(),
			isset( $draft['bultos'] ) && is_array( $draft['bultos'] ) ? $draft['bultos'] : array(),
			self::PREVIEW_QUANTITIES
		);
	}

	public static function format_preview_number( $value ) {
		$value     = (float) $value;
		$formatted = number_format( $value, 3, ',', '.' );
		$formatted = rtrim( rtrim( $formatted, '0' ), ',' );

		if ( '0' === $formatted && $value > 0 ) {
			return '< 0,001';
		}

		return $formatted;
	}

	public static function format_preview_weight( $weight_kg ) {
		$weight_kg = (float) $weight_kg;

		return $weight_kg < 1
			? self::format_preview_number( $weight_kg * 1000 ) . ' g'
			: self::format_preview_number( $weight_kg ) . ' kg';
	}

	public static function render_preview( array $rows ) {
		$strings = self::get_ui_strings();

		if ( empty( $rows ) ) {
			return '<p class="andreani-despacho-preview__message">' . esc_html( $strings['preview_empty'] ) . '</p>';
		}

		$html = '<table class="andreani-despacho-preview__table"><thead><tr>';

		foreach ( array( 'preview_col_units', 'preview_col_bultos', 'preview_col_volume', 'preview_col_weight', 'preview_col_aforado' ) as $key ) {
			$html .= '<th scope="col">' . esc_html( $strings[ $key ] ) . '</th>';
		}

		$html .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$html .= '<tr data-quantity="' . esc_attr( $row['quantity'] ) . '">'
				. '<td data-col="units">' . esc_html( $row['quantity'] ) . '</td>'
				. '<td data-col="bultos">' . esc_html( $row['bultos'] ) . '</td>'
				. '<td data-col="volume">' . esc_html( self::format_preview_number( $row['volume_cm3'] ) . ' cm³' ) . '</td>'
				. self::preview_weight_cell( 'real', $row['weight_kg'], 'real' === $row['charged'] )
				. self::preview_weight_cell( 'aforado', $row['aforado_kg'], 'aforado' === $row['charged'] )
				. '</tr>';
		}

		return $html . '</tbody></table>';
	}

	private static function preview_weight_cell( $column, $weight_kg, $charged ) {
		return '<td data-col="' . esc_attr( $column ) . '"' . ( $charged ? ' class="andreani-despacho-preview__cell--charged"' : '' ) . '>'
			. esc_html( self::format_preview_weight( $weight_kg ) )
			. '</td>';
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_ui_strings() {
		return array(
			'mode_question'            => __( '¿Cómo se despacha este producto?', 'andreani-shipping' ),
			'mode_single_title'        => __( 'En un solo paquete', 'andreani-shipping' ),
			'mode_single_desc'         => __( 'Usa el peso y las medidas de arriba.', 'andreani-shipping' ),
			'mode_apilado_title'       => __( 'Varias unidades viajan juntas', 'andreani-shipping' ),
			'mode_apilado_desc'        => __( 'Se apilan y viajan en un mismo bulto que crece. Ej.: sillas que se apilan una sobre otra.', 'andreani-shipping' ),
			'mode_multibulto_title'    => __( 'Una unidad viaja en varias piezas', 'andreani-shipping' ),
			'mode_multibulto_desc'     => __( 'Una sola unidad del producto se despacha en más de una caja. Ej.: un aire acondicionado, unidad interior + unidad exterior.', 'andreani-shipping' ),
			'mode_multibulto_warning'  => __( 'Cada pieza que agregues se cotiza y se despacha como un paquete más, por cada unidad vendida.', 'andreani-shipping' ),
			'bigger_prefix'            => __( 'Se despacha como Bigger — %s', 'andreani-shipping' ),
			'bigger_regular'           => __( 'Se despacha como Paquete estándar', 'andreani-shipping' ),
			/* translators: 1: peso del producto en kg, 2: umbral de peso en kg */
			'bigger_reason_weight'     => __( 'pesa %1$s kg y supera los %2$s kg de paquetería', 'andreani-shipping' ),
			/* translators: 1: suma de los lados en cm, 2: umbral de suma de lados en cm */
			'bigger_reason_sum_sides'  => __( 'la suma de sus lados es %1$s cm y supera los %2$s cm', 'andreani-shipping' ),
			/* translators: 1: lado más largo en cm, 2: umbral de lado máximo en cm */
			'bigger_reason_max_side'   => __( 'su lado más largo es %1$s cm y supera los %2$s cm', 'andreani-shipping' ),
			'same_dims_warning'        => __( 'Esta pieza tiene las mismas medidas que el bulto principal. Si lo que pasa es que vendés varias unidades y viajan juntas, esto no es multibulto: elegí «Varias unidades viajan juntas».', 'andreani-shipping' ),
			'switch_to_apilado'        => __( 'Cambiar a apilado', 'andreani-shipping' ),
			'apilado_invalid'          => class_exists( 'Andreani_Product_Apilado' )
				? Andreani_Product_Apilado::invalid_message()
				: '',
			'bultos_invalid'           => self::bultos_invalid_message(),
			'preview_title'            => __( 'Así se cotiza', 'andreani-shipping' ),
			'preview_help'             => sprintf(
				/* translators: %s: kilos por metro cúbico que se usan para calcular el peso aforado */
				__( 'Esto es lo que se le declara a Andreani según cuántas unidades te compren, con lo que tenés cargado en pantalla. El peso aforado es el que le corresponde al envío por el espacio que ocupa (%s kg por cada m³). Andreani cobra por el mayor de los dos pesos: en rojo, el que se cobra.', 'andreani-shipping' ),
				self::format_measure( Andreani_Api_Config::AFORO_KG_M3 )
			),
			'preview_empty'            => __( 'Cargá el peso y las tres medidas del producto, y completá la opción de despacho elegida, para ver el ejemplo.', 'andreani-shipping' ),
			'preview_col_units'        => __( 'Unidades', 'andreani-shipping' ),
			'preview_col_bultos'       => __( 'Bultos', 'andreani-shipping' ),
			'preview_col_volume'       => __( 'Volumen total', 'andreani-shipping' ),
			'preview_col_weight'       => __( 'Peso real', 'andreani-shipping' ),
			'preview_col_aforado'      => __( 'Peso aforado', 'andreani-shipping' ),
		);
	}

	/**
	 * @return array{weight:float,sum_sides:float,max_side:float}
	 */
	public static function get_canonical_thresholds() {
		return array(
			'weight'    => (float) Andreani_Api_Config::BIGGER_WEIGHT_KG,
			'sum_sides' => (float) Andreani_Api_Config::BIGGER_SUM_SIDES_CM,
			'max_side'  => (float) Andreani_Api_Config::BIGGER_MAX_SIDE_CM,
		);
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

		$mode = self::sanitize_dispatch_mode(
			isset( $_POST[ self::MODE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::MODE_FIELD ] ) ) : ''
		);

		if ( self::MODE_MULTIBULTO !== $mode ) {
			if ( self::MODE_APILADO === $mode && class_exists( 'Andreani_Product_Apilado' ) && ! Andreani_Product_Apilado::is_valid( Andreani_Product_Apilado::posted_config() ) ) {
				return;
			}

			delete_post_meta( $post_id, self::META_KEY );
			return;
		}

		$bultos = self::posted_pieces();

		if ( empty( $bultos ) ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( self::bultos_invalid_message() );
			}

			return;
		}

		// update_post_meta desescapa el valor: sin wp_slash una comilla en la
		// referencia del bulto rompe el JSON que se guarda.
		update_post_meta( $post_id, self::META_KEY, wp_slash( wp_json_encode( $bultos ) ) );
	}

	/**
	 * @return array<int,array{name:string,height:float,width:float,depth:float,weight:float}>
	 */
	public static function posted_pieces() {
		$rows = array();

		if ( ! empty( $_POST['andreani_bulto_weight'] ) && is_array( $_POST['andreani_bulto_weight'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$weights = array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_weight'] ) );
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$widths  = isset( $_POST['andreani_bulto_width'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_width'] ) ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$heights = isset( $_POST['andreani_bulto_height'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_height'] ) ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$depths  = isset( $_POST['andreani_bulto_depth'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_depth'] ) ) : array();
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$names   = isset( $_POST['andreani_bulto_name'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['andreani_bulto_name'] ) ) : array();

			foreach ( $weights as $i => $weight ) {
				$rows[] = array(
					'name'   => isset( $names[ $i ] ) ? $names[ $i ] : '',
					'height' => isset( $heights[ $i ] ) ? $heights[ $i ] : 0,
					'width'  => isset( $widths[ $i ] ) ? $widths[ $i ] : 0,
					'depth'  => isset( $depths[ $i ] ) ? $depths[ $i ] : 0,
					'weight' => $weight,
				);
			}
		}

		return self::pieces_from_rows( $rows );
	}

	/**
	 * @return string
	 */
	public static function bultos_invalid_message() {
		return __( 'Para despachar en varias piezas cargá al menos una pieza con peso y las tres medidas completas.', 'andreani-shipping' );
	}

	/**
	 * @param array $rows Filas con name, height, width, depth y weight en la unidad de la tienda.
	 * @return array<int,array{name:string,height:float,width:float,depth:float,weight:float}>
	 */
	public static function pieces_from_rows( array $rows ) {
		$bultos = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$weight = isset( $row['weight'] ) ? floatval( $row['weight'] ) : 0;
			$width  = isset( $row['width'] ) ? floatval( $row['width'] ) : 0;
			$height = isset( $row['height'] ) ? floatval( $row['height'] ) : 0;
			$depth  = isset( $row['depth'] ) ? floatval( $row['depth'] ) : 0;

			if ( $weight > 0 && $width > 0 && $height > 0 && $depth > 0 ) {
				$bultos[] = self::to_canonical_bulto(
					isset( $row['name'] ) ? $row['name'] : '',
					$height,
					$width,
					$depth,
					$weight,
					count( $bultos )
				);
			}
		}

		return $bultos;
	}

	/**
	 * Un bulto en el formato canónico del maestro de productos: dimensiones en cm,
	 * peso en gramos y una referencia siempre presente. Recibe las medidas tal como
	 * las tipeó el merchant, o sea en la unidad configurada en la tienda.
	 *
	 * @param string $name     Referencia del bulto; vacía autogenera "Bulto N".
	 * @param float  $height   Alto en la unidad de la tienda.
	 * @param float  $width    Ancho en la unidad de la tienda.
	 * @param float  $depth    Profundidad en la unidad de la tienda.
	 * @param float  $weight   Peso en la unidad de la tienda.
	 * @param int    $position Posición del bulto adicional, base 0.
	 * @return array{name:string,height:float,width:float,depth:float,weight:float}
	 */
	public static function to_canonical_bulto( $name, $height, $width, $depth, $weight, $position ) {
		return array(
			'name'   => self::resolve_bulto_name( $name, $position ),
			'height' => (float) Andreani_Order_Mapper::convert_dimension_to_cm( $height ),
			'width'  => (float) Andreani_Order_Mapper::convert_dimension_to_cm( $width ),
			'depth'  => (float) Andreani_Order_Mapper::convert_dimension_to_cm( $depth ),
			'weight' => (float) Andreani_Order_Mapper::convert_weight_to_unit( $weight, 'gr' ),
		);
	}

	/**
	 * Bultos canónicos pasados a la unidad de la tienda, que es en la que el
	 * merchant los tipea. Solo para pintar los formularios del admin.
	 *
	 * @param array $bultos Bultos en cm y gramos.
	 * @return array<int,array{name:string,height:float,width:float,depth:float,weight:float}>
	 */
	public static function to_store_units( array $bultos ) {
		$en_unidad_tienda = array();

		foreach ( array_values( $bultos ) as $position => $bulto ) {
			if ( ! is_array( $bulto ) ) {
				continue;
			}

			$en_unidad_tienda[] = array(
				'name'   => self::resolve_bulto_name( isset( $bulto['name'] ) ? $bulto['name'] : '', $position ),
				'height' => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['height'] ) ? $bulto['height'] : 0 ),
				'width'  => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['width'] ) ? $bulto['width'] : 0 ),
				'depth'  => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['depth'] ) ? $bulto['depth'] : 0 ),
				'weight' => (float) Andreani_Order_Mapper::convert_grams_to_weight_unit( isset( $bulto['weight'] ) ? $bulto['weight'] : 0 ),
			);
		}

		return $en_unidad_tienda;
	}

	/**
	 * Referencia del bulto, autogenerada cuando el merchant la dejó vacía. El
	 * bulto 1 es el producto principal, así que el primer adicional es el 2.
	 *
	 * @param string $name     Referencia tipeada.
	 * @param int    $position Posición del bulto adicional, base 0.
	 * @return string
	 */
	private static function resolve_bulto_name( $name, $position ) {
		$name = trim( sanitize_text_field( (string) $name ) );

		if ( '' !== $name ) {
			return $name;
		}

		/* translators: %d: número de bulto dentro del envío */
		return sprintf( __( 'Bulto %d', 'andreani-shipping' ), (int) $position + 2 );
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
				'thresholds'           => self::get_bigger_thresholds(),
				'thresholds_canonical' => self::get_canonical_thresholds(),
				'cm_factor'            => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( 1 ),
				'kg_factor'            => (float) Andreani_Order_Mapper::convert_weight_to_unit( 1, 'kg' ),
				'ajax_url'             => admin_url( 'admin-ajax.php' ),
				'nonce_preview'        => wp_create_nonce( self::PREVIEW_NONCE ),
				'i18n'                 => self::get_ui_strings(),
			)
		);
	}

	/**
	 * Bultos adicionales en unidades canónicas: dimensiones en cm y peso en gramos.
	 *
	 * @param int $product_id ID del producto (o variación).
	 * @return array<int,array{name:string,height:float,width:float,depth:float,weight:float}>
	 */
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
