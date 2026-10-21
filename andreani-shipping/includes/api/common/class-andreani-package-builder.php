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
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/class-andreani-store-boxes.php';

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
		list( $base, $weight_kg ) = self::unit_box( $product );

		$apilado = self::resolve_apilado( $product );

		return self::apply_min_weight( self::stack( $base, $weight_kg, $apilado, $quantity ), ! empty( $apilado ) );
	}

	private static function unit_box( $product ) {
		$measures = self::unit_measures( $product );

		return array(
			array(
				'width'  => $measures['width'] ?: 1,
				'height' => $measures['height'] ?: 1,
				'depth'  => $measures['depth'] ?: 1,
			),
			$measures['weight_kg'],
		);
	}

	/**
	 * Medidas de la caja principal del producto en cm y kg: las del paquete
	 * asignado cuando existe y, si no, las de WooCommerce. Con una caja el peso
	 * sigue siendo el del producto; con un juego, el de su primera caja.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return array{width:float,height:float,depth:float,weight_kg:float}
	 */
	public static function unit_measures( $product ) {
		if ( 'deleted' === self::assigned_package( $product )['state'] ) {
			return array( 'width' => 0.0, 'height' => 0.0, 'depth' => 0.0, 'weight_kg' => 0.0 );
		}

		$package = self::active_package( $product );

		if ( $package && Andreani_Store_Boxes::is_set( $package ) ) {
			$first = $package['boxes'][0];

			return array(
				'width'     => (float) $first['width'],
				'height'    => (float) $first['height'],
				'depth'     => (float) $first['length'],
				'weight_kg' => (float) $first['weight'] / 1000,
			);
		}

		$weight_kg = Andreani_Order_Mapper::convert_weight_to_unit( $product->get_weight(), 'kg' );

		if ( $package ) {
			return array(
				'width'     => (float) $package['width'],
				'height'    => (float) $package['height'],
				'depth'     => (float) $package['length'],
				'weight_kg' => $weight_kg,
			);
		}

		return array(
			'width'     => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_width() ),
			'height'    => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_height() ),
			'depth'     => Andreani_Order_Mapper::convert_dimension_to_cm( $product->get_length() ),
			'weight_kg' => $weight_kg,
		);
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return array{ref:string,state:string,package:?array} state: none, ok o deleted (referencia a un paquete que ya no existe, o juego borrado). Una variación sin modo propio usa el paquete del padre.
	 */
	public static function assigned_package( $product ) {
		$ref = trim( (string) self::inherited_meta( $product, Andreani_Product_Bultos::BOX_REF_META ) );

		if ( '' === $ref ) {
			$lost = 'yes' === self::inherited_meta( $product, Andreani_Product_Bultos::BOX_LOST_META );

			return array( 'ref' => '', 'state' => $lost ? 'deleted' : 'none', 'package' => null );
		}

		$package = Andreani_Store_Boxes::find( $ref );

		if ( ! $package && 'yes' !== self::inherited_meta( $product, Andreani_Product_Bultos::BOX_LOST_META ) && ! self::is_multibulto_product( $product ) ) {
			return array( 'ref' => $ref, 'state' => 'none', 'package' => null );
		}

		return array( 'ref' => $ref, 'state' => $package ? 'ok' : 'deleted', 'package' => $package );
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return bool Si el producto se despacha en varias cajas según su modo guardado, su clasificación o sus bultos propios.
	 */
	private static function is_multibulto_product( $product ) {
		$multibulto = Andreani_Product_Bultos::MODE_MULTIBULTO;

		return $multibulto === self::inherited_meta( $product, Andreani_Product_Bultos::MODE_META )
			|| $multibulto === self::inherited_meta( $product, '_andreani_dispatch_mode_class' )
			|| ! empty( self::own_bultos( $product ) );
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return array|null El paquete que manda sobre las medidas del producto: un juego siempre; una caja o bolsa solo si el producto no trae cajas propias.
	 */
	private static function active_package( $product ) {
		$assigned = self::assigned_package( $product );

		if ( ! $assigned['package'] ) {
			return null;
		}

		if ( Andreani_Store_Boxes::is_set( $assigned['package'] ) ) {
			return $assigned['package'];
		}

		return empty( self::own_bultos( $product ) ) ? $assigned['package'] : null;
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return array|null El juego de cajas asignado, si lo hay.
	 */
	public static function assigned_set( $product ) {
		$package = self::active_package( $product );

		return $package && Andreani_Store_Boxes::is_set( $package ) ? $package : null;
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return array{name:string,type:string,parts:array}|null El paquete de la tienda que manda sobre este producto, con la referencia de cada caja si es un juego.
	 */
	public static function applied_package( $product ) {
		$package = self::active_package( $product );

		if ( ! $package ) {
			return null;
		}

		$parts = array();

		if ( Andreani_Store_Boxes::is_set( $package ) ) {
			foreach ( $package['boxes'] as $position => $box ) {
				$parts[] = '' !== trim( (string) $box['ref'] ) ? trim( (string) $box['ref'] ) : sprintf( __( 'Caja %d', 'andreani-shipping' ), $position + 1 );
			}
		}

		return array( 'name' => (string) $package['name'], 'type' => (string) $package['type'], 'parts' => $parts );
	}

	/**
	 * Marca las cajas dibujadas de una línea con la línea a la que pertenecen y, si
	 * el producto tiene un paquete de la tienda aplicado, con su nombre.
	 *
	 * @param array      $groups   Grupos de draw_package_groups_for_product.
	 * @param mixed      $key      Clave de la línea.
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades de la línea.
	 * @return array
	 */
	public static function annotate_groups( array $groups, $key, $product, $quantity ) {
		$assigned = self::applied_package( $product );

		foreach ( $groups as $index => $group ) {
			$groups[ $index ]['line']     = $key;
			$groups[ $index ]['line_qty'] = (int) $quantity;

			if ( $assigned ) {
				$groups[ $index ]['assigned'] = $assigned;
			}
		}

		return $groups;
	}

	/**
	 * Líneas que se declaran para N unidades de un producto: una por cada grupo de
	 * bultos idénticos. quantity es cuántos bultos iguales trae la línea, weight_kg
	 * el peso de cada uno y units las unidades del producto que lleva la línea
	 * entera. Con capacidad por caja las medidas son las de la caja y solo suma peso.
	 *
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades en el carrito o en el ítem de la orden.
	 * @return array<int,array{width:float,height:float,depth:float,weight_kg:float,quantity:int,units:int}>
	 */
	public static function lines( $product, $quantity ) {
		$apila = ! empty( self::resolve_apilado( $product ) );
		$cap   = self::resolve_units_per_box( $product );
		$lines = array();

		if ( $cap < 2 ) {
			foreach ( self::build( $product, $quantity ) as $bulto ) {
				$units = (int) $bulto['units'];

				$lines[] = array(
					'width'     => $bulto['width'],
					'height'    => $bulto['height'],
					'depth'     => $bulto['depth'],
					'weight_kg' => $bulto['weight_kg'],
					'quantity'  => $apila ? 1 : $units,
					'units'     => $units,
				);
			}

			return $lines;
		}

		list( $base, $weight_kg ) = self::unit_box( $product );

		foreach ( self::pack_units( $quantity, $cap ) as $group ) {
			$lines[] = array(
				'width'     => $base['width'],
				'height'    => $base['height'],
				'depth'     => $base['depth'],
				'weight_kg' => self::floor_weight_kg( $weight_kg * $group['units'] ),
				'quantity'  => $group['count'],
				'units'     => $group['units'] * $group['count'],
			);
		}

		return $lines;
	}

	/**
	 * Reparte N unidades en cajas de hasta $units_per_box: las llenas juntas en un
	 * grupo y, si sobra, la última con las unidades restantes.
	 *
	 * @param int $quantity      Unidades a repartir.
	 * @param int $units_per_box Capacidad de la caja.
	 * @return array<int,array{units:int,count:int}> units son las de cada caja del grupo.
	 */
	public static function pack_units( $quantity, $units_per_box ) {
		$quantity      = (int) $quantity;
		$units_per_box = max( 1, (int) $units_per_box );

		if ( $quantity <= 0 ) {
			return array();
		}

		$groups = array();
		$full   = intdiv( $quantity, $units_per_box );
		$rest   = $quantity % $units_per_box;

		if ( $full > 0 ) {
			$groups[] = array( 'units' => $units_per_box, 'count' => $full );
		}

		if ( $rest > 0 ) {
			$groups[] = array( 'units' => $rest, 'count' => 1 );
		}

		return $groups;
	}

	/**
	 * Unidades que entran en la caja del producto. Solo vale en caja única: con
	 * apilado o varias cajas es 1. Una variación sin modo propio usa la del padre.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return int
	 */
	public static function resolve_units_per_box( $product ) {
		if ( ! empty( self::resolve_bultos( $product ) ) || ! empty( self::resolve_apilado( $product ) ) ) {
			return 1;
		}

		$units = Andreani_Product_Bultos::parse_units_per_box( self::inherited_meta( $product, Andreani_Product_Bultos::UNITS_META ) );

		return null === $units ? 1 : $units;
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return string together o separate: solo el override separate del producto fuerza una etiqueta por caja.
	 */
	public static function resolve_overflow( $product ) {
		if ( ! Andreani_Product_Bultos::overflow_supported() ) {
			return Andreani_Product_Bultos::OVERFLOW_TOGETHER;
		}

		return Andreani_Product_Bultos::OVERFLOW_SEPARATE === self::raw_overflow( $product )
			? Andreani_Product_Bultos::OVERFLOW_SEPARATE
			: Andreani_Product_Bultos::OVERFLOW_TOGETHER;
	}

	/**
	 * @param WC_Product $product Producto o variación.
	 * @return string inherit, together o separate: lo guardado en el producto, sin resolver.
	 */
	public static function raw_overflow( $product ) {
		return Andreani_Product_Bultos::sanitize_overflow( self::inherited_meta( $product, Andreani_Product_Bultos::OVERFLOW_META ) );
	}

	private static function inherited_meta( $product, $key ) {
		$id = Andreani_Product_Bultos::inherits_from_parent( $product ) ? $product->get_parent_id() : $product->get_id();

		return get_post_meta( $id, $key, true );
	}

	/**
	 * @param bool   $multibulto Si la línea es de varias cajas.
	 * @param string $overflow   Preferencia efectiva: together o separate.
	 * @param int    $boxes      Cajas que genera la línea.
	 * @return bool Siempre false en cuentas sin force_bigger (corporativas).
	 */
	public static function should_force_bigger( $multibulto, $overflow, $boxes ) {
		if ( ! Andreani_Product_Bultos::overflow_supported() ) {
			return false;
		}

		return (bool) $multibulto
			|| ( Andreani_Product_Bultos::OVERFLOW_SEPARATE === $overflow && (int) $boxes > 1 );
	}

	/**
	 * Cajas reales que genera una línea y si por sí sola obliga a cotizar como Bigger.
	 *
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades.
	 * @return array{boxes:int,force_bigger:bool,reason:string} reason: multibulto, separate o cadena vacía.
	 */
	public static function plan( $product, $quantity ) {
		$quantity    = (int) $quantity;
		$adicionales = self::resolve_bultos( $product );

		if ( ! empty( $adicionales ) ) {
			return array(
				'boxes'        => ( 1 + count( $adicionales ) ) * max( 0, $quantity ),
				'force_bigger' => self::should_force_bigger( true, Andreani_Product_Bultos::OVERFLOW_TOGETHER, 0 ),
				'reason'       => 'multibulto',
			);
		}

		$boxes = ! empty( self::resolve_apilado( $product ) )
			? count( self::build( $product, $quantity ) )
			: array_sum( array_column( self::pack_units( $quantity, self::resolve_units_per_box( $product ) ), 'count' ) );

		$force = self::should_force_bigger( false, self::resolve_overflow( $product ), $boxes );

		return array(
			'boxes'        => $boxes,
			'force_bigger' => $force,
			'reason'       => $force ? 'separate' : '',
		);
	}

	/**
	 * Decisión única de Bigger y de bulto para un conjunto de líneas. Con cajas
	 * de la tienda cargadas, lo que viaja junto se declara con las medidas de la
	 * más chica donde entra; las cajas propias del producto no se reemplazan.
	 *
	 * @param array $lines Líneas: product, quantity e id opcional.
	 * @return array{force_bigger:bool,reason:string,line:int,bundled:array,bundle:?array} reason: multibulto, separate o none.
	 */
	public static function cart_decision( array $lines ) {
		$none  = array( 'force_bigger' => false, 'reason' => 'none', 'line' => 0, 'bundled' => array(), 'bundle' => null );
		$boxes = Andreani_Store_Boxes::all();

		if ( ! Andreani_Product_Bultos::overflow_supported() ) {
			return empty( $boxes ) ? $none : self::boxed_decision( $none, $lines, $boxes );
		}

		$forcing = false;
		$bundled = array();
		$multi   = 0;
		$first   = 0;

		foreach ( $lines as $key => $line ) {
			$product = $line['product'];
			$plan    = self::plan( $product, (int) $line['quantity'] );
			$id      = (int) ( isset( $line['id'] ) ? $line['id'] : $product->get_id() );

			if ( ! $plan['force_bigger'] ) {
				$bundled[] = $key;
				continue;
			}

			$forcing = true;
			$first   = $first ? $first : $id;
			$multi   = ( ! $multi && 'multibulto' === $plan['reason'] ) ? $id : $multi;
		}

		if ( ! $forcing ) {
			return empty( $boxes ) ? $none : self::boxed_decision( $none, $lines, $boxes );
		}

		$bundled_lines = array_intersect_key( $lines, array_flip( $bundled ) );
		$bundle        = self::bundle_of( $bundled_lines );

		return array(
			'force_bigger' => true,
			'reason'       => $multi ? 'multibulto' : 'separate',
			'line'         => $multi ? $multi : $first,
			'bundled'      => $bundled,
			'bundle'       => self::with_store_box( $bundle, self::contents_of( $bundled_lines ), $boxes ),
		);
	}

	/**
	 * Sin Bigger de por medio: las líneas sueltas viajan en una caja de la tienda
	 * si entran en alguna; las que traen su propia caja se declaran como siempre.
	 */
	private static function boxed_decision( array $none, array $lines, array $boxes ) {
		$loose = array();

		foreach ( $lines as $key => $line ) {
			if ( empty( self::resolve_bultos( $line['product'] ) ) && self::resolve_units_per_box( $line['product'] ) < 2 ) {
				$loose[ $key ] = $line;
			}
		}

		$contents = self::contents_of( $loose );
		$box      = $contents ? Andreani_Store_Boxes::pick( $contents['pieces'], $boxes ) : null;

		if ( ! $box ) {
			return $none;
		}

		$none['bundled'] = array_keys( $loose );
		$none['bundle']  = array(
			'width'     => (float) $box['width'],
			'height'    => (float) $box['height'],
			'depth'     => (float) $box['length'],
			'weight_kg' => $contents['kg'],
			'units'     => $contents['units'],
			'store_box' => $box,
		);

		return $none;
	}

	private static function with_store_box( $bundle, $contents, array $boxes ) {
		if ( ! $bundle || empty( $boxes ) || ! $contents ) {
			return $bundle;
		}

		$box = Andreani_Store_Boxes::pick( $contents['pieces'], $boxes );

		if ( ! $box ) {
			return $bundle;
		}

		return array_merge( $bundle, array(
			'width'     => (float) $box['width'],
			'height'    => (float) $box['height'],
			'depth'     => (float) $box['length'],
			'store_box' => $box,
		) );
	}

	/**
	 * @param array $lines Líneas: product y quantity.
	 * @return array{pieces:array,kg:float,units:int}|null Null sin piezas o con demasiadas para acomodar.
	 */
	private static function contents_of( array $lines ) {
		$pieces = array();
		$kg     = 0.0;
		$units  = 0;

		foreach ( $lines as $line ) {
			$units += (int) $line['quantity'];

			foreach ( self::draw_package_groups_for_product( $line['product'], (int) $line['quantity'] ) as $group ) {
				if ( count( $pieces ) + $group['count'] > Andreani_Store_Boxes::MAX_PIECES ) {
					return null;
				}

				$kg += $group['kg'] * $group['count'];

				for ( $i = 0; $i < $group['count']; $i++ ) {
					$pieces[] = array( 'w' => $group['w'], 'd' => $group['d'], 'h' => $group['h'], 'stacked' => $group['units'] > 1 && $group['inc'] > 0, 'boxed' => ! empty( $group['boxed'] ) );
				}
			}
		}

		return empty( $pieces ) ? null : array( 'pieces' => $pieces, 'kg' => $kg, 'units' => $units );
	}

	/**
	 * @param array $decision Resultado de cart_decision.
	 * @return array{name:string,type:string,length:float,width:float,height:float}|null Caja de la tienda con la que viaja lo que va junto.
	 */
	public static function store_box_of( array $decision ) {
		return empty( $decision['bundle']['store_box'] ) ? null : $decision['bundle']['store_box'];
	}

	/**
	 * Etiquetas con las que sale el envío: Paquetería declara un solo bulto aunque
	 * los productos traigan cajas propias; Bigger lleva una por caja.
	 *
	 * @param bool  $bigger   Si el envío viaja como Bigger.
	 * @param array $packages Paquetes armados, con su count.
	 * @return int
	 */
	public static function label_count( $bigger, array $packages ) {
		return $bigger ? max( 1, (int) array_sum( array_column( $packages, 'count' ) ) ) : 1;
	}

	/**
	 * Caja de la tienda donde entran $quantity unidades de un producto y, si no
	 * entra en ninguna, la caja aproximada que las contendría.
	 *
	 * @param WC_Product $product  Producto o variación.
	 * @param int        $quantity Unidades.
	 * @return array{box:?array,suggested:?array,has_boxes:bool}|null Null si el producto trae su propia caja.
	 */
	public static function store_box_advice( $product, $quantity ) {
		if ( ! empty( self::resolve_bultos( $product ) ) || self::resolve_units_per_box( $product ) > 1 ) {
			return null;
		}

		$contents = self::contents_of( array( array( 'product' => $product, 'quantity' => (int) $quantity ) ) );

		if ( ! $contents ) {
			return null;
		}

		$boxes = Andreani_Store_Boxes::all();

		return array(
			'box'       => Andreani_Store_Boxes::pick( $contents['pieces'], $boxes ),
			'suggested' => Andreani_Store_Boxes::suggest( $contents['pieces'] ),
			'has_boxes' => ! empty( $boxes ),
		);
	}

	/**
	 * Un solo bulto con las cajas de las líneas: suma de pesos y la caja cúbica más
	 * chica que contiene el volumen total; con una única caja, sus medidas reales.
	 *
	 * @param array $lines Líneas: product y quantity.
	 * @return array{width:float,height:float,depth:float,weight_kg:float,units:int}|null
	 */
	private static function bundle_of( array $lines ) {
		$boxes  = 0;
		$volume = 0.0;
		$weight = 0.0;
		$units  = 0;
		$single = null;

		foreach ( $lines as $line ) {
			foreach ( self::lines( $line['product'], (int) $line['quantity'] ) as $box ) {
				$boxes  += (int) $box['quantity'];
				$volume += $box['width'] * $box['height'] * $box['depth'] * $box['quantity'];
				$weight += $box['weight_kg'] * $box['quantity'];
				$units  += (int) $box['units'];
				$single  = $box;
			}
		}

		if ( 0 === $boxes ) {
			return null;
		}

		$edge = (float) ceil( round( pow( $volume, 1 / 3 ), 4 ) );
		$side = 1 === $boxes
			? array( (float) $single['width'], (float) $single['height'], (float) $single['depth'] )
			: array( $edge, $edge, $edge );

		return array(
			'width'     => $side[0],
			'height'    => $side[1],
			'depth'     => $side[2],
			'weight_kg' => $weight,
			'units'     => $units,
		);
	}

	/**
	 * Cajas ya dibujadas por línea, sin las que se juntan, más la del bulto único.
	 *
	 * @param array $groups_by_key Grupos de cajas por clave de línea.
	 * @param array $names_by_key  Nombre del producto por clave de línea.
	 * @param array $decision      Resultado de cart_decision.
	 * @return array
	 */
	public static function with_bundle( array $groups_by_key, array $names_by_key, array $decision ) {
		$packages = array();

		foreach ( $groups_by_key as $key => $groups ) {
			if ( ! $decision['bundle'] || ! in_array( $key, $decision['bundled'], true ) ) {
				$packages = array_merge( $packages, $groups );
			}
		}

		if ( $decision['bundle'] ) {
			$bundle = $decision['bundle'];
			$names  = array();

			foreach ( $decision['bundled'] as $key ) {
				$names[] = $names_by_key[ $key ];
			}

			$package          = self::drawn_package( $bundle['width'], $bundle['depth'], $bundle['height'], $bundle['weight_kg'], $bundle['units'], $bundle['height'], 0.0, '' );
			$package['count'] = 1;
			$package['name']  = sprintf( __( '%s (todo junto)', 'andreani-shipping' ), self::join_names( $names ) );

			if ( ! empty( $bundle['store_box'] ) ) {
				$package['ref']       = $bundle['store_box']['name'];
				$package['store_box'] = $bundle['store_box'];
				$package['store_for'] = implode( ', ', $names );
			}

			$packages[]       = $package;
		}

		return $packages;
	}

	/**
	 * @param string[] $names Nombres de producto.
	 * @return string "A", "A y B" o "A, B y C".
	 */
	public static function join_names( array $names ) {
		$names = array_values( $names );
		$last  = array_pop( $names );

		return $names ? implode( ', ', $names ) . ' ' . __( 'y', 'andreani-shipping' ) . ' ' . $last : (string) $last;
	}

	/**
	 * @param array $package Paquete de WooCommerce.
	 * @return bool Si el carrito se cotiza como Bigger según cart_decision.
	 */
	public static function force_bigger_for_package( $package ) {
		return self::decision_for_package( $package )['force_bigger'];
	}

	/**
	 * @param array $package Paquete de WooCommerce.
	 * @return array Resultado de cart_decision, con las líneas por clave del carrito.
	 */
	public static function decision_for_package( $package ) {
		$lines = array();

		foreach ( isset( $package['contents'] ) ? $package['contents'] : array() as $key => $values ) {
			if ( $values['data']->needs_shipping() ) {
				$lines[ $key ] = array( 'product' => $values['data'], 'quantity' => (int) $values['quantity'] );
			}
		}

		return self::cart_decision( $lines );
	}

	/**
	 * @param WC_Order $order Orden.
	 * @return bool Si el pedido se da de alta como Bigger según cart_decision.
	 */
	public static function force_bigger_for_order( $order ) {
		return self::decision_for_order( $order )['force_bigger'];
	}

	/**
	 * @param WC_Order $order Orden.
	 * @return array Resultado de cart_decision, con las líneas por id de ítem.
	 */
	public static function decision_for_order( $order ) {
		$lines = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = wc_get_product( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );

			if ( $product && $product->needs_shipping() ) {
				$lines[ $item_id ] = array( 'product' => $product, 'quantity' => (int) $item->get_quantity() );
			}
		}

		return self::cart_decision( $lines );
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
		$set = self::assigned_set( $product );

		if ( $set ) {
			return self::set_bultos( $set );
		}

		return self::own_bultos( $product );
	}

	private static function own_bultos( $product ) {
		$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product->get_id() );

		if ( empty( $bultos_adicionales ) && Andreani_Product_Bultos::inherits_from_parent( $product ) ) {
			$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product->get_parent_id() );
		}

		return $bultos_adicionales;
	}

	/**
	 * @param array $set Juego de cajas.
	 * @return array Las cajas después de la primera, como bultos adicionales en cm y gramos.
	 */
	private static function set_bultos( array $set ) {
		$bultos = array();

		foreach ( array_slice( $set['boxes'], 1 ) as $position => $box ) {
			$bultos[] = array(
				'name'   => '' !== $box['ref'] ? $box['ref'] : sprintf( 'Bulto %d', $position + 2 ),
				'height' => (float) $box['height'],
				'width'  => (float) $box['width'],
				'depth'  => (float) $box['length'],
				'weight' => (float) $box['weight'],
			);
		}

		return $bultos;
	}

	/**
	 * Referencia de la caja del producto; una variación sin la propia hereda la del padre.
	 *
	 * @param WC_Product $product Producto o variación.
	 * @return string
	 */
	public static function resolve_main_ref( $product ) {
		$set = self::assigned_set( $product );

		if ( $set ) {
			return (string) $set['boxes'][0]['ref'];
		}

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
	public static function draw_packages( array $base, $weight_kg, array $config, array $adicionales, $quantity, $main_ref = '', $units_per_box = 1 ) {
		return self::expand_groups( self::draw_package_groups( $base, $weight_kg, $config, $adicionales, $quantity, $main_ref, $units_per_box ) );
	}

	/**
	 * @return array<int,array{w:float,d:float,h:float,kg:float,units:int,base:float,inc:float,ref:string,count:int}>
	 */
	public static function draw_package_groups( array $base, $weight_kg, array $config, array $adicionales, $quantity, $main_ref = '', $units_per_box = 1 ) {
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
		} elseif ( empty( $adicionales ) && (int) $units_per_box > 1 ) {
			foreach ( self::pack_units( $quantity, $units_per_box ) as $box ) {
				$groups[] = self::with_count(
					array_merge(
						self::drawn_package( $base['width'], $base['depth'], $base['height'], self::floor_weight_kg( $weight_kg * $box['units'] ), $box['units'], $base['height'], 0.0, $main_ref ),
						array( 'fixed' => true )
					),
					$box['count']
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
		$measures = self::unit_measures( $product );
		$base     = array(
			'width'  => $measures['width'],
			'height' => $measures['height'],
			'depth'  => $measures['depth'],
		);

		$groups = self::draw_package_groups(
			$base,
			$measures['weight_kg'],
			self::resolve_apilado( $product ),
			self::resolve_bultos( $product ),
			$quantity,
			self::resolve_main_ref( $product ),
			self::resolve_units_per_box( $product )
		);

		if ( self::active_package( $product ) ) {
			foreach ( $groups as $index => $group ) {
				$groups[ $index ]['boxed'] = true;
			}
		}

		return $groups;
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

	public static function preview( array $base, $weight_kg, array $config, array $adicionales, array $quantities, $units_per_box = 1 ) {
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

			if ( ! $apila && empty( $adicionales ) && (int) $units_per_box > 1 ) {
				foreach ( self::pack_units( $quantity, $units_per_box ) as $box ) {
					$bultos     += $box['count'];
					$volume_cm3 += $base['width'] * $base['height'] * $base['depth'] * $box['count'];
					$total_kg   += self::floor_weight_kg( $weight_kg * $box['units'] ) * $box['count'];
				}
			} else {
				foreach ( self::apply_min_weight( self::stack( $base, $weight_kg, $apila ? $config : array(), $quantity ), $apila ) as $bulto ) {
					$count = $apila ? 1 : (int) $bulto['units'];

					$bultos     += $count;
					$volume_cm3 += $bulto['width'] * $bulto['height'] * $bulto['depth'] * $count;
					$total_kg   += $bulto['weight_kg'] * $count;
				}
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
