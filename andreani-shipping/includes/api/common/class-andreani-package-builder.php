<?php
/**
 * Armado de bultos: única fuente de la forma del bulto que se declara a
 * Andreani, tanto al cotizar como al dar de alta el envío.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/admin/class-andreani-product-apilado.php';

class Andreani_Package_Builder {

	/**
	 * Bultos que ocupan N unidades de un producto en unidades canónicas (cm y kg).
	 *
	 * Las dimensiones y el peso caen al mínimo permisivo 1 cuando el producto no
	 * los tiene cargados: un bulto nunca se declara con un lado en cero.
	 *
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades en el carrito o en el ítem de la orden.
	 * @return array<int,array{width:float,height:float,depth:float,weight_kg:float,units:int}>
	 */
	public static function build( $product, $quantity ) {
		$base = array(
			'width'  => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_width() ) ?: 1,
			'height' => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_height() ) ?: 1,
			'depth'  => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_length() ) ?: 1,
		);

		$weight_kg = Andreani_Order_Mapper::convert_weight_to_unit( $product->get_weight() ?: 1, 'kg' );

		return self::stack( $base, $weight_kg, self::resolve_apilado( $product ), $quantity );
	}

	/**
	 * Config de apilado que le aplica al producto, ya resuelta la exclusión con
	 * los bultos adicionales. Vacía significa "sin apilado", y es lo que miran
	 * los consumidores para saber si cada bulto es una pila o una unidad suelta.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return array
	 */
	public static function resolve_apilado( $product ) {
		$product_id = $product->get_id();

		$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product_id );

		if ( empty( $bultos_adicionales ) && $product->is_type( 'variation' ) ) {
			$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product->get_parent_id() );
		}

		if ( ! empty( $bultos_adicionales ) ) {
			return array();
		}

		$config = Andreani_Product_Apilado::get_apilado( $product_id );

		return Andreani_Product_Apilado::is_valid( $config ) ? $config : array();
	}

	/**
	 * Reparte $quantity unidades en pilas de hasta maxStackableUnits, donde la primera
	 * unidad de cada pila ocupa las dimensiones base y cada unidad extra suma
	 * los incrementos configurados.
	 *
	 * Sin config válida devuelve una sola entrada con las dimensiones base y
	 * units = $quantity, que representa las unidades sueltas de hoy.
	 *
	 * @param array $base      Dimensiones de una unidad en cm: width, height, depth.
	 * @param float $weight_kg Peso de una unidad en kg.
	 * @param array $config    Config de apilado, o array vacío si no aplica.
	 * @param int   $quantity  Unidades a repartir.
	 * @return array<int,array{width:float,height:float,depth:float,weight_kg:float,units:int}>
	 */
	public static function stack( array $base, $weight_kg, array $config, $quantity ) {
		$quantity = (int) $quantity;

		if ( $quantity <= 0 ) {
			return array();
		}

		$width     = isset( $base['width'] ) ? (float) $base['width'] : 0.0;
		$height    = isset( $base['height'] ) ? (float) $base['height'] : 0.0;
		$depth     = isset( $base['depth'] ) ? (float) $base['depth'] : 0.0;
		$weight_kg = (float) $weight_kg;

		if ( ! Andreani_Product_Apilado::is_valid( $config ) ) {
			return array(
				array(
					'width'     => $width,
					'height'    => $height,
					'depth'     => $depth,
					'weight_kg' => $weight_kg,
					'units'     => $quantity,
				),
			);
		}

		$max_stackable_units   = (int) $config['maxStackableUnits'];
		$unit_increment_width  = (float) $config['unitIncrementWidth'];
		$unit_increment_height = (float) $config['unitIncrementHeight'];
		$unit_increment_depth  = (float) $config['unitIncrementDepth'];

		$pilas_completas = intdiv( $quantity, $max_stackable_units );
		$resto           = $quantity % $max_stackable_units;

		$unidades_por_pila = array_fill( 0, $pilas_completas, $max_stackable_units );

		if ( $resto > 0 ) {
			$unidades_por_pila[] = $resto;
		}

		$bultos = array();

		foreach ( $unidades_por_pila as $units ) {
			$extra = $units - 1;

			$bultos[] = array(
				'width'     => $width + $unit_increment_width * $extra,
				'height'    => $height + $unit_increment_height * $extra,
				'depth'     => $depth + $unit_increment_depth * $extra,
				'weight_kg' => $weight_kg * $units,
				'units'     => $units,
			);
		}

		return $bultos;
	}
}
