<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Upgrader {

	/**
	 * Opción de base de datos que almacena la versión actual del esquema.
	 */
	const DB_VERSION_OPTION = 'andreani_db_version';

	/**
	 * Lock del run de migraciones: dos pestañas de admin abiertas disparan
	 * `admin_init` en paralelo y ejecutarían las rutinas dos veces.
	 */
	const UPGRADE_LOCK_KEY = 'andreani_upgrade_lock';

	private static $upgrades = array(
		'1.4.0' => 'upgrade_1_4_0',
		'1.4.5' => 'upgrade_1_4_5',
		'1.5.0' => 'upgrade_1_5_0',
		'1.5.2' => 'upgrade_1_5_2',
		'1.6.4' => 'upgrade_1_6_4',
	);

	public static function maybe_upgrade() {
		$db_version = get_option( self::DB_VERSION_OPTION, '0' );

		if ( version_compare( $db_version, ANDREANI_PLUGIN_VERSION, '>=' ) ) {
			return;
		}

		if ( get_transient( self::UPGRADE_LOCK_KEY ) ) {
			return;
		}

		set_transient( self::UPGRADE_LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			self::run_pending_upgrades( $db_version );
			update_option( self::DB_VERSION_OPTION, ANDREANI_PLUGIN_VERSION, false );
		} finally {
			delete_transient( self::UPGRADE_LOCK_KEY );
		}
	}

	private static function run_pending_upgrades( $from_version ) {
		if ( '0' === $from_version ) {
			return;
		}

		foreach ( self::$upgrades as $version => $method ) {
			if ( version_compare( $from_version, $version, '<' ) && method_exists( __CLASS__, $method ) ) {
				Andreani_Utils::andreani_log( "[UPGRADE] Ejecutando migración a v{$version}", 'info' );
				
				try {
					call_user_func( array( __CLASS__, $method ) );
					Andreani_Utils::andreani_log( "[UPGRADE] Migración a v{$version} completada", 'info' );
				} catch ( \Throwable $e ) {
					Andreani_Utils::andreani_log( "[UPGRADE] Error en migración a v{$version}: " . $e->getMessage(), 'error' );
				}
			}
		}
	}

	private static function upgrade_1_4_0() {
		delete_option( 'andreani_pyme_info' );
		Andreani_Utils::andreani_log( '[UPGRADE 1.4.0] Cache Pyme limpiado', 'info' );
	}

	/**
	 * Agrega 'costo_adicional_enabled' a todas las instancias si no existe, inferido
	 * desde si hay costo_adicional > 0.
	 */
	private static function upgrade_1_4_5() {
		global $wpdb;

		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value 
				FROM {$wpdb->options} 
				WHERE option_name LIKE %s 
				AND option_name LIKE %s",
				'woocommerce_andreani_%',
				'%_settings'
			)
		);

		if ( empty( $instances ) ) {
			Andreani_Utils::andreani_log( '[UPGRADE 1.4.5] No se encontraron instancias de Andreani', 'info' );
			return;
		}

		$migrated_count = 0;

		foreach ( $instances as $row ) {
			$settings = maybe_unserialize( $row->option_value );

			if ( ! is_array( $settings ) ) {
				continue;
			}

			$config_por_modo_json = isset( $settings['config_por_modo'] ) ? $settings['config_por_modo'] : '{}';
			$config_por_modo = json_decode( $config_por_modo_json, true );

			if ( ! is_array( $config_por_modo ) || empty( $config_por_modo ) ) {
				continue;
			}

			$changed = false;

			foreach ( $config_por_modo as $modo_id => &$modo_config ) {
				if ( ! is_array( $modo_config ) ) {
					continue;
				}
				if ( ! isset( $modo_config['costo_adicional_enabled'] ) ) {
					$tiene_costo = isset( $modo_config['costo_adicional'] ) && $modo_config['costo_adicional'] > 0;
					$modo_config['costo_adicional_enabled'] = $tiene_costo;
					$changed = true;
				}
			}
			unset( $modo_config );

			if ( $changed ) {
				$settings['config_por_modo'] = wp_json_encode( $config_por_modo );
				update_option( $row->option_name, $settings, false );
				$migrated_count++;
			}
		}

		Andreani_Utils::andreani_log( "[UPGRADE 1.4.5] {$migrated_count} instancia(s) migradas", 'info' );
	}

	/**
	 * Migración a versión 1.5.0
	 *
	 * Siembra los defaults de los nuevos campos de checkout (checkout_modo, checkout_force_enqueue)
	 * en todas las instancias existentes. Además fuerza un re-login contra la API de Andreani
	 * para cada instancia, requerido por la nueva funcionalidad de persistencia de sesión
	 * introducida en la versión central de la API.
	 *
	 * @return void
	 */
	private static function upgrade_1_5_0() {
		global $wpdb;

		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s",
				'woocommerce_andreani_%',
				'%_settings'
			)
		);

		if ( empty( $instances ) ) {
			Andreani_Utils::andreani_log( '[UPGRADE 1.5.0] No se encontraron instancias de Andreani', 'info' );
			return;
		}

		$migrated_count   = 0;
		$relogged_ok      = 0;
		$expired_count    = 0;
		$transient_count  = 0;
		$error_count      = 0;

		// Arrancamos con el flag limpio — si hay expiradas en este run, se marcan abajo.
		delete_option( 'andreani_expired_credentials' );

		foreach ( $instances as $row ) {
			try {
				$settings = maybe_unserialize( $row->option_value );

				if ( ! is_array( $settings ) ) {
					continue;
				}

				$changed = false;

				if ( ! isset( $settings['checkout_modo'] ) ) {
					$settings['checkout_modo'] = 'auto';
					$changed                   = true;
				}

				if ( ! isset( $settings['checkout_force_enqueue'] ) ) {
					$settings['checkout_force_enqueue'] = 'no';
					$changed                            = true;
				}

				// Normalizar keys de config_por_modo a slug ascii-safe.
				// Configuraciones guardadas antes de 1.5.0 tenían keys como "estándar",
				// "llega hoy", etc., que ahora pasan a "estandar", "llega-hoy". Los lookups
				// en runtime tienen fallback a la key cruda, pero normalizar en DB simplifica
				// el modelo mental y evita depender del fallback de por vida.
				if ( isset( $settings['config_por_modo'] ) ) {
					$config_json = $settings['config_por_modo'];
					$config      = is_string( $config_json ) ? json_decode( $config_json, true ) : null;
					if ( is_array( $config ) && ! empty( $config ) ) {
						$normalized    = array();
						$needs_rewrite = false;
						foreach ( $config as $raw_key => $modo_config ) {
							if ( ! is_array( $modo_config ) ) {
								continue;
							}
							$slug = Andreani_Api_Response::normalize_modo_key( $raw_key );
							if ( '' === $slug ) {
								continue;
							}
							if ( $slug !== $raw_key ) {
								$needs_rewrite = true;
							}
							// Si dos raw keys colapsan al mismo slug (ej. "Estándar" y "estándar"),
							// gana la primera que tenga datos — merge preserva campos ausentes.
							$normalized[ $slug ] = isset( $normalized[ $slug ] )
								? array_merge( $modo_config, $normalized[ $slug ] )
								: $modo_config;
						}
						if ( $needs_rewrite ) {
							$settings['config_por_modo'] = wp_json_encode( $normalized );
							$changed                     = true;
						}
					}
				}

				$hash = isset( $settings['hash_andreani'] ) ? $settings['hash_andreani'] : '';
				if ( ! empty( $hash ) ) {
					$relogin = self::attempt_relogin( $hash );

					switch ( $relogin['status'] ) {
						case 'ok':
							$relogged_ok++;
							Andreani_Utils::andreani_log(
								"[UPGRADE 1.5.0] Re-login OK para {$row->option_name} (HTTP {$relogin['http_code']}, {$relogin['attempts']} intento/s)",
								'info'
							);
							break;

						case 'expired':
							// Credencial expirada o inválida — reintentar no sirve (siempre daría igual).
							// Limpiamos solo el hash/tipo_cliente del settings, preservamos el resto de la config.
							// También limpiamos los options de info del cliente (corporativo/pyme) para que
							// al re-ingresar una credencial nueva se regeneren desde cero.
							$expired_count++;
							self::clean_expired_credential( $settings );
							self::mark_credential_expired( $row->option_name );
							$changed = true;
							Andreani_Utils::andreani_log(
								"[UPGRADE 1.5.0] Credencial expirada en {$row->option_name} (HTTP {$relogin['http_code']}). hash y tipo_cliente removidos; config de envíos preservada.",
								'warning'
							);
							break;

						case 'transient':
							// 5xx o timeout/network — el server de Andreani puede estar intermitente.
							// NO tocamos la credencial, solo loggeamos para que el admin vea el incidente.
							// La próxima vez que se use el plugin, el flujo normal va a re-autenticar si hace falta.
							$transient_count++;
							Andreani_Utils::andreani_log(
								"[UPGRADE 1.5.0] Re-login con error transitorio en {$row->option_name} (HTTP {$relogin['http_code']}, {$relogin['attempts']} intento/s): {$relogin['error']}. Credencial preservada.",
								'error'
							);
							break;

						default: // 'error'
							// Otros casos (4xx distintos de 401/403, o problema interno). Tampoco tocamos credencial.
							$error_count++;
							Andreani_Utils::andreani_log(
								"[UPGRADE 1.5.0] Re-login falló en {$row->option_name} (HTTP {$relogin['http_code']}): {$relogin['error']}. Credencial preservada.",
								'error'
							);
							break;
					}
				}

				if ( $changed ) {
					update_option( $row->option_name, $settings, false );
					$migrated_count++;
				}
			} catch ( \Throwable $e ) {
				// Un error en una instancia no debe bloquear el resto.
				Andreani_Utils::andreani_log( "[UPGRADE 1.5.0] Error procesando {$row->option_name}: " . $e->getMessage(), 'error' );
			}
		}

		Andreani_Utils::andreani_log(
			"[UPGRADE 1.5.0] Completado: {$migrated_count} instancias migradas, " .
			"{$relogged_ok} re-login OK, {$expired_count} credenciales expiradas (limpiadas), " .
			"{$transient_count} errores transitorios, {$error_count} otros errores",
			'info'
		);
	}

	/**
	 * Intenta re-loggear contra la API central con retry por errores transitorios.
	 *
	 * Clasificación del resultado:
	 *   - ok       → 2xx, credencial válida y persistida.
	 *   - expired  → 401 o 403, credencial expirada/inválida (no se reintenta).
	 *   - transient → 5xx, timeout, network error (se reintenta hasta max_attempts).
	 *   - error    → otros 4xx u errores inesperados (no se reintenta).
	 *
	 * El retry usa backoff lineal corto (1s, 2s) para no demorar el admin_init.
	 * Máximo 3 intentos y ~3 segundos de espera total en el peor caso.
	 *
	 * @param string $hash         Credencial del cliente.
	 * @param int    $max_attempts Límite de intentos (default 3).
	 * @return array { status, http_code, error, attempts }
	 */
	private static function attempt_relogin( $hash, $max_attempts = 3 ) {
		$tipo_cliente = Andreani_Utils::detect_client_type_from_hash( $hash );
		if ( empty( $tipo_cliente ) ) {
			return array( 'status' => 'expired', 'http_code' => 0, 'error' => 'Hash sin tipo de cliente decodificable', 'attempts' => 0 );
		}

		if ( ! class_exists( 'Andreani_Api_Config' ) ) {
			return array( 'status' => 'error', 'http_code' => 0, 'error' => 'Andreani_Api_Config no disponible', 'attempts' => 0 );
		}

		$endpoints = Andreani_Api_Config::get_endpoints( $tipo_cliente );
		if ( empty( $endpoints['login'] ) ) {
			return array( 'status' => 'error', 'http_code' => 0, 'error' => 'Endpoint de login no configurado', 'attempts' => 0 );
		}

		$last_result = array( 'status' => 'error', 'http_code' => 0, 'error' => 'No se ejecutó ningún intento', 'attempts' => 0 );

		for ( $attempt = 1; $attempt <= $max_attempts; $attempt++ ) {
			$result               = self::do_relogin_http( $endpoints['login'], $hash );
			$result['attempts']   = $attempt;
			$last_result          = $result;

			if ( 'ok' === $result['status'] || 'expired' === $result['status'] || 'error' === $result['status'] ) {
				break;
			}

			if ( $attempt < $max_attempts ) {
				sleep( min( $attempt, 2 ) );
			}
		}

		// Si el re-login fue OK, delegar en validate_hash() del API para persistir client_info
		// (lista de contratos, razón social, tokens, etc.) con la lógica ya existente de corpo/pyme.
		if ( 'ok' === $last_result['status'] && class_exists( 'Andreani_Api_Manager' ) ) {
			try {
				Andreani_Api_Manager::validate_hash( $hash );
			} catch ( \Throwable $e ) {
				Andreani_Utils::andreani_log( '[UPGRADE 1.5.0] Post-relogin validate_hash lanzó excepción: ' . $e->getMessage(), 'warning' );
			}
		}

		return $last_result;
	}

	/**
	 * Ejecuta una sola request HTTP al endpoint de login y clasifica el resultado.
	 * Usa wp_remote_request directo (en vez de Andreani_Utils::make_request) para
	 * tener acceso preciso al status HTTP, que make_request encapsula en un string.
	 *
	 * @param string $login_url Endpoint de login resuelto.
	 * @param string $hash      Credencial a validar.
	 * @return array { status, http_code, error }
	 */
	private static function do_relogin_http( $login_url, $hash ) {
		$response = wp_remote_request( $login_url, array(
			'method'      => 'POST',
			'headers'     => array(
				'Authorization' => $hash,
				'Content-Type'  => 'application/json',
			),
			'timeout'     => 20,
			'httpversion' => '1.1',
		) );

		if ( is_wp_error( $response ) ) {
			// Errores de red, DNS, SSL, timeout — tratar como transitorios.
			return array( 'status' => 'transient', 'http_code' => 0, 'error' => $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return array( 'status' => 'ok', 'http_code' => $code, 'error' => '' );
		}

		if ( 401 === $code || 403 === $code ) {
			return array( 'status' => 'expired', 'http_code' => $code, 'error' => 'Credencial expirada o inválida' );
		}

		if ( $code >= 500 && $code < 600 ) {
			return array( 'status' => 'transient', 'http_code' => $code, 'error' => "Error del servidor ({$code})" );
		}

		return array( 'status' => 'error', 'http_code' => $code, 'error' => "HTTP {$code}" );
	}

	/**
	 * Limpia los datos de credencial de un array de settings.
	 * Preserva el resto de la configuración (CP origen, config_por_modo, cotizador, etc.)
	 * para que el cliente no pierda sus ajustes.
	 *
	 * @param array $settings Settings por referencia — se modifica in-place.
	 */
	private static function clean_expired_credential( &$settings ) {
		unset( $settings['hash_andreani'], $settings['tipo_cliente'] );

		// Info del cliente descargada de la API: se regenera al próximo login exitoso.
		if ( class_exists( 'Andreani_Client_Type' ) ) {
			foreach ( Andreani_Client_Type::all_info_option_names() as $option_name ) {
				delete_option( $option_name );
			}
		}
		delete_option( 'andreani_cp_origen_valid' );
	}

	/**
	 * Marca una instancia como con credencial expirada. Se persiste en un array
	 * de options globales (una sola row, no una por instancia) para que el
	 * admin_notice hook lo lea sin tener que escanear todas las zonas.
	 *
	 * @param string $option_name Nombre del option de la instancia (ej. woocommerce_andreani_flexipaas_1_settings).
	 */
	private static function mark_credential_expired( $option_name ) {
		$expired = get_option( 'andreani_expired_credentials', array() );
		if ( ! is_array( $expired ) ) {
			$expired = array();
		}
		if ( ! in_array( $option_name, $expired, true ) ) {
			$expired[] = $option_name;
		}
		update_option( 'andreani_expired_credentials', $expired, false );
	}

	/**
	 * Registra hooks de admin notices. Se llama una vez desde Andreani_Plugin::init().
	 */
	public static function register_admin_notices() {
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_expired_credential_notice' ) );
	}

	/**
	 * Muestra un admin notice persistente si alguna instancia tiene credencial expirada.
	 * El notice se auto-elimina cuando el admin re-ingresa una credencial válida
	 * (el save del shipping method remueve el flag; ver clear_expired_credential_flag).
	 */
	public static function maybe_show_expired_credential_notice() {
		$expired = get_option( 'andreani_expired_credentials', array() );
		if ( empty( $expired ) || ! is_array( $expired ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$link = admin_url( 'admin.php?page=wc-settings&tab=shipping' );
		echo '<div class="notice notice-warning"><p>';
		echo '<strong>' . esc_html__( 'Andreani WooCommerce', 'andreani-shipping' ) . ':</strong> ';
		echo esc_html( sprintf(
			/* translators: %d: número de instancias con credencial expirada */
			_n(
				'tu credencial de Andreani expiró en %d zona de envío durante la última actualización.',
				'tu credencial de Andreani expiró en %d zonas de envío durante la última actualización.',
				count( $expired ),
				'andreani-shipping'
			),
			count( $expired )
		) );
		echo ' ';
		echo esc_html__( 'Re-ingresala para reactivar los envíos. La configuración de zonas, modos y cotizador se preservó.', 'andreani-shipping' );
		echo ' <a href="' . esc_url( $link ) . '">' . esc_html__( 'Ir a Zonas de envío', 'andreani-shipping' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Remueve una instancia del array de credenciales expiradas. Llamado cuando
	 * el admin guarda una credencial nueva en el shipping method (ver hook en
	 * Andreani_Shipping::process_admin_options).
	 *
	 * @param string $option_name Nombre del option de la instancia.
	 */
	public static function clear_expired_credential_flag( $option_name ) {
		$expired = get_option( 'andreani_expired_credentials', array() );
		if ( ! is_array( $expired ) ) {
			return;
		}
		$expired = array_values( array_diff( $expired, array( $option_name ) ) );
		if ( empty( $expired ) ) {
			delete_option( 'andreani_expired_credentials' );
		} else {
			update_option( 'andreani_expired_credentials', $expired, false );
		}
	}

	/**
	 * Migración v1.5.2: persiste el `tipo_cliente` explícito en cada instancia
	 * de shipping zone y detecta Middle Market desde `andreani_pyme_info`.
	 *
	 * Antes de v1.5.2 el `tipo_cliente` se derivaba del hash en cada lectura.
	 * Ese mecanismo solo distingue Pyme vs Corpo, pero hay dos sabores que comparten
	 * el hash de Pyme (Pyme tradicional y Middle Market).
	 * El tipo resuelto se obtiene del `clientType` del response.
	 *
	 * 100% offline — no hace requests HTTP. Usa info ya persistida.
	 */
	private static function upgrade_1_5_2() {
		global $wpdb;

		$pyme_info = get_option( 'andreani_pyme_info', null );
		$is_middle_market = false;

		if ( is_array( $pyme_info ) && ! empty( $pyme_info['clientType'] ) ) {
			$normalized       = strtolower( trim( (string) $pyme_info['clientType'] ) );
			$is_middle_market = ( 'middle_market' === $normalized );
		}

		if ( $is_middle_market ) {
			update_option( 'andreani_middlemarket_info', $pyme_info, false );
			delete_option( 'andreani_pyme_info' );
			Andreani_Utils::andreani_log( '[UPGRADE 1.5.2] andreani_pyme_info movido a andreani_middlemarket_info (clientType=middle_market)', 'info' );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s",
				'woocommerce_andreani_%',
				'%_settings'
			)
		);

		if ( empty( $instances ) ) {
			Andreani_Utils::andreani_log( '[UPGRADE 1.5.2] No se encontraron instancias de Andreani', 'info' );
			return;
		}

		$migrated_count = 0;

		foreach ( $instances as $row ) {
			$settings = maybe_unserialize( $row->option_value );
			if ( ! is_array( $settings ) ) {
				continue;
			}

			$hash = isset( $settings['hash_andreani'] ) ? $settings['hash_andreani'] : '';
			if ( empty( $hash ) ) {
				continue;
			}

			$current_tipo = isset( $settings['tipo_cliente'] ) ? $settings['tipo_cliente'] : '';
			$detected     = Andreani_Utils::detect_client_type_from_hash( $hash );

			$new_tipo = $current_tipo;

			if ( empty( $new_tipo ) && ! empty( $detected ) ) {
				$new_tipo = $detected;
			}

			if ( $is_middle_market && 'pyme' === $new_tipo ) {
				$new_tipo = 'middle_market';
			}

			if ( ! empty( $new_tipo ) && $new_tipo !== $current_tipo ) {
				$settings['tipo_cliente'] = $new_tipo;
				update_option( $row->option_name, $settings, false );
				$migrated_count++;
			}
		}

		Andreani_Utils::andreani_log( "[UPGRADE 1.5.2] {$migrated_count} instancia(s) con tipo_cliente persistido", 'info' );
	}

	/**
	 * Migración v1.6.4: prepara el origen configurable.
	 *
	 * Siembra la dirección de origen desde los datos de la tienda, deja el modo en
	 * automático y normaliza el `cp_origen` de cada instancia (un CPA crudo rompe
	 * el alta contra Andreani). NO fija ninguna sucursal: el comportamiento de las
	 * tiendas que no configuren nada tiene que quedar idéntico al de 1.6.3.
	 *
	 * 100% offline — no hace requests HTTP.
	 */
	private static function upgrade_1_6_4() {
		global $wpdb;

		$sembrado = false;
		if ( class_exists( 'Andreani_Origen' ) ) {
			$sembrado = Andreani_Origen::seed_from_woocommerce();

			$origen = Andreani_Origen::get();
			if ( Andreani_Origen::MODO_AUTO !== $origen['modo'] && '' === (string) $origen['sucursal_codigo'] ) {
				Andreani_Origen::save( array( 'modo' => Andreani_Origen::MODO_AUTO ) );
			}

			// Sin request: solo deja la marca para que el envio salga despues del upgrade.
			Andreani_Origen::marcar_push_pendiente();
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s",
				'woocommerce_andreani_%',
				'%_settings'
			)
		);

		$normalizadas = 0;
		$revisadas    = 0;

		if ( ! empty( $instances ) ) {
			foreach ( $instances as $row ) {
				$settings = maybe_unserialize( $row->option_value );
				if ( ! is_array( $settings ) || ! isset( $settings['cp_origen'] ) ) {
					continue;
				}

				$revisadas++;

				$actual     = (string) $settings['cp_origen'];
				$normalizado = Andreani_Postcode::normalize( $actual );

				if ( $normalizado === $actual ) {
					continue;
				}

				$settings['cp_origen'] = $normalizado;
				update_option( $row->option_name, $settings, false );
				$normalizadas++;
			}
		}

		if ( class_exists( 'Andreani_Api_Manager' ) ) {
			Andreani_Api_Manager::clear_settings_cache();
		}

		$sembrado_label = $sembrado ? 'sí' : 'no';
		Andreani_Utils::andreani_log(
			"[UPGRADE 1.6.4] Origen preparado en modo automático. Dirección sembrada desde la tienda: {$sembrado_label}. " .
			"Instancias revisadas: {$revisadas}, CP origen normalizados: {$normalizadas}",
			'info'
		);
	}
}
