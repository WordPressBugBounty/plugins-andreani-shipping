<?php
/**
 * Andreani_Settings_Service
 * Servicio centralizado para manejo de configuración del plugin
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Settings_Service {
	/**
	 * Cache en memoria de la configuración
	 *
	 * @var array|null
	 */
	private static $settings_cache = null;

	/**
	 * TTL para cache de settings en transients
	 */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/**
	 * Nombre del transient para settings
	 */
	const TRANSIENT_KEY = 'andreani_active_settings';

	/**
	 * Obtener la configuración activa (con cache multinivel)
	 *
	 * @return array Configuración activa o array vacío
	 */
	public static function get_settings() {
		if ( self::$settings_cache !== null ) {
			return self::$settings_cache;
		}

		$cached_settings = get_transient( self::TRANSIENT_KEY );
		if ( $cached_settings !== false && is_array( $cached_settings ) ) {
			self::$settings_cache = $cached_settings;
			return $cached_settings;
		}

		$settings = self::find_active_settings();

		if ( ! empty( $settings ) ) {
			set_transient( self::TRANSIENT_KEY, $settings, self::CACHE_TTL );
			self::$settings_cache = $settings;
		}

		return $settings;
	}

	/**
	 * Obtener un valor específico de la configuración
	 *
	 * @param string $key     Clave de configuración
	 * @param mixed  $default Valor por defecto
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Verificar si el modo debug está habilitado
	 *
	 * @return bool
	 */
	public static function is_debug_enabled() {
		return self::get( 'modo_debug', 'no' ) === 'yes';
	}

	/**
	 * Obtener el tipo de cliente configurado
	 *
	 * @return string 'pyme' o 'corporativo'
	 */
	public static function get_client_type() {
		$tipo = self::get( 'tipo_cliente', '' );

		if ( empty( $tipo ) ) {
			$hash = self::get( 'hash_andreani', '' );
			if ( ! empty( $hash ) && class_exists( 'Andreani_Utils' ) ) {
				$tipo = Andreani_Utils::detect_client_type_from_hash( $hash );
			}
		}

		return $tipo ?: 'corporativo';
	}

	/**
	 * Verificar si hay configuración válida
	 *
	 * @return bool
	 */
	public static function has_valid_credentials() {
		$settings = self::get_settings();
		return ! empty( $settings['hash_andreani'] ) && ! empty( $settings['cp_origen'] );
	}

	/**
	 * Limpiar todos los caches de configuración
	 */
	public static function clear_cache() {
		delete_transient( self::TRANSIENT_KEY );
		self::$settings_cache = null;
		wp_cache_delete( self::get_query_cache_key(), 'andreani' );
	}

	/**
	 * Buscar configuración activa en la base de datos
	 *
	 * @return array Configuración encontrada o array vacío
	 */
	private static function find_active_settings() {
		global $wpdb;

		$option_pattern = $wpdb->esc_like( 'woocommerce_' . ANDREANI_SHIPPING_METHOD_ID . '_' ) . '%' . $wpdb->esc_like( '_settings' );
		$cache_key      = self::get_query_cache_key();

		$results = wp_cache_get( $cache_key, 'andreani' );

		if ( false === $results ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Necesaria para buscar configuración con patrón LIKE
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
					$option_pattern
				),
				ARRAY_A
			);

			wp_cache_set( $cache_key, $results, 'andreani', 5 * MINUTE_IN_SECONDS );
		}

		if ( empty( $results ) ) {
			return array();
		}

		foreach ( $results as $row ) {
			$settings = maybe_unserialize( $row['option_value'] );

			if ( ! is_array( $settings ) ) {
				continue;
			}

			if ( ! empty( $settings['hash_andreani'] ) && ! empty( $settings['cp_origen'] ) ) {
				if ( preg_match( '/woocommerce_' . preg_quote( ANDREANI_SHIPPING_METHOD_ID, '/' ) . '_(\d+)_settings/', $row['option_name'], $matches ) ) {
					return $settings;
				}
			}
		}

		return array();
	}

	/**
	 * Generar clave de cache para la query SQL
	 *
	 * @return string
	 */
	private static function get_query_cache_key() {
		$option_pattern = 'woocommerce_' . ANDREANI_SHIPPING_METHOD_ID . '_%_settings';
		return 'andreani_settings_query_' . md5( $option_pattern );
	}
}
