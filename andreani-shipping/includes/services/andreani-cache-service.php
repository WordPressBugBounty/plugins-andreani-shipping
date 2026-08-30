<?php
/**
 * Andreani_Cache_Service
 * Servicio centralizado para manejo de cache del plugin
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Cache_Service {
	const TTL_SUCURSALES = 2 * HOUR_IN_SECONDS;
	const TTL_COTIZACION = 15 * MINUTE_IN_SECONDS;
	const TTL_SUCURSALES_ORIGEN = 6 * HOUR_IN_SECONDS;
	const TTL_ORIGEN_DEFAULT    = 6 * HOUR_IN_SECONDS;
	const PREFIX_SUCURSALES = 'andreani_sucursales_';
	const PREFIX_COTIZACION = 'andreani_cotizacion_';
	const PREFIX_SUCURSALES_ORIGEN = 'andreani_suc_origen_';
	const PREFIX_ORIGEN_DEFAULT    = 'andreani_origen_def_';

	/**
	 * Obtener resultado cacheado para un método
	 *
	 * @param string $method Nombre del método API
	 * @param array  $args   Argumentos del método
	 * @return mixed|null Resultado cacheado o null si no existe
	 */
	public static function get( $method, $args ) {
		$cache_key = self::build_cache_key( $method, $args );

		if ( empty( $cache_key ) ) {
			return null;
		}

		$cached_result = get_transient( $cache_key );
		return $cached_result !== false ? $cached_result : null;
	}

	/**
	 * Guardar resultado en cache
	 *
	 * @param string $method Nombre del método API
	 * @param array  $args   Argumentos del método
	 * @param mixed  $result Resultado a cachear
	 * @return bool True si se guardó correctamente
	 */
	public static function set( $method, $args, $result ) {
		if ( is_wp_error( $result ) || empty( $result ) ) {
			return false;
		}

		if ( $method === 'get_cotizacion' && ( ! isset( $result['rates'] ) || empty( $result['rates'] ) ) ) {
			return false;
		}

		$cache_key = self::build_cache_key( $method, $args );
		$ttl       = self::get_ttl( $method, $result );

		if ( empty( $cache_key ) || $ttl <= 0 ) {
			return false;
		}

		return set_transient( $cache_key, $result, $ttl );
	}

	/**
	 * Obtener sucursales cacheadas
	 *
	 * @param string $postal_code Código postal
	 * @return array|null
	 */
	public static function get_sucursales( $postal_code ) {
		if ( empty( $postal_code ) ) {
			return null;
		}

		$cache_key     = self::PREFIX_SUCURSALES . $postal_code;
		$cached_result = get_transient( $cache_key );

		return $cached_result !== false ? $cached_result : null;
	}

	/**
	 * Guardar sucursales en cache
	 *
	 * @param string $postal_code Código postal
	 * @param array  $sucursales  Lista de sucursales
	 * @return bool
	 */
	public static function set_sucursales( $postal_code, $sucursales ) {
		if ( empty( $postal_code ) || is_wp_error( $sucursales ) || empty( $sucursales ) ) {
			return false;
		}

		$cache_key = self::PREFIX_SUCURSALES . $postal_code;
		return set_transient( $cache_key, $sucursales, self::TTL_SUCURSALES );
	}

	/**
	 * Obtener cotización cacheada
	 *
	 * @param array $package Paquete de WooCommerce
	 * @return array|null
	 */
	public static function get_cotizacion( $package ) {
		if ( empty( $package ) ) {
			return null;
		}

		$cache_key     = self::PREFIX_COTIZACION . self::hash_package( $package );
		$cached_result = get_transient( $cache_key );

		return $cached_result !== false ? $cached_result : null;
	}

	/**
	 * Guardar cotización en cache
	 *
	 * @param array $package Paquete de WooCommerce
	 * @param array $result  Resultado de cotización
	 * @return bool
	 */
	public static function set_cotizacion( $package, $result ) {
		if ( empty( $package ) || is_wp_error( $result ) ) {
			return false;
		}

		if ( ! isset( $result['rates'] ) || empty( $result['rates'] ) ) {
			return false;
		}

		$cache_key = self::PREFIX_COTIZACION . self::hash_package( $package );
		return set_transient( $cache_key, $result, self::TTL_COTIZACION );
	}

	/**
	 * Limpiar cache de sucursales para un código postal
	 *
	 * @param string $postal_code Código postal (opcional, vacío para limpiar todo)
	 */
	public static function clear_sucursales( $postal_code = '' ) {
		if ( ! empty( $postal_code ) ) {
			delete_transient( self::PREFIX_SUCURSALES . $postal_code );
			return;
		}

		self::delete_transients_by_prefix( self::PREFIX_SUCURSALES );
	}

	/**
	 * Limpiar cache del origen (listado de sucursales de origen + predeterminada).
	 */
	public static function clear_origen() {
		self::delete_transients_by_prefix( self::PREFIX_SUCURSALES_ORIGEN );
		self::delete_transients_by_prefix( self::PREFIX_ORIGEN_DEFAULT );
	}

	/**
	 * Limpiar todas las cotizaciones cacheadas.
	 */
	public static function clear_cotizaciones() {
		self::delete_transients_by_prefix( self::PREFIX_COTIZACION );
	}

	/**
	 * @param string $prefix Prefijo de los transients a eliminar.
	 * @return int Cantidad de transients eliminados.
	 */
	private static function delete_transients_by_prefix( $prefix ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- No hay API de WP para enumerar transients por prefijo.
		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $prefix ) . '%'
			)
		);

		if ( empty( $names ) ) {
			return 0;
		}

		$deleted = 0;
		foreach ( $names as $option_name ) {
			$transient = substr( $option_name, strlen( '_transient_' ) );
			if ( delete_transient( $transient ) ) {
				$deleted++;
			}
		}

		return $deleted;
	}

	/**
	 * Construir clave de cache basada en método y argumentos
	 *
	 * @param string $method Nombre del método
	 * @param array  $args   Argumentos
	 * @return string|null Clave de cache o null
	 */
	private static function build_cache_key( $method, $args ) {
		switch ( $method ) {
			case 'get_sucursales':
				if ( ! empty( $args[0] ) ) {
					return self::PREFIX_SUCURSALES . $args[0];
				}
				break;

			case 'get_cotizacion':
				if ( ! empty( $args[0] ) ) {
					return self::PREFIX_COTIZACION . self::hash_package( $args[0] );
				}
				break;

			case 'get_sucursales_origen':
				if ( ! empty( $args[0] ) ) {
					return self::PREFIX_SUCURSALES_ORIGEN . md5( wp_json_encode( array( Andreani_Settings_Service::get( 'hash_andreani', '' ), $args ) ) );
				}
				break;

			case 'get_origen_predeterminado':
				if ( ! empty( $args[0] ) ) {
					return self::PREFIX_ORIGEN_DEFAULT . md5( wp_json_encode( array( Andreani_Settings_Service::get( 'hash_andreani', '' ), $args ) ) );
				}
				break;
		}

		return null;
	}

	/**
	 * Obtener TTL para un método específico
	 *
	 * @param string $method Nombre del método
	 * @param mixed  $result Resultado (para validaciones adicionales)
	 * @return int TTL en segundos
	 */
	private static function get_ttl( $method, $result ) {
		switch ( $method ) {
			case 'get_sucursales':
				return self::TTL_SUCURSALES;

			case 'get_cotizacion':
				return self::TTL_COTIZACION;

			case 'get_sucursales_origen':
				return self::TTL_SUCURSALES_ORIGEN;

			case 'get_origen_predeterminado':
				return self::TTL_ORIGEN_DEFAULT;

			default:
				return 0;
		}
	}

	/**
	 * @param array $package Paquete de WooCommerce
	 * @return string Hash MD5
	 */
	private static function hash_package( $package ) {
		$key_data = array();

		$key_data['client_type'] = Andreani_Settings_Service::get( 'hash_andreani', '' );
		$key_data['cp_origen']   = Andreani_Settings_Service::get( 'cp_origen', '' );
		$key_data['pin_origen']  = class_exists( 'Andreani_Origen' ) ? Andreani_Origen::get_pin() : '';

		if ( isset( $package['destination']['postcode'] ) ) {
			$key_data['postcode'] = $package['destination']['postcode'];
		}

		if ( isset( $package['contents'] ) ) {
			$items = array();
			foreach ( $package['contents'] as $item ) {
				$items[] = array(
					'id'  => isset( $item['product_id'] ) ? $item['product_id'] : 0,
					'qty' => isset( $item['quantity'] ) ? $item['quantity'] : 1,
				);
			}
			usort( $items, function( $a, $b ) {
				return $a['id'] - $b['id'];
			} );
			$key_data['items'] = $items;
		}

		if ( isset( $package['contents_cost'] ) ) {
			$key_data['cost'] = $package['contents_cost'];
		}

		if ( isset( $package['codigo_sucursal'] ) ) {
			$key_data['sucursal'] = $package['codigo_sucursal'];
		}

		return md5( wp_json_encode( $key_data ) );
	}
}
