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

	const BOX_REF_META  = '_andreani_box_ref';
	const BOX_REF_FIELD = 'andreani_box_ref';
	const NONCE_KEY = 'andreani_bultos_nonce';

	const MODE_FIELD      = 'andreani_dispatch_mode';
	const MODE_META       = '_andreani_dispatch_mode';
	const MODE_SINGLE     = 'single';
	const MODE_APILADO    = 'apilado';
	const MODE_MULTIBULTO = 'multibulto';

	const UNITS_FIELD = 'andreani_units_per_box';
	const UNITS_META  = '_andreani_units_per_box';
	const UNITS_MAX   = 9999;

	const OVERFLOW_FIELD    = 'andreani_overflow';
	const OVERFLOW_META     = '_andreani_overflow';
	const OVERFLOW_INHERIT  = 'inherit';
	const OVERFLOW_TOGETHER = 'together';
	const OVERFLOW_SEPARATE = 'separate';

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
		$product = wc_get_product( $product_id );

		if ( ! empty( self::get_bultos( $product_id ) ) || ( $product && Andreani_Package_Builder::assigned_set( $product ) ) ) {
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
	 * @param mixed $value Valor tipeado.
	 * @return int|null Entero de 1 a UNITS_MAX; 1 si vino vacío; null si es inválido.
	 */
	public static function parse_units_per_box( $value ) {
		$value = is_string( $value ) ? trim( $value ) : $value;

		if ( '' === $value || null === $value ) {
			return 1;
		}

		if ( ! is_numeric( $value ) || (float) $value != (int) $value || (int) $value < 1 ) {
			return null;
		}

		return min( self::UNITS_MAX, (int) $value );
	}

	/**
	 * @param mixed $value Valor guardado o tipeado.
	 * @return string inherit, together o separate.
	 */
	public static function sanitize_overflow( $value ) {
		return in_array( $value, array( self::OVERFLOW_TOGETHER, self::OVERFLOW_SEPARATE ), true )
			? $value
			: self::OVERFLOW_INHERIT;
	}

	/**
	 * @return bool Si la cuenta cotiza con la preferencia de cajas: force_bigger no existe en la API corporativa.
	 */
	public static function overflow_supported() {
		if ( ! class_exists( 'Andreani_Settings_Service' ) || ! method_exists( 'Andreani_Settings_Service', 'get_client_type' ) ) {
			return true;
		}

		return 'corporativo' !== Andreani_Settings_Service::get_client_type();
	}

	/**
	 * La capacidad solo existe en caja única y la preferencia no aplica a varias
	 * cajas, que siempre es Bigger: lo que no corresponde al modo se borra.
	 *
	 * @param int    $product_id ID del producto (o variación).
	 * @param string $mode       Modo de despacho.
	 * @param int    $units      Unidades por caja.
	 * @param string $overflow   inherit, together o separate.
	 */
	public static function save_capacity( $product_id, $mode, $units, $overflow ) {
		if ( self::MODE_SINGLE === $mode && (int) $units > 1 ) {
			update_post_meta( $product_id, self::UNITS_META, (int) $units );
		} else {
			delete_post_meta( $product_id, self::UNITS_META );
		}

		$overflow = self::sanitize_overflow( $overflow );

		if ( self::MODE_MULTIBULTO !== $mode && self::OVERFLOW_INHERIT !== $overflow ) {
			update_post_meta( $product_id, self::OVERFLOW_META, $overflow );
		} else {
			delete_post_meta( $product_id, self::OVERFLOW_META );
		}
	}

	/**
	 * @param array $contratos Contratos de la cuenta.
	 * @return bool
	 */
	public static function contracts_have_bigger( array $contratos ) {
		foreach ( $contratos as $contrato ) {
			if ( is_array( $contrato ) && isset( $contrato['modoDeEntregaNombre'] ) && 'bigger' === strtolower( (string) $contrato['modoDeEntregaNombre'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool|null null cuando todavía no se conocen los contratos de la cuenta.
	 */
	public static function account_has_bigger() {
		if ( ! class_exists( 'Andreani_Settings_Service' ) || ! class_exists( 'Andreani_Client_Type' ) ) {
			return null;
		}

		$type_info = Andreani_Client_Type::from_id( Andreani_Settings_Service::get_client_type() );
		$info      = $type_info ? get_option( $type_info->info_option_name(), array() ) : array();

		if ( empty( $info['contratos'] ) || ! is_array( $info['contratos'] ) ) {
			return null;
		}

		return self::contracts_have_bigger( $info['contratos'] );
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

		return Andreani_Package_Builder::preview( $base, $weight_kg, $apilado, $bultos, self::PREVIEW_QUANTITIES, self::draft_units_per_box( $draft ) );
	}

	private static function draft_units_per_box( array $draft ) {
		return isset( $draft['units_per_box'] ) ? max( 1, (int) $draft['units_per_box'] ) : 1;
	}

	/**
	 * @param array $draft    Borrador del editor.
	 * @param int   $quantity Unidades del pedido simulado.
	 * @return bool Si el pedido simulado se cotiza como Bigger por la regla de cajas.
	 */
	public static function preview_force_bigger_from_draft( array $draft, $quantity ) {
		$mode = isset( $draft['mode'] ) ? $draft['mode'] : self::MODE_SINGLE;

		if ( self::MODE_MULTIBULTO === $mode ) {
			return Andreani_Package_Builder::should_force_bigger( true, self::OVERFLOW_TOGETHER, 0 );
		}

		$groups  = self::preview_package_groups_from_draft( $draft, $quantity );
		$boxes    = array_sum( array_column( $groups, 'count' ) );
		$overflow = isset( $draft['overflow'] ) ? self::sanitize_overflow( $draft['overflow'] ) : self::OVERFLOW_INHERIT;

		return Andreani_Package_Builder::should_force_bigger( false, $overflow, $boxes );
	}

	public static function preview_packages_from_draft( array $draft, $quantity ) {
		$draft = self::draft_with_package( $draft );

		list( $base, $weight_kg, $apilado, $bultos ) = self::preview_inputs( $draft );

		return Andreani_Package_Builder::draw_packages( $base, $weight_kg, $apilado, $bultos, $quantity, isset( $draft['main_ref'] ) ? (string) $draft['main_ref'] : '', self::draft_units_per_box( $draft ) );
	}

	private static function preview_package_groups_from_draft( array $draft, $quantity ) {
		list( $base, $weight_kg, $apilado, $bultos ) = self::preview_inputs( $draft );

		return Andreani_Package_Builder::draw_package_groups( $base, $weight_kg, $apilado, $bultos, $quantity, '', self::draft_units_per_box( $draft ) );
	}

	/**
	 * @param mixed  $value Id de paquete tal como llegó del formulario.
	 * @param string $mode  Modo de despacho del producto.
	 * @return string El id si existe y corresponde al modo (juego en varias cajas, caja o bolsa en el resto); vacío si no.
	 */
	public static function sanitize_box_ref( $value, $mode ) {
		$package = is_string( $value ) ? Andreani_Store_Boxes::find( trim( $value ) ) : null;

		if ( ! $package || Andreani_Store_Boxes::is_set( $package ) !== ( self::MODE_MULTIBULTO === $mode ) ) {
			return '';
		}

		return $package['id'];
	}

	/**
	 * Asigna un paquete guardado a un producto. Un juego lo pasa a «Varias cajas»:
	 * lo que no corresponde a ese modo se borra, como al guardar el editor.
	 *
	 * @param int   $product_id ID del producto (o del padre, si la variación hereda).
	 * @param array $package    Paquete guardado.
	 */
	public static function assign_package( $product_id, array $package ) {
		self::save_box_ref( $product_id, $package['id'] );

		if ( Andreani_Store_Boxes::is_set( $package ) ) {
			foreach ( array( self::META_KEY, self::MAIN_REF_META, self::UNITS_META, self::OVERFLOW_META ) as $meta_key ) {
				delete_post_meta( $product_id, $meta_key );
			}

			delete_post_meta( $product_id, Andreani_Product_Apilado::META_KEY );
		}
	}

	/**
	 * Guarda el paquete asignado al producto; vacío borra la meta.
	 *
	 * @param int    $product_id ID del producto (o variación).
	 * @param string $ref        Id del paquete ya saneado.
	 */
	public static function save_box_ref( $product_id, $ref ) {
		if ( '' === $ref ) {
			delete_post_meta( $product_id, self::BOX_REF_META );
		} else {
			update_post_meta( $product_id, self::BOX_REF_META, $ref );
		}
	}

	/**
	 * Un borrador con paquete asignado toma de él las medidas (y, con un juego,
	 * el peso y las cajas adicionales), como lo hace el cálculo con lo guardado.
	 *
	 * @param array $draft Borrador del editor, con box_ref opcional.
	 * @return array
	 */
	public static function draft_with_package( array $draft ) {
		$ref     = isset( $draft['box_ref'] ) ? (string) $draft['box_ref'] : '';
		$package = '' !== $ref ? Andreani_Store_Boxes::find( $ref ) : null;

		if ( ! $package ) {
			return $draft;
		}

		$side = static function ( $cm ) {
			return Andreani_Order_Mapper::convert_cm_to_dimension_unit( $cm );
		};

		if ( Andreani_Store_Boxes::is_set( $package ) ) {
			$first = $package['boxes'][0];

			$draft['length']   = $side( $first['length'] );
			$draft['width']    = $side( $first['width'] );
			$draft['height']   = $side( $first['height'] );
			$draft['weight']   = Andreani_Order_Mapper::convert_grams_to_weight_unit( $first['weight'] );
			$draft['main_ref'] = $first['ref'];
			$draft['bultos']   = array();

			foreach ( array_slice( $package['boxes'], 1 ) as $position => $box ) {
				$draft['bultos'][] = array(
					'name'   => '' !== $box['ref'] ? $box['ref'] : sprintf( 'Bulto %d', $position + 2 ),
					'height' => (float) $box['height'],
					'width'  => (float) $box['width'],
					'depth'  => (float) $box['length'],
					'weight' => (float) $box['weight'],
				);
			}

			return $draft;
		}

		$draft['length'] = $side( $package['length'] );
		$draft['width']  = $side( $package['width'] );
		$draft['height'] = $side( $package['height'] );

		return $draft;
	}

	private static function preview_inputs( array $draft ) {
		$draft = self::draft_with_package( $draft );

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
			'mode_question'            => __( '¿Cómo viaja este producto?', 'andreani-shipping' ),
			'kind_box_title'           => __( 'En una caja', 'andreani-shipping' ),
			'kind_box_hint'            => __( 'Entran 1 o más', 'andreani-shipping' ),
			'kind_stack_title'         => __( 'Se apilan', 'andreani-shipping' ),
			'kind_stack_hint'          => __( 'La caja crece', 'andreani-shipping' ),
			'kind_multi_title'         => __( 'Viene en varias cajas', 'andreani-shipping' ),
			'kind_multi_hint'          => __( 'Ej.: un placard', 'andreani-shipping' ),
			'multi_note'               => __( 'Cada caja lleva su etiqueta: viaja como Bigger.', 'andreani-shipping' ),
			'mode_single_title'        => __( 'En su caja', 'andreani-shipping' ),
			'mode_apilado_title'       => __( 'Se apilan', 'andreani-shipping' ),
			'mode_multibulto_title'    => __( 'Varias cajas', 'andreani-shipping' ),
			'box_single'               => __( 'Caja', 'andreani-shipping' ),
			'dims_title'               => __( 'Medidas de la caja', 'andreani-shipping' ),
			'sku_label'                => __( 'SKU (opcional)', 'andreani-shipping' ),
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
			/* translators: %s: número de caja */
			'piece_complete'           => __( 'Completá las medidas de la caja %s', 'andreani-shipping' ),
			'piece_ignored'            => __( 'Completá la caja %s para verla.', 'andreani-shipping' ),
			/* translators: %s: números de caja, separados por coma */
			'piece_ignored_many'       => __( 'Completá las cajas %s para verlas.', 'andreani-shipping' ),
			'preview_unit_one'         => __( 'unidad', 'andreani-shipping' ),
			'preview_unit_many'        => __( 'unidades', 'andreani-shipping' ),
			'stack_up_to'             => __( 'Se apilan hasta', 'andreani-shipping' ),
			'stack_adds_height'        => __( 'y cada una suma', 'andreani-shipping' ),
			'stack_adds_height_unit'   => __( 'cm de alto', 'andreani-shipping' ),
			'stack_advanced'           => __( 'Ancho y fondo', 'andreani-shipping' ),
			'stack_adds_width'         => __( 'Suma de ancho por unidad', 'andreani-shipping' ),
			'stack_adds_depth'         => __( 'Suma de fondo por unidad', 'andreani-shipping' ),
			'stack_adds_unit'          => __( 'cm', 'andreani-shipping' ),
			'piece_title'              => __( 'Caja', 'andreani-shipping' ),
			'piece_reference'          => __( 'Referencia', 'andreani-shipping' ),
			'piece_reference_hint'     => __( 'Ej. Base', 'andreani-shipping' ),
			'main_ref_hint'            => __( 'Ej. Base', 'andreani-shipping' ),
			'piece_add'                => __( '+ Agregar caja', 'andreani-shipping' ),
			'piece_remove'             => __( 'Quitar caja', 'andreani-shipping' ),
			'label_length'             => __( 'Largo', 'andreani-shipping' ),
			'label_width'              => __( 'Ancho', 'andreani-shipping' ),
			'label_height'             => __( 'Alto', 'andreani-shipping' ),
			'label_weight'             => __( 'Peso', 'andreani-shipping' ),
			/* translators: %s: cantidad de unidades del pedido simulado, con su unidad */
			'preview_qty'              => __( 'Pedido de', 'andreani-shipping' ),
			'loader_preview'           => __( 'Armando la caja…', 'andreani-shipping' ),
			'preview_empty'            => __( 'Cargá el peso y las tres medidas del producto para ver cómo viaja.', 'andreani-shipping' ),
			'preview_col_units'        => __( 'Unidades', 'andreani-shipping' ),
			'preview_col_bultos'       => __( 'Bultos', 'andreani-shipping' ),
			'preview_col_volume'       => __( 'Volumen total', 'andreani-shipping' ),
			'preview_col_weight'       => __( 'Peso real', 'andreani-shipping' ),
			'preview_col_aforado'      => __( 'Peso aforado', 'andreani-shipping' ),
			'pack_title'               => __( 'Paquetería · 1 etiqueta', 'andreani-shipping' ),
			'pack_joined'              => __( 'Van juntas en un bulto', 'andreani-shipping' ),
			/* translators: %s: suma de los lados del bulto en cm */
			'size_sum'                 => __( 'Por tamaño: suma %s cm', 'andreani-shipping' ),
			/* translators: %s: lado más largo del bulto en cm */
			'size_side'                => __( 'Por tamaño: lado %s cm', 'andreani-shipping' ),
			/* translators: 1: nombre de la caja de la tienda, 2: medidas en cm */
			'box_use'                  => __( 'Usá tu «%1$s» (%2$s cm)', 'andreani-shipping' ),
			/* translators: 1: largo, 2: ancho, 3: alto de la caja aproximada, en cm */
			'box_suggested'            => __( 'Caja sugerida ~%1$s × %2$s × %3$s cm', 'andreani-shipping' ),
			'box_no_fit'               => __( 'No entra en ninguna de tus cajas', 'andreani-shipping' ),
			'box_load'                 => __( 'Guardá tus paquetes para que te digamos cuál usar', 'andreani-shipping' ),
			'cart_big_mixed'           => __( 'Todo el pedido pasa a Bigger: también los productos chicos viajan como cajas propias.', 'andreani-shipping' ),
			'labels_one_pack'          => __( '1 etiqueta · Paquetería', 'andreani-shipping' ),
			'labels_one_big'           => __( '1 etiqueta · Bigger', 'andreani-shipping' ),
			/* translators: %s: cantidad de etiquetas */
			'labels_many_big'          => __( '%s etiquetas · Bigger', 'andreani-shipping' ),
			'pack_home_or_branch'      => __( 'Domicilio o sucursal', 'andreani-shipping' ),
			'chip_boxes_one'           => __( '1 caja', 'andreani-shipping' ),
			/* translators: %s: cantidad de cajas */
			'chip_boxes_many'          => __( '%s cajas', 'andreani-shipping' ),
			'chip_labels_one'          => __( '1 etiqueta', 'andreani-shipping' ),
			/* translators: %s: cantidad de etiquetas */
			'chip_labels_many'         => __( '%s etiquetas', 'andreani-shipping' ),
			/* translators: %s: nombre del producto */
			'cart_forced_multi'        => __( 'Bigger porque «%s» viene en varias cajas.', 'andreani-shipping' ),
			/* translators: %s: nombre del producto */
			'cart_forced_separate'     => __( 'Bigger porque «%s» lleva cada caja con su etiqueta.', 'andreani-shipping' ),
			'cart_rest_together'       => __( 'Lo demás va junto en una caja.', 'andreani-shipping' ),
			'box_word'                 => __( 'Caja', 'andreani-shipping' ),
			/* translators: %s: unidades de la pila */
			'box_units'                => __( '%s unidades', 'andreani-shipping' ),
			'box_unit'                 => __( '1 unidad', 'andreani-shipping' ),
			/* translators: 1: nombre del juego guardado, 2: referencias de sus cajas */
			'pkg_use_set'              => __( 'Usá tu juego «%1$s» · %2$s', 'andreani-shipping' ),
			/* translators: %s: nombre de la caja o bolsa guardada */
			'pkg_in_box'               => __( 'En tu «%s»', 'andreani-shipping' ),
			/* translators: %s: nombre de la caja o bolsa guardada */
			'pkg_use_box'              => __( 'Usá tu «%s»', 'andreani-shipping' ),
			/* translators: %s: cantidad de cajas */
			'list_title'               => __( 'Este envío son %s cajas, cada una con su etiqueta', 'andreani-shipping' ),
			'big_home_only'            =>__( 'Solo a domicilio', 'andreani-shipping' ),
			'big_per_pile'             => __( 'una caja por pila', 'andreani-shipping' ),
			/* translators: 1: peso total en kg, 2: máximo de paquetería en kg */
			'why_weight'               => __( 'Pesa %1$s kg', 'andreani-shipping' ),
			/* translators: 1: lado más largo en cm, 2: máximo de paquetería en cm */
			'why_max_side'             => __( 'Un lado mide %1$s cm', 'andreani-shipping' ),
			/* translators: 1: suma de los lados en cm, 2: máximo de paquetería en cm */
			'why_sum_sides'            => __( 'Lados suman %1$s cm', 'andreani-shipping' ),
			/* translators: %d: unidades que entran por caja */
			'grid_up_to'               => __( 'Hasta %d por caja', 'andreani-shipping' ),
			'grid_one_per_box'         => __( '1 por caja', 'andreani-shipping' ),
			/* translators: %d: unidades por pila */
			'grid_stacked'             => __( 'Se apilan de a %d', 'andreani-shipping' ),
			/* translators: %d: cajas que forman una unidad */
			'grid_in_boxes'            => __( 'Viene en %d cajas', 'andreani-shipping' ),
			'grid_each_label'          => __( 'cada caja con su etiqueta', 'andreani-shipping' ),
			/* translators: %d: unidades desde las que viaja como Bigger */
			'grid_bigger_from'         => __( 'Bigger desde %d u.', 'andreani-shipping' ),
			'capacity_label'           => __( '¿Cuántos entran en una caja?', 'andreani-shipping' ),
			'units_less'               => __( 'Menos', 'andreani-shipping' ),
			'units_more'               => __( 'Más', 'andreani-shipping' ),
			'overflow_together'        => __( 'Paquetería', 'andreani-shipping' ),
			'overflow_separate'        => __( 'Bigger', 'andreani-shipping' ),
			'overflow_question'        => __( 'Si te compran más de lo que entra en una caja', 'andreani-shipping' ),
			'opt_together_title'  => __( 'Todo junto', 'andreani-shipping' ),
			'opt_together_hint'   => __( '1 etiqueta · Paquetería', 'andreani-shipping' ),
			'opt_separate_title'  => __( 'Cada caja con su etiqueta', 'andreani-shipping' ),
			'opt_separate_hint'   => __( 'Bigger · solo a domicilio', 'andreani-shipping' ),
			'labels_per_box'           => __( 'Cada caja con su etiqueta', 'andreani-shipping' ),
			'labels_for_all'           => __( 'Todo junto', 'andreani-shipping' ),
			'no_bigger'                => __( 'Tu cuenta no tiene Bigger', 'andreani-shipping' ),
			'units_invalid'            => __( 'Las unidades por caja tienen que ser un número entero de 1 o más.', 'andreani-shipping' ),
			'pkg_label'                => __( 'Paquete', 'andreani-shipping' ),
			'pkg_label_set'            => __( 'Juego de cajas', 'andreani-shipping' ),
			'pkg_manual'               => __( 'Medidas a mano', 'andreani-shipping' ),
			'pkg_manual_set'           => __( 'Cajas a mano', 'andreani-shipping' ),
			'pkg_hint_box'             => __( 'Las medidas salen del paquete. El peso es el del producto.', 'andreani-shipping' ),
			'pkg_hint_set'             => __( 'Cada caja del juego trae sus medidas y su peso.', 'andreani-shipping' ),
			'pkg_save'                 => __( 'Guardar como paquete', 'andreani-shipping' ),
			'pkg_save_set'             => __( 'Guardar como juego de cajas', 'andreani-shipping' ),
			/* translators: %s: nombre del paquete guardado */
			'pkg_suggest'              => __( 'Coincide con «%s»:', 'andreani-shipping' ),
			'pkg_suggest_use'          => __( 'usarlo', 'andreani-shipping' ),
			'pkg_deleted'              => __( 'Paquete eliminado: elegí otro.', 'andreani-shipping' ),
			'pkg_modal_title'          => __( 'Guardar como paquete', 'andreani-shipping' ),
			'pkg_modal_title_set'      => __( 'Guardar como juego de cajas', 'andreani-shipping' ),
			'pkg_name'                 => __( 'Nombre', 'andreani-shipping' ),
			'pkg_name_hint'            => __( 'Ej. Caja chica', 'andreani-shipping' ),
			'pkg_name_hint_set'        => __( 'Ej. Aire acondicionado', 'andreani-shipping' ),
			'pkg_cancel'               => __( 'Cancelar', 'andreani-shipping' ),
			'pkg_confirm'              => __( 'Guardar y usar', 'andreani-shipping' ),
			'pkg_error'                => __( 'No pudimos guardar el paquete. Probá de nuevo.', 'andreani-shipping' ),
			'pkg_incomplete'           => __( 'Completá las medidas antes de guardarlo como paquete.', 'andreani-shipping' ),
			/* translators: %d: cantidad de cajas del juego */
			'pkg_set_count'            => __( '%d cajas', 'andreani-shipping' ),
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
			'overflow_supported' => self::overflow_supported(),
			'cm_factor' => (float) Andreani_Order_Mapper::convert_cm_to_dimension_unit( 1 ),
			'kg_factor' => (float) Andreani_Order_Mapper::convert_weight_to_unit( 1, 'kg' ),
			'dim_icons' => self::dim_icons(),
			'store_boxes' => Andreani_Store_Boxes::singles(),
			'packages'    => Andreani_Store_Boxes::for_display(),
			'package_api' => array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'action'   => Andreani_Boxes_Page::ACTION_ADD,
				'nonce'    => wp_create_nonce( Andreani_Boxes_Page::NONCE ),
				'unit'     => get_option( 'woocommerce_dimension_unit', 'cm' ),
				'weight_unit' => get_option( 'woocommerce_weight_unit', 'kg' ),
			),
			'boxes_url'   => class_exists( 'Andreani_Admin_Menu' ) ? Andreani_Admin_Menu::boxes_url() : '',
			'service_icons' => array(
				'paqueteria' => self::service_icon_url( 'paqueteria' ),
				'bigger'     => self::service_icon_url( 'bigger' ),
			),
		);
	}

	/**
	 * @param string $service paqueteria o bigger.
	 * @return string
	 */
	public static function service_icon_url( $service ) {
		return ANDREANI_PLUGIN_URL . 'includes/assets/img/services/' . $service . '.svg';
	}

	/**
	 * @param string $service paqueteria o bigger.
	 * @return string Imagen decorativa lista para imprimir.
	 */
	public static function service_icon( $service ) {
		return '<img class="andr-service-icon" src="' . esc_url( self::service_icon_url( $service ) ) . '" alt="" aria-hidden="true">';
	}

	/**
	 * La elección del producto: separate guarda separate, todo lo demás deja inherit.
	 *
	 * @param string $raw inherit, together o separate: lo guardado en el producto.
	 * @return array{on:bool,stored:string}
	 */
	public static function exception_view( $raw ) {
		$on = self::OVERFLOW_SEPARATE === self::sanitize_overflow( $raw );

		return array(
			'on'     => $on,
			'stored' => $on ? self::OVERFLOW_SEPARATE : self::OVERFLOW_INHERIT,
		);
	}

	/**
	 * @param string $effective together o separate.
	 * @return string "Todo junto" o "Cada caja con su etiqueta".
	 */
	public static function labels_text( $effective ) {
		$strings = self::get_ui_strings();

		return self::OVERFLOW_SEPARATE === $effective ? $strings['labels_per_box'] : $strings['labels_for_all'];
	}

	/**
	 * Dibujo decorativo de las tarjetas: cajas con o sin etiqueta y las etiquetas sueltas.
	 *
	 * @param string     $key  together, separate, box, apilado, multi, label_one o label_many.
	 * @param array|null $size Ancho y alto si no son los de la tarjeta.
	 * @return string SVG ya escapado.
	 */
	public static function art( $key, $size = null ) {
		$art = array(
			'together'   => array( 150, 90 ),
			'separate'   => array( 150, 90 ),
			'box'        => array( 110, 70 ),
			'apilado'    => array( 110, 70 ),
			'multi'      => array( 110, 70 ),
			'label_one'  => array( 44, 36 ),
			'label_many' => array( 70, 36 ),
		);

		if ( ! isset( $art[ $key ] ) ) {
			return '';
		}

		list( $width, $height ) = is_array( $size ) ? array( (int) $size[0], (int) $size[1] ) : $art[ $key ];

		return '<svg class="andr-art andr-art--' . esc_attr( $key ) . '" width="' . $width . '" height="' . $height . '" data-andr-art="' . esc_attr( $key ) . '" aria-hidden="true" focusable="false"></svg>';
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

		$mode_is_invalid = ( self::MODE_APILADO === $mode && class_exists( 'Andreani_Product_Apilado' ) && ! Andreani_Product_Apilado::is_valid( Andreani_Product_Apilado::posted_config() ) )
			|| ( self::MODE_MULTIBULTO === $mode && ! self::posted_pieces_are_valid() );

		$box_ref = isset( $_POST[ self::BOX_REF_FIELD ] ) ? self::sanitize_box_ref( sanitize_text_field( wp_unslash( $_POST[ self::BOX_REF_FIELD ] ) ), $mode ) : null;

		if ( self::MODE_MULTIBULTO === $mode && '' !== (string) $box_ref ) {
			$this->save_posted_capacity( $post_id, $mode );
			self::save_box_ref( $post_id, $box_ref );
			delete_post_meta( $post_id, self::META_KEY );
			delete_post_meta( $post_id, self::MAIN_REF_META );
			return;
		}

		if ( ! $mode_is_invalid ) {
			$this->save_posted_capacity( $post_id, $mode );
		}

		if ( self::MODE_MULTIBULTO !== $mode ) {
			if ( self::MODE_APILADO === $mode && class_exists( 'Andreani_Product_Apilado' ) && ! Andreani_Product_Apilado::is_valid( Andreani_Product_Apilado::posted_config() ) ) {
				return;
			}

			if ( null !== $box_ref ) {
				self::save_box_ref( $post_id, $box_ref );
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

		if ( null !== $box_ref ) {
			self::save_box_ref( $post_id, '' );
		}

		self::save_main_ref( $post_id, isset( $_POST['andreani_main_box_ref'] ) ? wp_unslash( $_POST['andreani_main_box_ref'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	private function save_posted_capacity( $post_id, $mode ) {
		if ( ! isset( $_POST[ self::UNITS_FIELD ] ) && ! isset( $_POST[ self::OVERFLOW_FIELD ] ) ) {
			return;
		}

		$units = self::parse_units_per_box( isset( $_POST[ self::UNITS_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::UNITS_FIELD ] ) ) : '' );

		if ( null === $units ) {
			if ( class_exists( 'WC_Admin_Meta_Boxes' ) ) {
				WC_Admin_Meta_Boxes::add_error( self::get_ui_strings()['units_invalid'] );
			}

			return;
		}

		self::save_capacity(
			$post_id,
			$mode,
			$units,
			isset( $_POST[ self::OVERFLOW_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::OVERFLOW_FIELD ] ) ) : ''
		);
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
			'weight' => Andreani_Order_Mapper::round_measure( Andreani_Order_Mapper::convert_weight_to_unit( $weight, 'gr' ) ),
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
				'height' => Andreani_Order_Mapper::round_measure( Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['height'] ) ? $bulto['height'] : 0 ) ),
				'width'  => Andreani_Order_Mapper::round_measure( Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['width'] ) ? $bulto['width'] : 0 ) ),
				'depth'  => Andreani_Order_Mapper::round_measure( Andreani_Order_Mapper::convert_cm_to_dimension_unit( isset( $bulto['depth'] ) ? $bulto['depth'] : 0 ) ),
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
				Andreani_Core_Assets::HANDLE_C_MODAL,
				Andreani_Core_Assets::HANDLE_C_PACKAGE,
				Andreani_Core_Assets::HANDLE_C_LOADER,
			),
			ANDREANI_PLUGIN_VERSION
		);

		wp_enqueue_script(
			'andreani-product-bultos',
			ANDREANI_PLUGIN_URL . 'includes/assets/js/product-bultos.js',
			array( 'jquery', Andreani_Core_Assets::HANDLE_BOX_PREVIEW, Andreani_Core_Assets::HANDLE_PACKAGE_PICKER ),
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
