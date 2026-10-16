<?php
/**
 * Cajas y bolsas que tiene la tienda, y la elección de cuál usar para un
 * conjunto de piezas. Gemelo de pickBox() en andreani-box-preview.js: los dos
 * arman el contenido con el mismo acomodo por estantes y aplican el mismo margen.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Store_Boxes {

	const OPTION               = 'andreani_store_boxes';
	const MAX_BOXES            = 30;
	const MAX_PIECES           = 500;
	const TYPE_BOX             = 'caja';
	const TYPE_BAG             = 'bolsa';
	const TYPE_SET             = 'juego';
	const SET_MAX_BOXES        = 10;
	const SET_MIN_BOXES        = 2;
	const REF_MAX_LENGTH       = 60;
	const BAG_THICKNESS_CM     = 5;
	const BAG_VOLUME_TOLERANCE = 1.1;
	const FIT_MARGIN_CM        = 1;
	const MARGIN_SIDE_CM       = 2;
	const MARGIN_HEIGHT_CM     = 1;
	const NAME_MAX_LENGTH      = 40;
	const SIDE_MAX_CM          = 1000;

	/**
	 * Los paquetes guardados antes de que tuvieran id lo reciben acá y se persiste
	 * en el acto: un producto asignado referencia el id, así que no puede cambiar
	 * entre lecturas.
	 *
	 * @return array<int,array> Cajas y bolsas en cm; los juegos traen sus cajas en cm y gramos.
	 */
	public static function all() {
		$raw   = get_option( self::OPTION, array() );
		$boxes = self::sanitize( $raw );

		if ( ! empty( $boxes ) && self::lacks_ids( $raw ) ) {
			update_option( self::OPTION, $boxes, false );
		}

		return $boxes;
	}

	private static function lacks_ids( $raw ) {
		foreach ( (array) $raw as $entry ) {
			if ( is_array( $entry ) && ! self::valid_id( isset( $entry['id'] ) ? $entry['id'] : '' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[A-Za-z0-9_-]{4,40}$/', $id );
	}

	private static function new_id() {
		return 'pk_' . bin2hex( random_bytes( 6 ) );
	}

	/**
	 * @param string $id Id estable de un paquete.
	 * @return array|null
	 */
	public static function find( $id ) {
		if ( ! self::valid_id( $id ) ) {
			return null;
		}

		foreach ( self::all() as $box ) {
			if ( $box['id'] === $id ) {
				return $box;
			}
		}

		return null;
	}

	public static function is_set( $box ) {
		return is_array( $box ) && isset( $box['type'] ) && self::TYPE_SET === $box['type'];
	}

	/**
	 * @param array|null $boxes Paquetes a filtrar; null usa los de la tienda.
	 * @return array Cajas y bolsas, sin los juegos.
	 */
	public static function singles( $boxes = null ) {
		return array_values( array_filter( null === $boxes ? self::all() : $boxes, function ( $box ) {
			return ! self::is_set( $box );
		} ) );
	}

	/**
	 * @return array Solo los juegos de cajas.
	 */
	public static function sets() {
		return array_values( array_filter( self::all(), array( __CLASS__, 'is_set' ) ) );
	}

	/**
	 * @return array<int,array> En la unidad de medidas y de peso de la tienda.
	 */
	public static function for_display() {
		$boxes = array();

		foreach ( self::all() as $box ) {
			if ( self::is_set( $box ) ) {
				foreach ( $box['boxes'] as $index => $piece ) {
					$box['boxes'][ $index ] = self::piece_for_display( $piece );
				}
			} else {
				foreach ( array( 'length', 'width', 'height' ) as $side ) {
					$box[ $side ] = round( Andreani_Order_Mapper::convert_cm_to_dimension_unit( $box[ $side ] ), 2 );
				}
			}

			$boxes[] = $box;
		}

		return $boxes;
	}

	private static function piece_for_display( array $piece ) {
		foreach ( array( 'length', 'width', 'height' ) as $side ) {
			$piece[ $side ] = round( Andreani_Order_Mapper::convert_cm_to_dimension_unit( $piece[ $side ] ), 2 );
		}

		$piece['weight'] = (float) Andreani_Order_Mapper::convert_grams_to_weight_unit( $piece['weight'] );

		return $piece;
	}

	/**
	 * @param string $json Lista de cajas en JSON con las medidas en la unidad de la tienda.
	 * @return string Nombre de la caja ya guardada que una entrada nueva duplicaria; vacio si no hay duplicado.
	 */
	public static function duplicate_name( $json ) {
		$boxes = self::from_json( $json );

		if ( null === $boxes ) {
			return '';
		}

		$stored = array();

		foreach ( self::all() as $box ) {
			$stored[ self::signature( $box ) ][] = $box['name'];
		}

		$count = array();

		foreach ( $boxes as $box ) {
			$key           = self::signature( $box );
			$count[ $key ] = ( isset( $count[ $key ] ) ? $count[ $key ] : 0 ) + 1;
		}

		foreach ( $boxes as $box ) {
			$key = self::signature( $box );

			if ( $count[ $key ] > 1 && $count[ $key ] > ( isset( $stored[ $key ] ) ? count( $stored[ $key ] ) : 0 ) ) {
				return isset( $stored[ $key ] ) ? $stored[ $key ][0] : $box['name'];
			}
		}

		return '';
	}

	private static function signature( array $box ) {
		if ( self::is_set( $box ) ) {
			$parts = array();

			foreach ( $box['boxes'] as $piece ) {
				$parts[] = self::signature( array( 'type' => self::TYPE_BOX, 'length' => $piece['length'], 'width' => $piece['width'], 'height' => $piece['height'] ) ) . '@' . round( $piece['weight'], 1 );
			}

			sort( $parts );

			return self::TYPE_SET . ':' . implode( '|', $parts );
		}

		$sides = array( round( $box['length'], 2 ), round( $box['width'], 2 ), round( $box['height'], 2 ) );

		sort( $sides );

		return $box['type'] . ':' . implode( 'x', $sides );
	}

	private static function from_json( $json ) {
		$decoded = json_decode( (string) $json, true );

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		foreach ( $decoded as $index => $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			if ( isset( $entry['boxes'] ) && is_array( $entry['boxes'] ) ) {
				foreach ( $entry['boxes'] as $position => $piece ) {
					if ( is_array( $piece ) ) {
						$decoded[ $index ]['boxes'][ $position ] = self::piece_from_store_units( $piece );
					}
				}

				continue;
			}

			foreach ( array( 'length', 'width', 'height' ) as $side ) {
				if ( isset( $entry[ $side ] ) ) {
					$decoded[ $index ][ $side ] = Andreani_Order_Mapper::convert_dimension_to_cm( str_replace( ',', '.', (string) $entry[ $side ] ) );
				}
			}
		}

		return self::sanitize( $decoded );
	}

	private static function piece_from_store_units( array $piece ) {
		foreach ( array( 'length', 'width', 'height' ) as $side ) {
			if ( isset( $piece[ $side ] ) ) {
				$piece[ $side ] = Andreani_Order_Mapper::convert_dimension_to_cm( str_replace( ',', '.', (string) $piece[ $side ] ) );
			}
		}

		if ( isset( $piece['weight'] ) ) {
			$piece['weight'] = Andreani_Order_Mapper::round_measure( Andreani_Order_Mapper::convert_weight_to_unit( str_replace( ',', '.', (string) $piece['weight'] ), 'gr' ) );
		}

		return $piece;
	}

	/**
	 * @param string $json Lista de paquetes en JSON con las medidas en la unidad de la tienda.
	 * @return array|null Primer error de validación: code name_required, name_duplicate (con name) o side_max (con max en la unidad de la tienda); null si no hay.
	 */
	public static function validation_error( $json ) {
		$decoded = json_decode( (string) $json, true );

		return is_array( $decoded ) ? self::validate_entries( $decoded, array() ) : null;
	}

	/**
	 * @param array      $package Caja o bolsa guardada (en cm; el grosor de la bolsa es su alto).
	 * @param WC_Product $product Producto o variación.
	 * @return string Medidas del producto en la unidad de la tienda si no entran en el paquete (lados ordenados, sin margen); vacío si entra o no tiene medidas.
	 */
	public static function product_misfit( array $package, $product ) {
		if ( self::is_set( $package ) ) {
			return '';
		}

		$need = array_map( array( 'Andreani_Order_Mapper', 'convert_dimension_to_cm' ), array( $product->get_length(), $product->get_width(), $product->get_height() ) );
		$have = array( $package['length'], $package['width'], $package['height'] );

		rsort( $need );
		rsort( $have );

		if ( min( $need ) <= 0 ) {
			return '';
		}

		foreach ( $need as $i => $side ) {
			if ( round( $side, 2 ) > round( $have[ $i ], 2 ) ) {
				$shown = array_map( function ( $cm ) {
					return (string) round( Andreani_Order_Mapper::convert_cm_to_dimension_unit( $cm ), 2 );
				}, $need );

				return implode( '×', $shown ) . ' ' . get_option( 'woocommerce_dimension_unit', 'cm' );
			}
		}

		return '';
	}

	private static function name_key( $name ) {
		return mb_strtolower( trim( preg_replace( '/\s+/u', ' ', (string) $name ) ) );
	}

	private static function validate_entries( array $entries, array $other_names ) {
		$stored = array();

		foreach ( self::all() as $box ) {
			$stored[ $box['id'] ] = self::name_key( $box['name'] );
		}

		$count = array_count_values( $other_names );
		$rows  = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$sides = array();

			if ( isset( $entry['boxes'] ) && is_array( $entry['boxes'] ) ) {
				foreach ( $entry['boxes'] as $piece ) {
					$sides = array_merge( $sides, is_array( $piece ) ? self::raw_sides( $piece ) : array() );
				}
			} else {
				$sides = self::raw_sides( $entry );
			}

			foreach ( $sides as $value ) {
				if ( Andreani_Order_Mapper::convert_dimension_to_cm( str_replace( ',', '.', (string) $value ) ) > self::SIDE_MAX_CM + 0.01 ) {
					return array( 'code' => 'side_max', 'max' => round( Andreani_Order_Mapper::convert_cm_to_dimension_unit( self::SIDE_MAX_CM ), 2 ) );
				}
			}

			$name = isset( $entry['name'] ) && is_scalar( $entry['name'] ) ? trim( sanitize_text_field( (string) $entry['name'] ) ) : '';
			$key  = self::name_key( $name );
			$id   = isset( $entry['id'] ) && is_string( $entry['id'] ) ? $entry['id'] : '';

			if ( '' === $key ) {
				return array( 'code' => 'name_required' );
			}

			$count[ $key ] = ( isset( $count[ $key ] ) ? $count[ $key ] : 0 ) + 1;
			$rows[]        = array( $key, $name, '' !== $id && isset( $stored[ $id ] ) && $stored[ $id ] === $key );
		}

		foreach ( $rows as $row ) {
			if ( ! $row[2] && $count[ $row[0] ] > 1 ) {
				return array( 'code' => 'name_duplicate', 'name' => $row[1] );
			}
		}

		return null;
	}

	private static function raw_sides( array $entry ) {
		$sides = array();

		foreach ( array( 'length', 'width', 'height' ) as $side ) {
			if ( isset( $entry[ $side ] ) && is_scalar( $entry[ $side ] ) ) {
				$sides[] = $entry[ $side ];
			}
		}

		return $sides;
	}

	/**
	 * @param string $json Lista de cajas en JSON con las medidas en la unidad de la tienda.
	 * @return bool Si las cajas guardadas cambiaron.
	 */
	public static function save_from_json( $json ) {
		$boxes = self::from_json( $json );

		if ( null === $boxes ) {
			return false;
		}

		$stored = self::all();

		if ( self::without_ids( $boxes ) === self::without_ids( $stored ) ) {
			return false;
		}

		update_option( self::OPTION, $boxes, false );

		return true;
	}

	/**
	 * @param array $before Paquetes antes de guardar.
	 * @param array $after  Paquetes después de guardar.
	 * @return array<string,bool> Id de cada paquete que ya no está, con si era un juego de cajas.
	 */
	public static function removed( array $before, array $after ) {
		$kept = array();

		foreach ( $after as $box ) {
			if ( isset( $box['id'] ) ) {
				$kept[ $box['id'] ] = true;
			}
		}

		$removed = array();

		foreach ( $before as $box ) {
			if ( isset( $box['id'] ) && '' !== $box['id'] && ! isset( $kept[ $box['id'] ] ) ) {
				$removed[ $box['id'] ] = self::is_set( $box );
			}
		}

		return $removed;
	}

	private static function without_ids( array $boxes ) {
		return array_map( function ( $box ) {
			unset( $box['id'] );

			return $box;
		}, $boxes );
	}

	/**
	 * Suma un paquete a los guardados, al principio de la lista.
	 *
	 * @param string $json Un paquete en JSON con las medidas en la unidad de la tienda.
	 * @return array{status:string,package:?array,name:string} status: ok, invalid, max o duplicate.
	 */
	public static function add_from_json( $json ) {
		$result  = array( 'status' => 'invalid', 'package' => null, 'name' => '' );
		$decoded = json_decode( (string) $json, true );

		if ( is_array( $decoded ) ) {
			$error = self::validate_entries( array( $decoded ), array_map( function ( $box ) {
				return self::name_key( $box['name'] );
			}, self::all() ) );

			if ( $error ) {
				return array_merge( $result, $error, array( 'status' => $error['code'] ) );
			}
		}

		$new =is_array( $decoded ) ? self::from_json( wp_json_encode( array( $decoded ) ) ) : null;

		if ( empty( $new ) ) {
			return $result;
		}

		$stored = self::all();

		if ( count( $stored ) >= self::MAX_BOXES ) {
			$result['status'] = 'max';

			return $result;
		}

		$signature = self::signature( $new[0] );

		foreach ( $stored as $box ) {
			if ( self::signature( $box ) === $signature ) {
				$result['status'] = 'duplicate';
				$result['name']   = $box['name'];

				return $result;
			}
		}

		array_unshift( $stored, $new[0] );
		update_option( self::OPTION, self::sanitize( $stored ), false );

		return array( 'status' => 'ok', 'package' => $new[0], 'name' => $new[0]['name'] );
	}

	/**
	 * @param mixed $raw Lista de cajas con las medidas en cm.
	 * @return array<int,array{name:string,type:string,length:float,width:float,height:float}>
	 */
	public static function sanitize( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$boxes = array();
		$seen  = array();

		foreach ( $raw as $entry ) {
			if ( count( $boxes ) >= self::MAX_BOXES ) {
				break;
			}

			$box = self::sanitize_one( $entry );

			if ( ! $box ) {
				continue;
			}

			while ( isset( $seen[ $box['id'] ] ) ) {
				$box['id'] = self::new_id();
			}

			$seen[ $box['id'] ] = true;
			$boxes[]            = $box;
		}

		return $boxes;
	}

	private static function sanitize_one( $entry ) {
		if ( ! is_array( $entry ) ) {
			return null;
		}

		if ( isset( $entry['type'] ) && self::TYPE_SET === $entry['type'] ) {
			return self::sanitize_set( $entry );
		}

		$type   = isset( $entry['type'] ) && self::TYPE_BAG === $entry['type'] ? self::TYPE_BAG : self::TYPE_BOX;
		$length = self::side( isset( $entry['length'] ) ? $entry['length'] : 0 );
		$width  = self::side( isset( $entry['width'] ) ? $entry['width'] : 0 );
		$height = self::side( isset( $entry['height'] ) ? $entry['height'] : 0 );

		if ( self::TYPE_BAG === $type && $height <= 0 ) {
			$height = (float) self::BAG_THICKNESS_CM;
		}

		if ( $length <= 0 || $width <= 0 || $height <= 0 ) {
			return null;
		}

		$name = isset( $entry['name'] ) && is_scalar( $entry['name'] ) ? trim( sanitize_text_field( (string) $entry['name'] ) ) : '';

		return array(
			'id'     => self::id_of( $entry ),
			'name'   => mb_substr( '' === $name ? self::default_name( $type ) : $name, 0, self::NAME_MAX_LENGTH ),
			'type'   => $type,
			'length' => $length,
			'width'  => $width,
			'height' => $height,
		);
	}

	private static function id_of( array $entry ) {
		return isset( $entry['id'] ) && self::valid_id( $entry['id'] ) ? $entry['id'] : self::new_id();
	}

	/**
	 * @param array $entry Juego con name y boxes (length, width, height en cm, weight en gramos y ref opcional).
	 * @return array|null Null si no tiene al menos SET_MIN_BOXES cajas completas.
	 */
	private static function sanitize_set( array $entry ) {
		$pieces = array();

		foreach ( isset( $entry['boxes'] ) && is_array( $entry['boxes'] ) ? $entry['boxes'] : array() as $piece ) {
			if ( count( $pieces ) >= self::SET_MAX_BOXES ) {
				break;
			}

			$clean = self::sanitize_piece( $piece );

			if ( $clean ) {
				$pieces[] = $clean;
			}
		}

		if ( count( $pieces ) < self::SET_MIN_BOXES ) {
			return null;
		}

		$name = isset( $entry['name'] ) && is_scalar( $entry['name'] ) ? trim( sanitize_text_field( (string) $entry['name'] ) ) : '';

		return array(
			'id'    => self::id_of( $entry ),
			'name'  => mb_substr( '' === $name ? __( 'Juego de cajas', 'andreani-shipping' ) : $name, 0, self::NAME_MAX_LENGTH ),
			'type'  => self::TYPE_SET,
			'boxes' => $pieces,
		);
	}

	private static function sanitize_piece( $piece ) {
		if ( ! is_array( $piece ) ) {
			return null;
		}

		$length = self::side( isset( $piece['length'] ) ? $piece['length'] : 0 );
		$width  = self::side( isset( $piece['width'] ) ? $piece['width'] : 0 );
		$height = self::side( isset( $piece['height'] ) ? $piece['height'] : 0 );
		$weight = round( max( 0.0, (float) str_replace( ',', '.', isset( $piece['weight'] ) ? (string) $piece['weight'] : '0' ) ), 3 );

		if ( $length <= 0 || $width <= 0 || $height <= 0 || $weight <= 0 ) {
			return null;
		}

		$ref = isset( $piece['ref'] ) && is_scalar( $piece['ref'] ) ? trim( sanitize_text_field( (string) $piece['ref'] ) ) : '';

		return array(
			'ref'    => mb_substr( $ref, 0, self::REF_MAX_LENGTH ),
			'length' => $length,
			'width'  => $width,
			'height' => $height,
			'weight' => $weight,
		);
	}

	private static function side( $value ) {
		return round( min( max( 0.0, (float) str_replace( ',', '.', (string) $value ) ), self::SIDE_MAX_CM ), 2 );
	}

	private static function default_name( $type ) {
		return self::TYPE_BAG === $type ? __( 'Sobre o bolsa', 'andreani-shipping' ) : __( 'Caja', 'andreani-shipping' );
	}

	/**
	 * @return string Huella de las cajas cargadas; vacía si no hay ninguna.
	 */
	public static function hash() {
		$boxes = self::all();

		return empty( $boxes ) ? '' : md5( wp_json_encode( $boxes ) );
	}

	/**
	 * @param array      $pieces Piezas: w, d, h en cm y stacked opcional.
	 * @param array|null $boxes  Cajas a considerar; null usa las de la tienda.
	 * @return array|null La más chica por volumen donde entra todo, o null.
	 */
	public static function pick( array $pieces, $boxes = null ) {
		$boxes = self::singles( $boxes );
		$pack  = self::pack_pieces( $pieces );

		if ( empty( $boxes ) || ! $pack ) {
			return null;
		}

		$sides  = array( $pack['W'], $pack['D'], $pack['H'] );
		$volume = 0.0;

		foreach ( $pieces as $piece ) {
			$volume += $piece['w'] * $piece['d'] * $piece['h'];
		}

		rsort( $sides );

		$margin = self::all_boxed( $pieces ) ? 0 : self::FIT_MARGIN_CM;
		$need   = array( $sides[0] + $margin, $sides[1] + $margin, $sides[2] + $margin );

		$fitting = array();

		foreach ( $boxes as $box ) {
			if ( self::fits( $box, $need, $volume ) ) {
				$fitting[] = $box;
			}
		}

		usort( $fitting, array( __CLASS__, 'compare_volume' ) );

		return empty( $fitting ) ? null : $fitting[0];
	}

	/**
	 * @param array $pieces Piezas: w, d, h en cm y stacked opcional.
	 * @return array{w:int,d:int,h:int}|null Caja aproximada que las contiene, con margen.
	 */
	public static function suggest( array $pieces ) {
		$pack = self::pack_pieces( $pieces );

		if ( ! $pack ) {
			return null;
		}

		$boxed = self::all_boxed( $pieces );

		return array(
			'w' => (int) ceil( $pack['W'] + ( $boxed ? 0 : self::MARGIN_SIDE_CM ) ),
			'd' => (int) ceil( $pack['D'] + ( $boxed ? 0 : self::MARGIN_SIDE_CM ) ),
			'h' => (int) ceil( $pack['H'] + ( $boxed ? 0 : self::MARGIN_HEIGHT_CM ) ),
		);
	}

	private static function all_boxed( array $pieces ) {
		foreach ( $pieces as $piece ) {
			if ( empty( $piece['boxed'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array $box Caja de la tienda.
	 * @return string Largo × ancho × alto en cm; la bolsa solo largo × ancho.
	 */
	public static function dims_text( array $box ) {
		if ( self::is_set( $box ) ) {
			return count( $box['boxes'] ) . ' cajas';
		}

		$sides = self::TYPE_BAG === $box['type'] ? array( $box['length'], $box['width'] ) : array( $box['length'], $box['width'], $box['height'] );

		return implode( '×', array_map( function ( $side ) {
			return rtrim( rtrim( number_format( (float) $side, 2, '.', '' ), '0' ), '.' );
		}, $sides ) );
	}

	public static function volume( array $box ) {
		return $box['length'] * $box['width'] * $box['height'];
	}

	public static function compare_volume( $a, $b ) {
		return self::volume( $a ) <=> self::volume( $b );
	}

	private static function fits( array $box, array $need, $volume ) {
		if ( self::TYPE_BAG === $box['type'] ) {
			return $volume <= self::volume( $box ) * self::BAG_VOLUME_TOLERANCE
				&& $need[0] <= max( $box['length'], $box['width'] )
				&& $need[1] <= min( $box['length'], $box['width'] )
				&& $need[2] <= $box['height'];
		}

		$box_sides = array( $box['length'], $box['width'], $box['height'] );

		rsort( $box_sides );

		return $need[0] <= $box_sides[0] && $need[1] <= $box_sides[1] && $need[2] <= $box_sides[2];
	}

	private static function pack_pieces( array $pieces ) {
		if ( empty( $pieces ) || count( $pieces ) > self::MAX_PIECES ) {
			return null;
		}

		return self::best_pack( $pieces );
	}

	private static function orients( array $piece ) {
		if ( ! empty( $piece['stacked'] ) ) {
			return array( array( $piece['w'], $piece['d'], $piece['h'] ), array( $piece['d'], $piece['w'], $piece['h'] ) );
		}

		$s = array( $piece['w'], $piece['d'], $piece['h'] );

		rsort( $s );
		list( $a, $b, $c ) = $s;

		return array( array( $a, $b, $c ), array( $b, $a, $c ), array( $a, $c, $b ), array( $c, $a, $b ), array( $b, $c, $a ), array( $c, $b, $a ) );
	}

	private static function shelf( array $items, $width, $depth ) {
		$x = 0.0;
		$y = 0.0;
		$z = 0.0;
		$row_d = 0.0;
		$lay_h = 0.0;
		$mx    = 0.0;
		$my    = 0.0;
		$mz    = 0.0;
		$out   = array();

		foreach ( $items as $item ) {
			$o = self::first_orient( $item['or'], $width, $depth, $x, $y, true );

			if ( ! $o ) {
				$y    += $row_d;
				$x     = 0.0;
				$row_d = 0.0;
				$o     = self::first_orient( $item['or'], $width, $depth, $x, $y, false );
			}

			if ( ! $o ) {
				$z    += $lay_h;
				$x     = 0.0;
				$y     = 0.0;
				$row_d = 0.0;
				$lay_h = 0.0;
				$o     = self::first_orient( $item['or'], $width, $depth, $x, $y, false );
			}

			if ( ! $o ) {
				return null;
			}

			$out[] = array( 'w' => $o[0], 'd' => $o[1], 'h' => $o[2], 'stacked' => $item['stacked'] );
			$x    += $o[0];
			$row_d = max( $row_d, $o[1] );
			$lay_h = max( $lay_h, $o[2] );
			$mx    = max( $mx, $x );
			$my    = max( $my, $y + $o[1] );
			$mz    = max( $mz, $z + $o[2] );
		}

		return array( 'out' => $out, 'W' => $mx, 'D' => $my, 'H' => $mz );
	}

	private static function first_orient( array $orients, $width, $depth, $x, $y, $same_row ) {
		foreach ( $orients as $o ) {
			$fits = $same_row ? ( $x + $o[0] <= $width ) : ( $o[0] <= $width );

			if ( $fits && $y + $o[1] <= $depth ) {
				return $o;
			}
		}

		return null;
	}

	private static function best_pack( array $pieces ) {
		$items = array();

		foreach ( $pieces as $piece ) {
			$items[] = array( 'or' => self::orients( $piece ), 'stacked' => ! empty( $piece['stacked'] ) );
		}

		usort( $items, function ( $a, $b ) {
			return ( $b['or'][0][0] * $b['or'][0][1] * $b['or'][0][2] ) <=> ( $a['or'][0][0] * $a['or'][0][1] * $a['or'][0][2] );
		} );

		$vol  = 0.0;
		$cand = array();
		$add  = function ( $v ) use ( &$cand ) {
			if ( $v > 0 ) {
				$cand[ (string) round( $v, 6 ) ] = (float) $v;
			}
		};

		foreach ( $pieces as $piece ) {
			$vol += $piece['w'] * $piece['d'] * $piece['h'];
		}

		foreach ( $items as $item ) {
			foreach ( $item['or'] as $o ) {
				$add( $o[0] );
				$add( $o[1] );
			}
		}

		$big = array();

		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$big[] = $item['or'][0];
		}

		for ( $i = 0; $i < count( $big ); $i++ ) {
			for ( $j = $i + 1; $j < count( $big ); $j++ ) {
				$add( $big[ $i ][0] + $big[ $j ][0] );
				$add( $big[ $i ][1] + $big[ $j ][1] );
				$add( $big[ $i ][0] + $big[ $j ][1] );
			}
		}

		$side = pow( $vol, 1 / 3 );

		foreach ( array( 0.7, 0.85, 1, 1.2, 1.5, 2, 3 ) as $k ) {
			$add( $side * $k );
		}

		$candidates = array_values( $cand );

		sort( $candidates );

		$candidates = array_slice( $candidates, 0, 40 );
		$best       = null;

		foreach ( $candidates as $width ) {
			foreach ( $candidates as $depth ) {
				if ( $depth > $width ) {
					continue;
				}

				$r = self::shelf( $items, $width, $depth );

				if ( ! $r ) {
					continue;
				}

				$score = self::score( $r );

				if ( ! $best || $score < $best['score'] ) {
					$best = array( 'W' => $r['W'], 'D' => $r['D'], 'H' => $r['H'], 'score' => $score );
				}
			}
		}

		return $best;
	}

	private static function score( array $r ) {
		$dims = array( $r['W'] + self::MARGIN_SIDE_CM, $r['D'] + self::MARGIN_SIDE_CM, $r['H'] + self::MARGIN_HEIGHT_CM );
		$ok   = max( $dims ) <= Andreani_Api_Config::BIGGER_MAX_SIDE_CM && array_sum( $dims ) <= Andreani_Api_Config::BIGGER_SUM_SIDES_CM;
		$flat = 0;

		foreach ( $r['out'] as $o ) {
			if ( empty( $o['stacked'] ) && $o['h'] > min( $o['w'], $o['d'] ) ) {
				$flat++;
			}
		}

		return $dims[0] * $dims[1] * $dims[2] * ( max( $dims ) / min( $dims ) ) * pow( 1.15, $flat ) * ( $ok ? 1 : 100 );
	}
}
