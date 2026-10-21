<?php
/**
 * Promociones de envío de la tienda: descuentos y recargos sobre el precio de la
 * tarifa de Andreani, por servicio, provincia de destino y fechas.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Shipping_Promotions {

	const OPTION          = 'andreani_shipping_promotions';
	const MAX             = 50;
	const NAME_MAX_LENGTH = 40;
	const TIMEZONE        = 'America/Argentina/Buenos_Aires';

	const TYPE_DISCOUNT  = 'descuento';
	const TYPE_SURCHARGE = 'recargo';
	const FORM_PERCENT   = 'porcentaje';
	const FORM_FIXED     = 'fijo';

	const STATUS_ACTIVE   = 'activa';
	const STATUS_SCHEDULED = 'programada';
	const STATUS_ENDED    = 'finalizada';
	const STATUS_PAUSED   = 'pausada';

	const META_SERVICE   = '_andreani_servicio';
	const META_PRICE     = '_andreani_precio_andreani';
	const META_FREE      = '_andreani_gratis';
	const META_ADJUST    = '_andreani_promocion_ajuste';
	const META_TAG       = '_andreani_promocion_etiqueta';
	const META_NAME      = 'Promoción';

	const COST_PROMOTION_NAME = 'Costo adicional';

	/**
	 * @return array<string,string> Servicio => nombre que ve la tienda.
	 */
	public static function services() {
		return array(
			'estandar'  => __( 'Estándar', 'andreani-shipping' ),
			'sucursal'  => __( 'Sucursal', 'andreani-shipping' ),
			'llega-hoy' => __( 'Llega hoy', 'andreani-shipping' ),
			'bigger'    => __( 'Bigger', 'andreani-shipping' ),
		);
	}

	/**
	 * @return array<string,string> Código de provincia (el mismo de WooCommerce y Shopify) => nombre.
	 */
	public static function provinces() {
		return array(
			'C' => __( 'Ciudad Autónoma de Buenos Aires', 'andreani-shipping' ),
			'B' => __( 'Buenos Aires', 'andreani-shipping' ),
			'K' => __( 'Catamarca', 'andreani-shipping' ),
			'H' => __( 'Chaco', 'andreani-shipping' ),
			'U' => __( 'Chubut', 'andreani-shipping' ),
			'X' => __( 'Córdoba', 'andreani-shipping' ),
			'W' => __( 'Corrientes', 'andreani-shipping' ),
			'E' => __( 'Entre Ríos', 'andreani-shipping' ),
			'P' => __( 'Formosa', 'andreani-shipping' ),
			'Y' => __( 'Jujuy', 'andreani-shipping' ),
			'L' => __( 'La Pampa', 'andreani-shipping' ),
			'F' => __( 'La Rioja', 'andreani-shipping' ),
			'M' => __( 'Mendoza', 'andreani-shipping' ),
			'N' => __( 'Misiones', 'andreani-shipping' ),
			'Q' => __( 'Neuquén', 'andreani-shipping' ),
			'R' => __( 'Río Negro', 'andreani-shipping' ),
			'A' => __( 'Salta', 'andreani-shipping' ),
			'J' => __( 'San Juan', 'andreani-shipping' ),
			'D' => __( 'San Luis', 'andreani-shipping' ),
			'Z' => __( 'Santa Cruz', 'andreani-shipping' ),
			'S' => __( 'Santa Fe', 'andreani-shipping' ),
			'G' => __( 'Santiago del Estero', 'andreani-shipping' ),
			'V' => __( 'Tierra del Fuego', 'andreani-shipping' ),
			'T' => __( 'Tucumán', 'andreani-shipping' ),
		);
	}

	/**
	 * @return array<string,string[]> Región => códigos de sus provincias.
	 */
	public static function province_groups() {
		return array(
			__( 'Centro', 'andreani-shipping' )    => array( 'C', 'B', 'X', 'S', 'E' ),
			__( 'Cuyo', 'andreani-shipping' )      => array( 'M', 'J', 'D' ),
			__( 'Noroeste', 'andreani-shipping' )  => array( 'K', 'F', 'T', 'A', 'Y', 'G' ),
			__( 'Noreste', 'andreani-shipping' )   => array( 'H', 'P', 'W', 'N' ),
			__( 'Patagonia', 'andreani-shipping' ) => array( 'L', 'Q', 'R', 'U', 'Z', 'V' ),
		);
	}

	/**
	 * @param string $code Nombre del modo de entrega o código de la tarifa ("Estándar", "llega-hoy").
	 * @return string Servicio de las promociones, vacío si no es uno de los cuatro.
	 */
	public static function service_key( $code ) {
		$key = strtolower( strtr( trim( (string) $code ), array(
			'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
			'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
		) ) );
		$key = trim( (string) preg_replace( '/[^a-z0-9]+/', '-', $key ), '-' );

		return isset( self::services()[ $key ] ) ? $key : '';
	}

	/**
	 * @param string $state Provincia de destino tal como llega del carrito.
	 * @return string Código de provincia, vacío si no se conoce.
	 */
	public static function state_code( $state ) {
		$code = strtoupper( trim( (string) $state ) );

		if ( 0 === strpos( $code, 'AR-' ) ) {
			$code = substr( $code, 3 );
		}

		return isset( self::provinces()[ $code ] ) ? $code : '';
	}

	/**
	 * @return string Hoy en Argentina, AAAA-MM-DD.
	 */
	public static function today() {
		return ( new DateTimeImmutable( 'now', new DateTimeZone( self::TIMEZONE ) ) )->format( 'Y-m-d' );
	}

	/**
	 * @return array<int,array> Promociones guardadas, en el orden en que se crearon.
	 */
	public static function all() {
		$raw = get_option( self::OPTION, '' );

		if ( is_string( $raw ) ) {
			$raw = json_decode( $raw, true );
		}

		$list = array();

		foreach ( is_array( $raw ) ? $raw : array() as $entry ) {
			$result = is_array( $entry ) ? self::prepare( $entry, array() ) : array( 'promotion' => null );

			if ( $result['promotion'] ) {
				$list[] = $result['promotion'];
			}
		}

		return $list;
	}

	/**
	 * @param array[] $promotions Lista ya validada.
	 */
	public static function replace_all( array $promotions ) {
		update_option( self::OPTION, wp_json_encode( array_values( $promotions ) ), false );
		self::invalidate_rates();
	}

	/**
	 * WooCommerce guarda las tarifas en la sesión: sin esto el comprador sigue viendo
	 * el precio anterior hasta que cambie el carrito.
	 */
	private static function invalidate_rates() {
		if ( class_exists( 'WC_Cache_Helper' ) ) {
			WC_Cache_Helper::get_transient_version( 'shipping', true );
		}
	}

	private static function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[A-Za-z0-9_-]{4,40}$/', $id );
	}

	private static function new_id() {
		return 'pr_' . bin2hex( random_bytes( 6 ) );
	}

	private static function valid_date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			return false;
		}

		return checkdate( (int) $m[2], (int) $m[3], (int) $m[1] );
	}

	/**
	 * Normaliza una promoción y la valida contra las demás.
	 *
	 * @param array   $input  Lo que mandó el formulario o lo que estaba guardado.
	 * @param array[] $others Las demás promociones guardadas, sin esta.
	 * @return array{promotion:?array,error:?string}
	 */
	public static function prepare( array $input, array $others ) {
		$fail = static function ( $message ) {
			return array( 'promotion' => null, 'error' => $message );
		};

		$name = trim( sanitize_text_field( isset( $input['nombre'] ) ? (string) $input['nombre'] : '' ) );

		if ( '' === $name ) {
			return $fail( __( 'Poné un nombre para la promoción.', 'andreani-shipping' ) );
		}

		$name = mb_substr( $name, 0, self::NAME_MAX_LENGTH, 'UTF-8' );
		$type = isset( $input['tipo'] ) ? (string) $input['tipo'] : '';
		$form = isset( $input['forma'] ) ? (string) $input['forma'] : '';

		if ( ! in_array( $type, array( self::TYPE_DISCOUNT, self::TYPE_SURCHARGE ), true ) ) {
			return $fail( __( 'Elegí si es un descuento o un recargo.', 'andreani-shipping' ) );
		}

		if ( ! in_array( $form, array( self::FORM_PERCENT, self::FORM_FIXED ), true ) ) {
			return $fail( __( 'Elegí si se calcula en porcentaje o en pesos.', 'andreani-shipping' ) );
		}

		$value = isset( $input['valor'] ) && is_numeric( $input['valor'] ) ? round( (float) $input['valor'], 2 ) : 0.0;

		if ( $value <= 0 ) {
			return $fail( __( 'El valor tiene que ser mayor a 0.', 'andreani-shipping' ) );
		}

		if ( self::FORM_PERCENT === $form && self::TYPE_DISCOUNT === $type && $value > 100 ) {
			return $fail( __( 'Un descuento no puede ser de más del 100 %.', 'andreani-shipping' ) );
		}

		$services = array_keys( self::services() );

		if ( isset( $input['servicios'] ) ) {
			$services = array_values( array_intersect( $services, array_map( 'strval', (array) $input['servicios'] ) ) );
		}

		if ( empty( $services ) ) {
			return $fail( __( 'Elegí al menos un servicio.', 'andreani-shipping' ) );
		}

		$provinces = array();

		foreach ( isset( $input['provincias'] ) ? (array) $input['provincias'] : array() as $code ) {
			$code = strtoupper( trim( (string) $code ) );

			if ( ! isset( self::provinces()[ $code ] ) ) {
				return $fail( __( 'Hay una provincia que no reconocemos.', 'andreani-shipping' ) );
			}

			$provinces[ $code ] = $code;
		}

		$provinces = array_keys( array_intersect_key( self::provinces(), $provinces ) );

		if ( count( $provinces ) === count( self::provinces() ) ) {
			$provinces = array();
		}

		$from  = isset( $input['desde'] ) ? trim( (string) $input['desde'] ) : '';
		$until = isset( $input['hasta'] ) ? trim( (string) $input['hasta'] ) : '';

		if ( ( '' !== $from && ! self::valid_date( $from ) ) || ( '' !== $until && ! self::valid_date( $until ) ) ) {
			return $fail( __( 'Revisá las fechas: no son válidas.', 'andreani-shipping' ) );
		}

		if ( '' !== $from && '' !== $until && $until < $from ) {
			return $fail( __( 'La fecha de fin no puede ser anterior a la de inicio.', 'andreani-shipping' ) );
		}

		$promotion = array(
			'id'         => self::valid_id( isset( $input['id'] ) ? $input['id'] : '' ) ? $input['id'] : self::new_id(),
			'nombre'     => $name,
			'tipo'       => $type,
			'forma'      => $form,
			'valor'      => $value,
			'servicios'  => $services,
			'provincias' => $provinces,
			'desde'      => '' === $from ? null : $from,
			'hasta'      => '' === $until ? null : $until,
			'activa'     => ! array_key_exists( 'activa', $input ) || (bool) $input['activa'],
		);

		$conflict = self::overlap_error( $promotion, $others );

		if ( null !== $conflict ) {
			return $fail( $conflict );
		}

		return array( 'promotion' => $promotion, 'error' => null );
	}

	/**
	 * @param array   $promotion Promoción a guardar.
	 * @param array[] $others    Las demás.
	 * @return string|null Mensaje para la tienda, null si no se superpone con ninguna activa.
	 */
	public static function overlap_error( array $promotion, array $others ) {
		if ( empty( $promotion['activa'] ) ) {
			return null;
		}

		foreach ( $others as $other ) {
			if ( empty( $other['activa'] ) || $other['id'] === $promotion['id'] ) {
				continue;
			}

			$shared = array_values( array_intersect( $promotion['servicios'], $other['servicios'] ) );

			if ( empty( $shared ) ) {
				continue;
			}

			if ( ! empty( $promotion['provincias'] ) && ! empty( $other['provincias'] ) && empty( array_intersect( $promotion['provincias'], $other['provincias'] ) ) ) {
				continue;
			}

			$start = max( (string) $promotion['desde'], (string) $other['desde'] );
			$end   = self::min_end( $promotion['hasta'], $other['hasta'] );

			if ( '' !== $start && null !== $end && $end < $start ) {
				continue;
			}

			$labels = array_map( static function ( $service ) {
				return self::services()[ $service ];
			}, $shared );

			/* translators: 1: nombre de la otra promoción, 2: servicios que comparten, 3: fechas en que coinciden */
			return trim( sprintf( __( "Se superpone con '%1\$s' en %2\$s %3\$s", 'andreani-shipping' ), $other['nombre'], self::join_list( $labels ), self::overlap_dates( $start, $end ) ) );
		}

		return null;
	}

	private static function min_end( $a, $b ) {
		if ( null === $a || '' === $a ) {
			return null === $b || '' === $b ? null : $b;
		}

		if ( null === $b || '' === $b ) {
			return $a;
		}

		return min( $a, $b );
	}

	private static function overlap_dates( $start, $end ) {
		if ( '' !== $start && null !== $end ) {
			/* translators: 1: día/mes de inicio, 2: día/mes de fin */
			return sprintf( __( 'del %1$s al %2$s', 'andreani-shipping' ), self::short_date( $start ), self::short_date( $end ) );
		}

		if ( '' !== $start ) {
			/* translators: %s: día/mes de inicio */
			return sprintf( __( 'desde el %s', 'andreani-shipping' ), self::short_date( $start ) );
		}

		if ( null !== $end ) {
			/* translators: %s: día/mes de fin */
			return sprintf( __( 'hasta el %s', 'andreani-shipping' ), self::short_date( $end ) );
		}

		return '';
	}

	private static function short_date( $date ) {
		return substr( $date, 8, 2 ) . '/' . substr( $date, 5, 2 );
	}

	/**
	 * @param array $input Datos del formulario; con id existente se edita.
	 * @return array{promotion:?array,error:?string}
	 */
	public static function save( array $input ) {
		$all   = self::all();
		$id    = isset( $input['id'] ) ? (string) $input['id'] : '';
		$index = self::index_of( $all, $id );

		if ( false === $index && count( $all ) >= self::MAX ) {
			/* translators: %d: máximo de promociones */
			return array( 'promotion' => null, 'error' => sprintf( __( 'Llegaste al máximo de %d promociones.', 'andreani-shipping' ), self::MAX ) );
		}

		$others = $all;

		if ( false !== $index ) {
			unset( $others[ $index ] );
		} else {
			unset( $input['id'] );
		}

		$result = self::prepare( $input, array_values( $others ) );

		if ( null === $result['promotion'] ) {
			return $result;
		}

		if ( false === $index ) {
			$all[] = $result['promotion'];
		} else {
			$all[ $index ] = $result['promotion'];
		}

		self::replace_all( $all );

		return $result;
	}

	/**
	 * @param string $id     Id de la promoción.
	 * @param bool   $active Si queda activa o pausada.
	 * @return array{promotion:?array,error:?string}
	 */
	public static function set_active( $id, $active ) {
		$all   = self::all();
		$index = self::index_of( $all, $id );

		if ( false === $index ) {
			return array( 'promotion' => null, 'error' => __( 'No encontramos esa promoción.', 'andreani-shipping' ) );
		}

		$input           = $all[ $index ];
		$input['activa'] = (bool) $active;
		$others          = $all;
		unset( $others[ $index ] );

		$result = self::prepare( $input, array_values( $others ) );

		if ( null === $result['promotion'] ) {
			return $result;
		}

		$all[ $index ] = $result['promotion'];
		self::replace_all( $all );

		return $result;
	}

	/**
	 * @return bool Si existía y se borró.
	 */
	public static function delete( $id ) {
		$all   = self::all();
		$index = self::index_of( $all, $id );

		if ( false === $index ) {
			return false;
		}

		unset( $all[ $index ] );
		self::replace_all( $all );

		return true;
	}

	private static function index_of( array $all, $id ) {
		foreach ( $all as $index => $promotion ) {
			if ( $promotion['id'] === $id ) {
				return $index;
			}
		}

		return false;
	}

	/**
	 * @param array       $promotion Promoción.
	 * @param string|null $today     AAAA-MM-DD, hoy si no se pasa.
	 * @return string activa, programada, finalizada o pausada.
	 */
	public static function status( array $promotion, $today = null ) {
		$today = null === $today ? self::today() : $today;

		if ( ! empty( $promotion['hasta'] ) && $today > $promotion['hasta'] ) {
			return self::STATUS_ENDED;
		}

		if ( empty( $promotion['activa'] ) ) {
			return self::STATUS_PAUSED;
		}

		if ( ! empty( $promotion['desde'] ) && $today < $promotion['desde'] ) {
			return self::STATUS_SCHEDULED;
		}

		return self::STATUS_ACTIVE;
	}

	/**
	 * @return array<string,string> Estado => texto para la tienda.
	 */
	public static function status_labels() {
		return array(
			self::STATUS_ACTIVE    => __( 'Activa ahora', 'andreani-shipping' ),
			self::STATUS_SCHEDULED => __( 'Programada', 'andreani-shipping' ),
			self::STATUS_ENDED     => __( 'Finalizada', 'andreani-shipping' ),
			self::STATUS_PAUSED    => __( 'Pausada', 'andreani-shipping' ),
		);
	}

	/**
	 * @param string       $service    Servicio de la tarifa.
	 * @param string       $state      Provincia de destino; vacía si no se conoce.
	 * @param string|null  $today      AAAA-MM-DD, hoy si no se pasa.
	 * @param array[]|null $promotions Lista a usar, las guardadas si no se pasa.
	 * @return array|null La promoción vigente que aplica.
	 */
	public static function find( $service, $state, $today = null, $promotions = null ) {
		$today  = null === $today ? self::today() : $today;
		$state  = self::state_code( $state );
		$source = null === $promotions ? self::all() : $promotions;

		foreach ( $source as $promotion ) {
			if ( self::STATUS_ACTIVE !== self::status( $promotion, $today ) || ! in_array( $service, $promotion['servicios'], true ) ) {
				continue;
			}

			if ( empty( $promotion['provincias'] ) || ( '' !== $state && in_array( $state, $promotion['provincias'], true ) ) ) {
				return $promotion;
			}
		}

		return null;
	}

	/**
	 * @param float $price     Precio de la tarifa.
	 * @param array $promotion Promoción.
	 * @return float Precio final, nunca menor a 0.
	 */
	public static function price( $price, array $promotion ) {
		$amount = self::FORM_PERCENT === $promotion['forma'] ? $price * $promotion['valor'] / 100 : $promotion['valor'];
		$sign   = self::TYPE_DISCOUNT === $promotion['tipo'] ? -1 : 1;

		return max( 0.0, round( $price + $sign * $amount, 2 ) );
	}

	/**
	 * @return string Lo que se descontó o recargó, listo para la grilla: "−15 %", "+$500".
	 */
	public static function tag( array $promotion ) {
		$sign  = self::TYPE_DISCOUNT === $promotion['tipo'] ? '−' : '+';
		$value = self::FORM_PERCENT === $promotion['forma'] ? self::number( $promotion['valor'] ) . ' %' : '$' . self::number( $promotion['valor'] );

		return $sign . $value;
	}

	public static function number( $value ) {
		$value = (float) $value;

		return number_format( $value, floor( $value ) === $value ? 0 : 2, ',', '.' );
	}

	/**
	 * Aplica la promoción vigente a tarifas ya armadas. Va después del caché de
	 * cotización: la provincia no es parte de su clave.
	 *
	 * @param array[]      $rates      Tarifas de Andreani_Api_Response::build_shipping_rates().
	 * @param string       $state      Provincia de destino.
	 * @param string|null  $today      AAAA-MM-DD.
	 * @param array[]|null $promotions Lista a usar, las guardadas si no se pasa.
	 * @return array[]
	 */
	public static function apply_to_rates( array $rates, $state, $today = null, $promotions = null ) {
		$today      = null === $today ? self::today() : $today;
		$promotions = null === $promotions ? self::all() : $promotions;

		if ( empty( $promotions ) ) {
			return $rates;
		}

		foreach ( $rates as $i => $rate ) {
			$meta = isset( $rate['meta_data'] ) && is_array( $rate['meta_data'] ) ? $rate['meta_data'] : array();

			if ( ! isset( $meta[ self::META_SERVICE ], $meta[ self::META_PRICE ] ) || 'yes' === ( isset( $meta[ self::META_FREE ] ) ? $meta[ self::META_FREE ] : '' ) ) {
				continue;
			}

			$promotion = self::find( $meta[ self::META_SERVICE ], $state, $today, $promotions );

			if ( null === $promotion ) {
				continue;
			}

			$base  = (float) $meta[ self::META_PRICE ];
			$final = self::price( $base, $promotion );

			$meta[ self::META_NAME ]   = $promotion['nombre'];
			$meta[ self::META_ADJUST ] = round( $final - $base, 2 );
			$meta[ self::META_TAG ]    = self::tag( $promotion );

			$rates[ $i ]['cost']      = $final;
			$rates[ $i ]['label']     = $rate['label'] . ' - ' . $promotion['nombre'];
			$rates[ $i ]['meta_data'] = $meta;
		}

		return $rates;
	}

	/**
	 * @param mixed $hidden Meta que WooCommerce ya oculta en el ítem de envío.
	 * @return string[]
	 */
	public static function hide_internal_item_meta( $hidden ) {
		return array_values(
			array_unique(
				array_merge(
					is_array( $hidden ) ? $hidden : array(),
					array( self::META_SERVICE, self::META_PRICE, self::META_FREE, self::META_ADJUST, self::META_TAG, '_andreani_rate_id' )
				)
			)
		);
	}

	/**
	 * @param WC_Order $order Pedido.
	 * @return array{andreani:float,charged:float,name:string,tag:string}|null Null si el pedido no tuvo promoción.
	 */
	public static function order_summary( $order ) {
		foreach ( $order->get_shipping_methods() as $item ) {
			$name = (string) $item->get_meta( self::META_NAME, true );
			$base = $item->get_meta( self::META_PRICE, true );

			if ( '' === $name || '' === (string) $base ) {
				continue;
			}

			return array(
				'andreani' => (float) $base,
				'charged'  => (float) $item->get_total(),
				'name'     => $name,
				'tag'      => (string) $item->get_meta( self::META_TAG, true ),
			);
		}

		return null;
	}

	/**
	 * @return string "Andreani $10.000 · Cobrado $8.500 · Black Friday −15 %"
	 */
	public static function summary_line( array $summary ) {
		/* translators: 1: precio de Andreani, 2: lo cobrado al comprador, 3: nombre de la promoción, 4: lo que se descontó o recargó */
		return trim( sprintf( __( 'Andreani $%1$s · Cobrado $%2$s · %3$s %4$s', 'andreani-shipping' ), self::number( $summary['andreani'] ), self::number( $summary['charged'] ), $summary['name'], $summary['tag'] ) );
	}

	/**
	 * @param array       $promotion Promoción.
	 * @param string|null $today     AAAA-MM-DD, para decidir si el año se escribe.
	 * @return string "15 % de descuento en Estándar y Sucursal, en Córdoba y Santa Fe, del 24 al 30 de noviembre"
	 */
	public static function describe( array $promotion, $today = null ) {
		$today    = null === $today ? self::today() : $today;
		$amount   = self::FORM_PERCENT === $promotion['forma'] ? self::number( $promotion['valor'] ) . ' %' : '$' . self::number( $promotion['valor'] );
		$kind     = self::TYPE_DISCOUNT === $promotion['tipo'] ? __( 'de descuento', 'andreani-shipping' ) : __( 'de recargo', 'andreani-shipping' );
		$services = self::services();
		$all      = count( $promotion['servicios'] ) === count( $services );

		$where_service = $all
			? __( 'en todos los servicios', 'andreani-shipping' )
			/* translators: %s: servicios */
			: sprintf( __( 'en %s', 'andreani-shipping' ), self::join_list( array_map( static function ( $service ) use ( $services ) {
				return $services[ $service ];
			}, $promotion['servicios'] ) ) );

		$parts = array( $amount . ' ' . $kind . ' ' . $where_service, self::describe_provinces( $promotion['provincias'] ) );
		$when  = self::describe_dates( $promotion['desde'], $promotion['hasta'], $today );

		if ( '' !== $when ) {
			$parts[] = $when;
		}

		return implode( ', ', $parts );
	}

	private static function describe_provinces( array $codes ) {
		$all = self::provinces();

		if ( empty( $codes ) ) {
			return __( 'en todo el país', 'andreani-shipping' );
		}

		$rest = array_diff( array_keys( $all ), array( 'C' ) );

		if ( count( $codes ) === count( $rest ) && empty( array_diff( $rest, $codes ) ) ) {
			return __( 'en el interior (todo menos CABA)', 'andreani-shipping' );
		}

		if ( count( $codes ) > 4 ) {
			/* translators: %d: cantidad de provincias */
			return sprintf( __( 'en %d provincias', 'andreani-shipping' ), count( $codes ) );
		}

		/* translators: %s: provincias */
		return sprintf( __( 'en %s', 'andreani-shipping' ), self::join_list( array_map( static function ( $code ) use ( $all ) {
			return $all[ $code ];
		}, $codes ) ) );
	}

	private static function describe_dates( $from, $until, $today ) {
		if ( empty( $from ) && empty( $until ) ) {
			return __( 'sin fecha de fin', 'andreani-shipping' );
		}

		if ( empty( $until ) ) {
			/* translators: %s: fecha de inicio */
			return sprintf( __( 'desde el %s', 'andreani-shipping' ), self::long_date( $from, $today ) );
		}

		if ( empty( $from ) ) {
			/* translators: %s: fecha de fin */
			return sprintf( __( 'hasta el %s', 'andreani-shipping' ), self::long_date( $until, $today ) );
		}

		if ( substr( $from, 0, 7 ) === substr( $until, 0, 7 ) ) {
			return $from === $until
				/* translators: %s: fecha */
				? sprintf( __( 'el %s', 'andreani-shipping' ), self::long_date( $from, $today ) )
				/* translators: 1: día de inicio, 2: fecha de fin completa */
				: sprintf( __( 'del %1$s al %2$s', 'andreani-shipping' ), (int) substr( $from, 8, 2 ), self::long_date( $until, $today ) );
		}

		/* translators: 1: fecha de inicio, 2: fecha de fin */
		return sprintf( __( 'del %1$s al %2$s', 'andreani-shipping' ), self::long_date( $from, $today ), self::long_date( $until, $today ) );
	}

	private static function long_date( $date, $today ) {
		$months = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		$text   = (int) substr( $date, 8, 2 ) . ' de ' . $months[ (int) substr( $date, 5, 2 ) - 1 ];

		return substr( $date, 0, 4 ) === substr( $today, 0, 4 ) ? $text : $text . ' de ' . substr( $date, 0, 4 );
	}

	private static function join_list( array $items ) {
		$items = array_values( $items );
		$last  = array_pop( $items );

		return empty( $items ) ? (string) $last : implode( ', ', $items ) . ' ' . __( 'y', 'andreani-shipping' ) . ' ' . $last;
	}

	/**
	 * @return array[] Las promociones con su estado y su resumen, listas para la pantalla.
	 */
	public static function for_display() {
		$today  = self::today();
		$labels = self::status_labels();
		$list   = array();

		foreach ( self::all() as $promotion ) {
			$status = self::status( $promotion, $today );

			$list[] = array_merge( $promotion, array(
				'estado'       => $status,
				'estado_texto' => $labels[ $status ],
				'resumen'      => self::describe( $promotion, $today ),
			) );
		}

		return $list;
	}
}
