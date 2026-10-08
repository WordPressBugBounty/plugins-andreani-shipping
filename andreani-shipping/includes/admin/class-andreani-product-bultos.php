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
	const SKU_META_KEY = '_andreani_sku';

	const MAIN_REF_META = '_andreani_main_box_ref';
	const MAIN_REF_MAX  = 60;
	const NONCE_KEY = 'andreani_bultos_nonce';

	const MODE_FIELD      = 'andreani_dispatch_mode';
	const MODE_META       = '_andreani_dispatch_mode';
	const MODE_SINGLE     = 'single';
	const MODE_APILADO    = 'apilado';
	const MODE_MULTIBULTO = 'multibulto';

	const PREVIEW_NONCE      = 'andreani_preview_bultos';
	const BOX_OPEN_NONCE     = 'andreani_dispatch_box_open';
	const BOX_OPEN_META      = 'andreani_dispatch_box_open';
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
		add_action( 'wp_ajax_andreani_dispatch_box_open', array( $this, 'handle_box_open' ) );
	}

	public static function box_is_open() {
		return '1' === get_user_meta( get_current_user_id(), self::BOX_OPEN_META, true );
	}

	public function handle_box_open() {
		check_ajax_referer( self::BOX_OPEN_NONCE, 'nonce' );

		if ( ! current_user_can( 'edit_products' ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$open = isset( $_POST['open'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['open'] ) );

		update_user_meta( get_current_user_id(), self::BOX_OPEN_META, $open ? '1' : '0' );

		wp_send_json_success();
	}

	public function render_panel() {
		global $post;

		if ( ! $post ) {
			return;
		}

		$status = self::dispatch_status( $post->ID );
		$bultos = self::to_store_units( self::get_bultos( $post->ID ) );
		$mode   = self::resolve_dispatch_mode( $post->ID );
		$open   = self::box_is_open();

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-bultos-panel.php';
	}

	/**
	 * @param int $product_id ID del producto (o variación).
	 * @return string missing, bigger u ok.
	 */
	public static function dispatch_status( $product_id ) {
		$product = wc_get_product( $product_id );

		return $product ? Andreani_Products_Stats::classify_product( $product )['state'] : 'missing';
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
	 * Evaluación pura: sin lecturas de WordPress, para que el listado y los
	 * contadores del catálogo usen exactamente la misma regla.
	 *
	 * @param float $weight_kg Peso de una unidad en kg.
	 * @param float $width     Ancho en cm.
	 * @param float $height    Alto en cm.
	 * @param float $length    Largo en cm.
	 * @param array $bultos    Bultos adicionales en cm y gramos.
	 * @param array $apilado   Config de apilado cruda (se ignora si hay bultos).
	 * @return array{is_bigger:bool,reason:string,value:float}
	 */
	public static function bigger_from_values( $weight_kg, $width, $height, $length, array $bultos, array $apilado ) {
		$weight = (float) $weight_kg;
		$width  = (float) $width;
		$height = (float) $height;
		$length = (float) $length;

		$total_weight  = $weight;
		$max_sum_sides = $width + $height + $length;
		$max_side      = max( $width, $height, $length );

		if ( empty( $bultos ) && Andreani_Product_Apilado::is_valid( $apilado ) ) {
			$max_stackable_units = (int) $apilado['maxStackableUnits'];
			$extra               = $max_stackable_units - 1;

			$width  += (float) $apilado['unitIncrementWidth'] * $extra;
			$height += (float) $apilado['unitIncrementHeight'] * $extra;
			$length += (float) $apilado['unitIncrementDepth'] * $extra;

			$total_weight  = $weight * $max_stackable_units;
			$max_sum_sides = $width + $height + $length;
			$max_side      = max( $width, $height, $length );
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
	 * @param mixed $value Magnitud numérica.
	 * @return string
	 */
	public static function format_measure( $value ) {
		$formatted = number_format( (float) $value, 2, '.', '' );
		$formatted = rtrim( rtrim( $formatted, '0' ), '.' );

		return '' === $formatted ? '0' : $formatted;
	}

	public static function preview_rows_from_draft( array $draft ) {
		list( $base, $weight_kg, $apilado, $bultos ) = self::preview_inputs( $draft );

		return Andreani_Package_Builder::preview( $base, $weight_kg, $apilado, $bultos, self::PREVIEW_QUANTITIES );
	}

	public static function preview_packages_from_draft( array $draft, $quantity ) {
		list( $base, $weight_kg, $apilado, $bultos ) = self::preview_inputs( $draft );

		return Andreani_Package_Builder::draw_packages( $base, $weight_kg, $apilado, $bultos, $quantity, isset( $draft['main_ref'] ) ? (string) $draft['main_ref'] : '' );
	}

	private static function preview_inputs( array $draft ) {
		return array(
			array(
				'width'  => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['width'] ) ? $draft['width'] : 0 ),
				'height' => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['height'] ) ? $draft['height'] : 0 ),
				'depth'  => Andreani_Order_Mapper::convert_dimension_to_cm( isset( $draft['length'] ) ? $draft['length'] : 0 ),
			),
			Andreani_Order_Mapper::convert_weight_to_unit( isset( $draft['weight'] ) ? $draft['weight'] : 0, 'kg' ),
			isset( $draft['apilado'] ) && is_array( $draft['apilado'] ) ? $draft['apilado'] : array(),
			isset( $draft['bultos'] ) && is_array( $draft['bultos'] ) ? $draft['bultos'] : array(),
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
			return '<p class="andr-dispatch__message">' . esc_html( $strings['preview_empty'] ) . '</p>';
		}

		$html = '<table class="andr-dispatch__table"><thead><tr>';

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
		return '<td data-col="' . esc_attr( $column ) . '"' . ( $charged ? ' class="andr-dispatch__cell--charged"' : '' ) . '>'
			. esc_html( self::format_preview_weight( $weight_kg ) )
			. '</td>';
	}

	/**
	 * @return array<string,string>
	 */
	public static function get_ui_strings() {
		return array(
			'mode_question'            => __( '¿Cómo se despacha?', 'andreani-shipping' ),
			'mode_single_title'        => __( 'En su caja', 'andreani-shipping' ),
			'mode_apilado_title'       => __( 'Se apilan', 'andreani-shipping' ),
			'mode_multibulto_title'    => __( 'Varias cajas', 'andreani-shipping' ),
			'box_single'               => __( 'Caja', 'andreani-shipping' ),
			'box_main_note'            => __( 'de los campos de arriba', 'andreani-shipping' ),
			'box_main_empty'           => __( 'Completá el peso y las medidas arriba', 'andreani-shipping' ),
			'sku_label'                => __( 'SKU', 'andreani-shipping' ),
			'sku_placeholder'          => __( 'Opcional', 'andreani-shipping' ),
			'status_ok'                => __( 'Entra en paquetería', 'andreani-shipping' ),
			'status_bigger'            => __( 'Envío grande (Bigger)', 'andreani-shipping' ),
			'status_missing'           => __( 'Faltan medidas', 'andreani-shipping' ),
			'box_status_ok'            => __( 'Paquetería', 'andreani-shipping' ),
			'box_status_bigger'        => __( 'Bigger', 'andreani-shipping' ),
			'box_status_missing'       => __( 'Falta configurar', 'andreani-shipping' ),
			'same_dims_warning'        => __( 'Esta caja tiene las mismas medidas que la principal. Si lo que pasa es que vendés varias unidades y se acomodan juntas, esto no es una unidad en varias cajas: elegí «Se apilan».', 'andreani-shipping' ),
			'switch_to_apilado'        => __( 'Cambiar a apilado', 'andreani-shipping' ),
			'apilado_invalid'          => class_exists( 'Andreani_Product_Apilado' )
				? Andreani_Product_Apilado::invalid_message()
				: '',
			'bultos_invalid'           => self::bultos_invalid_message(),
			'piece_incomplete'         => __( 'Completá el peso y las tres medidas de esta caja, o quitala.', 'andreani-shipping' ),
			/* translators: %s: número de caja */
			'piece_ignored'            => __( 'Completá la caja %s para verla.', 'andreani-shipping' ),
			/* translators: %s: números de caja, separados por coma */
			'piece_ignored_many'       => __( 'Completá las cajas %s para verlas.', 'andreani-shipping' ),
			'preview_unit_one'         => __( 'unidad', 'andreani-shipping' ),
			'preview_unit_many'        => __( 'unidades', 'andreani-shipping' ),
			'stack_up_to'              => __( 'Entran hasta', 'andreani-shipping' ),
			'stack_up_to_unit'         => __( 'por pila', 'andreani-shipping' ),
			'stack_adds_height'        => __( 'cada una suma', 'andreani-shipping' ),
			'stack_adds_height_unit'   => __( 'cm de alto', 'andreani-shipping' ),
			'stack_advanced'           => __( 'Avanzado', 'andreani-shipping' ),
			'stack_adds_width'         => __( 'Suma de ancho por unidad', 'andreani-shipping' ),
			'stack_adds_depth'         => __( 'Suma de fondo por unidad', 'andreani-shipping' ),
			'stack_adds_unit'          => __( 'cm', 'andreani-shipping' ),
			'piece_title'              => __( 'Caja', 'andreani-shipping' ),
			'piece_reference'          => __( 'Referencia', 'andreani-shipping' ),
			'piece_reference_hint'     => __( 'Ej. Base', 'andreani-shipping' ),
			'main_ref_hint'            => __( 'Ej. Interior', 'andreani-shipping' ),
			'piece_add'                => __( '+ Agregar caja', 'andreani-shipping' ),
			'piece_remove'             => __( 'Eliminar caja', 'andreani-shipping' ),
			'label_length'             => __( 'Longitud', 'andreani-shipping' ),
			'label_width'              => __( 'Anchura', 'andreani-shipping' ),
			'label_height'             => __( 'Altura', 'andreani-shipping' ),
			'label_weight'             => __( 'Peso', 'andreani-shipping' ),
			/* translators: %s: cantidad de unidades del pedido simulado, con su unidad */
			'preview_qty'              => __( 'Un pedido de', 'andreani-shipping' ),
			'preview_detail'           => __( 'Detalle técnico', 'andreani-shipping' ),
			'preview_help'             => sprintf(
				/* translators: %s: kilos por metro cúbico que se usan para calcular el peso aforado */
				__( 'El peso aforado es el que le corresponde al envío por el espacio que ocupa (%s kg por cada m³). Andreani cobra por el mayor de los dos pesos: en rojo, el que se cobra.', 'andreani-shipping' ),
				self::format_measure( Andreani_Api_Config::AFORO_KG_M3 )
			),
			'loader_preview'           => __( 'Armando la caja…', 'andreani-shipping' ),
			'preview_empty'            => __( 'Cargá el peso y las tres medidas del producto para ver cómo viaja.', 'andreani-shipping' ),
			'preview_col_units'        => __( 'Unidades', 'andreani-shipping' ),
			'preview_col_bultos'       => __( 'Bultos', 'andreani-shipping' ),
			'preview_col_volume'       => __( 'Volumen total', 'andreani-shipping' ),
			'preview_col_weight'       => __( 'Peso real', 'andreani-shipping' ),
			'preview_col_aforado'      => __( 'Peso aforado', 'andreani-shipping' ),
			'pack_title'               => __( 'Paquetería · 1 caja · 1 etiqueta', 'andreani-shipping' ),
			/* translators: %s: cantidad de unidades del pedido simulado */
			'pack_together'            => __( 'Las %s van juntas en una caja.', 'andreani-shipping' ),
			'pack_together_one'        => __( 'La unidad va en una caja.', 'andreani-shipping' ),
			'pack_stacked'             => __( 'Apiladas ocupan menos.', 'andreani-shipping' ),
			/* translators: 1: ancho, 2: fondo, 3: alto de la caja sugerida, en cm */
			'pack_hint'                => __( 'Caja sugerida: ~%1$s × %2$s × %3$s cm, con 1-2 cm de margen. Cobramos por el peso y el volumen de lo que va adentro, no por la caja: usá la más chica en la que entre.', 'andreani-shipping' ),
			'pack_too_big'             => __( 'No entra en una caja de paquetería.', 'andreani-shipping' ),
			/* translators: 1: ancho, 2: fondo, 3: alto de la caja más chica posible, en cm; 4: motivo */
			'pack_too_big_detail'      => __( 'La más chica posible mide ~%1$s × %2$s × %3$s cm: %4$s.', 'andreani-shipping' ),
			/* translators: 1: lado más largo en cm, 2: máximo permitido en cm */
			'pack_side_over'           => __( 'un lado mide %1$s cm (máx. %2$s)', 'andreani-shipping' ),
			/* translators: 1: suma de los lados en cm, 2: máximo permitido en cm */
			'pack_sum_over'            => __( 'sus lados suman %1$s cm (máx. %2$s)', 'andreani-shipping' ),
			/* translators: %s: kilos que se cobran */
			'pack_charged'             => __( 'Se cobra como %s kg · domicilio o sucursal', 'andreani-shipping' ),
			'cart_pack_together'       => __( 'Todo el pedido viaja junto en una sola caja, acomodado así.', 'andreani-shipping' ),
			'cart_big_mixed'           => __( 'Todo el pedido pasa a Bigger: también los productos chicos viajan como cajas propias.', 'andreani-shipping' ),
			'big_title_one'            => __( 'Bigger · 1 caja · 1 etiqueta', 'andreani-shipping' ),
			/* translators: %s: cantidad de cajas */
			'big_title_many'           => __( 'Bigger · %s cajas · %s etiquetas', 'andreani-shipping' ),
			/* translators: %s: motivo por el que no entra en paquetería */
			'big_because'              => __( 'Porque %s.', 'andreani-shipping' ),
			'box_word'                 => __( 'Caja', 'andreani-shipping' ),
			/* translators: %s: unidades de la pila */
			'box_units'                => __( '%s unidades', 'andreani-shipping' ),
			/* translators: %s: cantidad de cajas */
			'list_title'               => __( 'Este envío son %s cajas, cada una con su etiqueta', 'andreani-shipping' ),
			'big_home_only'            =>__( 'Solo a domicilio', 'andreani-shipping' ),
			'big_per_pile'             => __( 'una caja por pila', 'andreani-shipping' ),
			/* translators: 1: peso total en kg, 2: máximo de paquetería en kg */
			'why_weight'               => __( 'pesa %1$s kg (paquetería llega a %2$s)', 'andreani-shipping' ),
			/* translators: 1: lado más largo en cm, 2: máximo de paquetería en cm */
			'why_max_side'             => __( 'una caja mide %1$s cm de lado (paquetería llega a %2$s)', 'andreani-shipping' ),
			/* translators: 1: suma de los lados en cm, 2: máximo de paquetería en cm */
			'why_sum_sides'            => __( 'una caja suma %1$s cm de lados (paquetería llega a %2$s)', 'andreani-shipping' ),
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
	 * Lo que el módulo JS de la vista previa necesita para evaluar y dibujar:
	 * umbrales canónicos (kg/cm), aforo y los factores para pasar lo que el
	 * merchant tipea, que está en la unidad de la tienda, a cm y kg.
	 *
	 * @return array{limits:array,aforo:float,cm_factor:float,kg_factor:float}
	 */
	public static function get_preview_config() {
		return array(
			'limits'    => self::get_canonical_thresholds(),
			'aforo'     => (float) Andreani_Api_Config::AFORO_KG_M3,
			'cm_factor' => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( 1 ),
			'kg_factor' => (float) Andreani_Order_Mapper::convert_weight_to_unit( 1, 'kg' ),
			'dim_icons' => self::dim_icons(),
		);
	}

	/**
	 * Íconos de línea de los campos de medidas, por clave: length, width, height y weight.
	 *
	 * @return array<string,string>
	 */
	public static function dim_icons() {
		$paths = array(
			'length' => '<path d="M3 12h18M7 8l-4 4 4 4M17 8l4 4-4 4"/>',
			'width'  => '<path d="M14 4h6v6M10 20H4v-6M20 4l-7 7M4 20l7-7"/>',
			'height' => '<path d="M12 3v18M8 7l4-4 4 4M8 17l4 4 4-4"/>',
			'weight' => '<path d="M6.5 9h11l2 11h-15z"/><circle cx="12" cy="5.5" r="2.5"/>',
		);

		$icons = array();
		foreach ( $paths as $key => $path ) {
			$icons[ $key ] = '<svg class="andr-dim__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
		}

		return $icons;
	}

	/**
	 * Campo de medida: etiqueta arriba, ícono dentro del input y unidad como sufijo.
	 *
	 * @param string               $key   length, width, height o weight.
	 * @param string               $label Texto visible.
	 * @param string               $unit  Unidad de la tienda.
	 * @param array<string,string> $attrs Atributos del input.
	 * @return string HTML ya escapado.
	 */
	public static function dim_field( $key, $label, $unit, array $attrs ) {
		$icons = self::dim_icons();
		$html  = '';

		foreach ( $attrs as $name => $value ) {
			$html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
		}

		return '<label class="andr-dim">'
			. '<span class="andr-dim__label">' . esc_html( $label ) . '</span>'
			. '<span class="andr-dim__box">' . $icons[ $key ] . '<input type="number"' . $html . ' /><span class="andr-dim__unit">' . esc_html( $unit ) . '</span></span>'
			. '</label>';
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
			delete_post_meta( $post_id, self::MAIN_REF_META );
			return;
		}

		$rows   = self::posted_rows();
		$bultos = self::pieces_from_rows( $rows );

		if ( empty( $bultos ) ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( self::bultos_invalid_message() );
			}

			return;
		}

		if ( self::has_incomplete_rows( $rows ) ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( self::bultos_incomplete_message() );
			}

			return;
		}

		// update_post_meta desescapa el valor: sin wp_slash una comilla en la
		// referencia del bulto rompe el JSON que se guarda.
		update_post_meta( $post_id, self::META_KEY, wp_slash( wp_json_encode( $bultos ) ) );

		self::save_main_ref( $post_id, isset( $_POST['andreani_main_box_ref'] ) ? wp_unslash( $_POST['andreani_main_box_ref'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	/**
	 * @param mixed $value Referencia tipeada.
	 * @return string
	 */
	public static function sanitize_main_ref( $value ) {
		return mb_substr( trim( sanitize_text_field( (string) $value ) ), 0, self::MAIN_REF_MAX );
	}

	/**
	 * Guarda la referencia de la caja del producto; vacía borra la meta.
	 *
	 * @param int   $product_id ID del producto (o variación).
	 * @param mixed $value      Referencia tipeada.
	 * @return string La referencia que quedó guardada.
	 */
	public static function save_main_ref( $product_id, $value ) {
		$ref = self::sanitize_main_ref( $value );

		if ( '' === $ref ) {
			delete_post_meta( $product_id, self::MAIN_REF_META );
		} else {
			update_post_meta( $product_id, self::MAIN_REF_META, wp_slash( $ref ) );
		}

		return $ref;
	}

	/**
	 * @return bool Hay piezas y ninguna quedó a medias.
	 */
	public static function posted_pieces_are_valid() {
		$rows = self::posted_rows();

		return ! empty( self::pieces_from_rows( $rows ) ) && ! self::has_incomplete_rows( $rows );
	}

	/**
	 * @return array<int,array{name:string,height:mixed,width:mixed,depth:mixed,weight:mixed}>
	 */
	public static function posted_rows() {
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

		return $rows;
	}

	/**
	 * Una fila con algún dato pero sin peso o sin las tres medidas: no se puede
	 * guardar ni descartar en silencio. Una fila totalmente vacía no cuenta.
	 *
	 * @param array $rows Filas con height, width, depth y weight.
	 * @return bool
	 */
	public static function has_incomplete_rows( array $rows ) {
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$filled = 0;

			foreach ( array( 'weight', 'width', 'height', 'depth' ) as $field ) {
				if ( isset( $row[ $field ] ) && floatval( $row[ $field ] ) > 0 ) {
					++$filled;
				}
			}

			if ( $filled > 0 && $filled < 4 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return string
	 */
	public static function bultos_invalid_message() {
		return __( 'Para despachar en varias piezas cargá al menos una pieza con peso y las tres medidas completas.', 'andreani-shipping' );
	}

	/**
	 * @return string
	 */
	public static function bultos_incomplete_message() {
		return __( 'Hay cajas sin completar: cargales el peso y las tres medidas, o quitalas.', 'andreani-shipping' );
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
			array(
				'andreani-core-base',
				Andreani_Core_Assets::HANDLE_C_BADGE,
				Andreani_Core_Assets::HANDLE_C_BUTTON,
				Andreani_Core_Assets::HANDLE_C_DISPATCH,
				Andreani_Core_Assets::HANDLE_C_LOADER,
			),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'andreani-product-bultos',
			ANDREANI_PLUGIN_URL . 'includes/assets/js/product-bultos.js',
			array( 'jquery', Andreani_Core_Assets::HANDLE_BOX_PREVIEW ),
			ANDREANI_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'andreani-product-bultos',
			'AndreaniBultosConfig',
			array(
				'preview'       => array_merge( self::get_preview_config(), array( 'i18n' => self::get_ui_strings() ) ),
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'nonce_preview' => wp_create_nonce( self::PREVIEW_NONCE ),
				'nonce_open'    => wp_create_nonce( self::BOX_OPEN_NONCE ),
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

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return bool Una variación sin modo propio guardado hereda del padre.
	 */
	public static function inherits_from_parent( $product ) {
		return $product->is_type( 'variation' ) && '' === (string) get_post_meta( $product->get_id(), self::MODE_META, true );
	}

	public static function get_effective_sku( $product ) {
		$own = (string) get_post_meta( $product->get_id(), self::SKU_META_KEY, true );

		return '' !== $own ? $own : (string) $product->get_sku( 'edit' );
	}

	public static function sku_to_store( $submitted, $woo_sku ) {
		$submitted = trim( (string) $submitted );

		return $submitted === (string) $woo_sku ? '' : $submitted;
	}
}
