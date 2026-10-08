<?php
/**
 * Armado de bultos: única fuente de la forma del bulto que se declara a
 * Andreani, tanto al cotizar como al dar de alta el envío.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/admin/class-andreani-product-apilado.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Package_Builder {

	const MIN_WEIGHT_GRAMS = 1;

	/**
	 * @param mixed $grams Peso en gramos, en cualquier forma numérica.
	 * @return float
	 */
	public static function floor_weight_grams( $grams ) {
		$grams = floatval( $grams );

		return $grams < self::MIN_WEIGHT_GRAMS ? (float) self::MIN_WEIGHT_GRAMS : $grams;
	}

	/**
	 * @param mixed $weight_kg Peso en kg, en cualquier forma numérica.
	 * @return float
	 */
	public static function floor_weight_kg( $weight_kg ) {
		return self::floor_weight_grams( floatval( $weight_kg ) * 1000 ) / 1000;
	}

	/**
	 * El peso de un bulto no apilado viene por unidad y se declara $units veces,
	 * así que el piso se aplica al total del bulto y se reparte de vuelta: 10
	 * unidades de 300 g declaran 3 kg, no 10.
	 *
	 * @param array $bultos Bultos tal como los devuelve stack().
	 * @param bool  $apila  Si los bultos son pilas (el peso ya es el total).
	 * @return array
	 */
	public static function apply_min_weight( array $bultos, $apila ) {
		foreach ( $bultos as $index => $bulto ) {
			$units     = max( 1, (int) $bulto['units'] );
			$weight_kg = floatval( $bulto['weight_kg'] );
			$total_kg  = self::floor_weight_kg( $apila ? $weight_kg : $weight_kg * $units );

			$bultos[ $index ]['weight_kg'] = $apila ? $total_kg : $total_kg / $units;
		}

		return $bultos;
	}

	/**
	 * Bultos que ocupan N unidades de un producto en unidades canónicas (cm y kg).
	 *
	 * Las dimensiones caen al mínimo permisivo 1 cuando el producto no las tiene
	 * cargadas: un bulto nunca se declara con un lado en cero. El peso de cada
	 * bulto sale con el piso de MIN_WEIGHT_GRAMS ya aplicado.
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

		$weight_kg = Andreani_Order_Mapper::convert_weight_to_unit( $product->get_weight(), 'kg' );

		$apilado = self::resolve_apilado( $product );

		return self::apply_min_weight( self::stack( $base, $weight_kg, $apilado, $quantity ), ! empty( $apilado ) );
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
		if ( ! empty( self::resolve_bultos( $product ) ) ) {
			return array();
		}

		$config = Andreani_Product_Apilado::get_apilado( $product->get_id() );

		return Andreani_Product_Apilado::is_valid( $config ) ? $config : array();
	}

	/**
	 * Bultos adicionales del producto; una variación sin los propios hereda los del padre.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return array
	 */
	public static function resolve_bultos( $product ) {
		$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product->get_id() );

		if ( empty( $bultos_adicionales ) && Andreani_Product_Bultos::inherits_from_parent( $product ) ) {
			$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product->get_parent_id() );
		}

		return $bultos_adicionales;
	}

	/**
	 * Referencia de la caja del producto; una variación sin la propia hereda la del padre.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return string
	 */
	public static function resolve_main_ref( $product ) {
		$ref = (string) get_post_meta( $product->get_id(), Andreani_Product_Bultos::MAIN_REF_META, true );

		if ( '' === $ref && Andreani_Product_Bultos::inherits_from_parent( $product ) ) {
			$ref = (string) get_post_meta( $product->get_parent_id(), Andreani_Product_Bultos::MAIN_REF_META, true );
		}

		return $ref;
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

	/**
	 * Cajas de un pedido de $quantity unidades, listas para dibujar, en cm y kg.
	 * Una pila trae units, base (alto de la primera unidad) e inc (alto que suma cada
	 * unidad extra) para dibujarla en rodajas; cuando la pila también crece de ancho
	 * o de fondo no se puede rebanar y inc queda en 0. El resto de las cajas son de
	 * una unidad.
	 *
	 * @param array $base        Dimensiones de una unidad en cm: width, height, depth.
	 * @param float $weight_kg   Peso de una unidad en kg.
	 * @param array $config      Config de apilado, o array vacío si no aplica.
	 * @param array $adicionales Piezas adicionales en cm y gramos.
	 * @param int   $quantity    Unidades del pedido.
	 * @param string $main_ref   Referencia de la caja del producto, que lleva la primera caja de cada unidad.
	 * @return array<int,array{w:float,d:float,h:float,kg:float,units:int,base:float,inc:float,ref:string}>
	 */
	public static function draw_packages( array $base, $weight_kg, array $config, array $adicionales, $quantity, $main_ref = '' ) {
		return self::expand_groups( self::draw_package_groups( $base, $weight_kg, $config, $adicionales, $quantity, $main_ref ) );
	}

	/**
	 * @return array<int,array{w:float,d:float,h:float,kg:float,units:int,base:float,inc:float,ref:string,count:int}>
	 */
	public static function draw_package_groups( array $base, $weight_kg, array $config, array $adicionales, $quantity, $main_ref = '' ) {
		foreach ( array( 'width', 'height', 'depth' ) as $side ) {
			if ( ! isset( $base[ $side ] ) || (float) $base[ $side ] <= 0 ) {
				return array();
			}
		}

		$quantity = (int) $quantity;

		if ( $quantity <= 0 ) {
			return array();
		}

		$apila  = empty( $adicionales ) && Andreani_Product_Apilado::is_valid( $config );
		$groups = array();

		if ( $apila ) {
			$max    = (int) $config['maxStackableUnits'];
			$slices = (float) $config['unitIncrementWidth'] <= 0 && (float) $config['unitIncrementDepth'] <= 0;

			foreach ( array( array( $max, intdiv( $quantity, $max ) ), array( $quantity % $max, 1 ) ) as $pile ) {
				list( $units, $count ) = $pile;

				if ( $units <= 0 || $count <= 0 ) {
					continue;
				}

				$bulto = self::apply_min_weight( self::stack( $base, $weight_kg, $config, $units ), true )[0];

				$groups[] = self::with_count(
					self::drawn_package(
						$bulto['width'],
						$bulto['depth'],
						$bulto['height'],
						$bulto['weight_kg'],
						$bulto['units'],
						$base['height'],
						$slices ? (float) $config['unitIncrementHeight'] : 0.0,
						$main_ref
					),
					$count
				);
			}
		} else {
			$bulto = self::apply_min_weight( self::stack( $base, $weight_kg, array(), $quantity ), false )[0];

			$groups[] = self::with_count(
				self::drawn_package( $bulto['width'], $bulto['depth'], $bulto['height'], $bulto['weight_kg'], 1, $bulto['height'], 0.0, $main_ref ),
				$bulto['units']
			);
		}

		foreach ( $adicionales as $pieza ) {
			$width  = isset( $pieza['width'] ) ? (float) $pieza['width'] : 0.0;
			$height = isset( $pieza['height'] ) ? (float) $pieza['height'] : 0.0;
			$depth  = isset( $pieza['depth'] ) ? (float) $pieza['depth'] : 0.0;

			if ( $width <= 0 || $height <= 0 || $depth <= 0 ) {
				continue;
			}

			$kg = self::floor_weight_grams( isset( $pieza['weight'] ) ? $pieza['weight'] : 0 ) / 1000;

			$groups[] = self::with_count( self::drawn_package( $width, $depth, $height, $kg, 1, $height, 0.0, self::piece_ref( $pieza ) ), $quantity );
		}

		return $groups;
	}

	private static function with_count( array $package, $count ) {
		$package['count'] = (int) $count;

		return $package;
	}

	private static function expand_groups( array $groups ) {
		$packages = array();

		foreach ( $groups as $group ) {
			$count = $group['count'];
			unset( $group['count'] );

			for ( $i = 0; $i < $count; $i++ ) {
				$packages[] = $group;
			}
		}

		return $packages;
	}

	/**
	 * Cajas de $quantity unidades del producto tal como las arma el cotizador.
	 *
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades.
	 * @return array<int,array{w:float,d:float,h:float,kg:float,units:int,base:float,inc:float}>
	 */
	public static function draw_packages_for_product( $product, $quantity ) {
		return self::expand_groups( self::draw_package_groups_for_product( $product, $quantity ) );
	}

	/**
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades.
	 * @return array<int,array{w:float,d:float,h:float,kg:float,units:int,base:float,inc:float,ref:string,count:int}>
	 */
	public static function draw_package_groups_for_product( $product, $quantity ) {
		$base = array(
			'width'  => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_width() ),
			'height' => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_height() ),
			'depth'  => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_length() ),
		);

		return self::draw_package_groups(
			$base,
			Andreani_Order_Mapper::convert_weight_to_unit( $product->get_weight(), 'kg' ),
			self::resolve_apilado( $product ),
			self::resolve_bultos( $product ),
			$quantity,
			self::resolve_main_ref( $product )
		);
	}

	private static function piece_ref( array $pieza ) {
		$name = isset( $pieza['name'] ) ? trim( (string) $pieza['name'] ) : '';

		return preg_match( '/^Bulto \d+$/', $name ) ? '' : $name;
	}

	private static function drawn_package( $width, $depth, $height, $kg, $units, $base_height, $increment, $ref = '' ) {
		return array(
			'w'     => round( (float) $width, 4 ),
			'd'     => round( (float) $depth, 4 ),
			'h'     => round( (float) $height, 4 ),
			'kg'    => round( (float) $kg, 6 ),
			'units' => (int) $units,
			'base'  => round( (float) $base_height, 4 ),
			'inc'   => round( (float) $increment, 4 ),
			'ref'   => (string) $ref,
		);
	}

	public static function preview( array $base, $weight_kg, array $config, array $adicionales, array $quantities ) {
		foreach ( array( 'width', 'height', 'depth' ) as $side ) {
			if ( ! isset( $base[ $side ] ) || (float) $base[ $side ] <= 0 ) {
				return array();
			}
		}

		$apila = empty( $adicionales ) && Andreani_Product_Apilado::is_valid( $config );
		$rows  = array();

		foreach ( $quantities as $quantity ) {
			$quantity = (int) $quantity;

			if ( $quantity <= 0 ) {
				continue;
			}

			$bultos     = 0;
			$volume_cm3 = 0.0;
			$total_kg   = 0.0;

			foreach ( self::apply_min_weight( self::stack( $base, $weight_kg, $apila ? $config : array(), $quantity ), $apila ) as $bulto ) {
				$count = $apila ? 1 : (int) $bulto['units'];

				$bultos     += $count;
				$volume_cm3 += $bulto['width'] * $bulto['height'] * $bulto['depth'] * $count;
				$total_kg   += $bulto['weight_kg'] * $count;
			}

			foreach ( $adicionales as $pieza ) {
				$width  = isset( $pieza['width'] ) ? (float) $pieza['width'] : 0.0;
				$height = isset( $pieza['height'] ) ? (float) $pieza['height'] : 0.0;
				$depth  = isset( $pieza['depth'] ) ? (float) $pieza['depth'] : 0.0;

				if ( $width <= 0 || $height <= 0 || $depth <= 0 ) {
					continue;
				}

				$bultos     += $quantity;
				$volume_cm3 += $width * $height * $depth * $quantity;
				$total_kg   += self::floor_weight_grams( isset( $pieza['weight'] ) ? $pieza['weight'] : 0 ) / 1000 * $quantity;
			}

			$aforado_kg = $volume_cm3 * Andreani_Api_Config::AFORO_KG_M3 / 1000000;

			$rows[] = array(
				'quantity'   => $quantity,
				'bultos'     => $bultos,
				'volume_cm3' => $volume_cm3,
				'weight_kg'  => $total_kg,
				'aforado_kg' => $aforado_kg,
				'charged'    => $aforado_kg > $total_kg ? 'aforado' : 'real',
			);
		}

		return $rows;
	}
}
