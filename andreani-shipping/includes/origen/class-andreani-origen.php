<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Origen {

	const OPTION = 'andreani_origen';

	const MODO_AUTO   = 'auto';
	const MODO_FIJADA = 'fijada';

	const FUENTE_WOOCOMMERCE = 'woocommerce';
	const FUENTE_MANUAL      = 'manual';

	const REMITENTE_NOMBRE_MIN = 2;
	const REMITENTE_NOMBRE_MAX = 100;

	public static function defaults() {
		return array(
			'modo'                 => self::MODO_AUTO,
			'sucursal_codigo'      => '',
			'sucursal_nombre'      => '',
			'sucursal_direccion'   => '',
			'calle'                => '',
			'numero'               => '',
			'piso'                 => '',
			'localidad'            => '',
			'provincia'            => '',
			'remitente_nombre'     => '',
			'direccion_fuente'     => '',
			'direccion_confirmada' => 'no',
			'actualizado_en'       => '',
		);
	}

	/**
	 * @return array Origen completo, con todas las claves presentes.
	 */
	public static function get() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$origen = array();
		foreach ( self::defaults() as $key => $default ) {
			$origen[ $key ] = isset( $stored[ $key ] ) ? $stored[ $key ] : $default;
		}

		if ( ! in_array( $origen['modo'], array( self::MODO_AUTO, self::MODO_FIJADA ), true ) ) {
			$origen['modo'] = self::MODO_AUTO;
		}

		if ( self::MODO_FIJADA === $origen['modo'] && '' === (string) $origen['sucursal_codigo'] ) {
			$origen['modo'] = self::MODO_AUTO;
		}

		if ( ! in_array( $origen['direccion_fuente'], array( self::FUENTE_WOOCOMMERCE, self::FUENTE_MANUAL, '' ), true ) ) {
			$origen['direccion_fuente'] = self::FUENTE_MANUAL;
		}

		$origen['direccion_confirmada'] = 'yes' === $origen['direccion_confirmada'] ? 'yes' : 'no';

		return $origen;
	}

	/**
	 * @return string Código de la sucursal fijada, o '' si el origen es automático.
	 */
	public static function get_pin() {
		$origen = self::get();

		if ( self::MODO_FIJADA !== $origen['modo'] ) {
			return '';
		}

		return (string) $origen['sucursal_codigo'];
	}

	/**
	 * @param array $values Claves a sobrescribir; el resto se preserva.
	 * @return array Origen persistido.
	 */
	public static function save( array $values ) {
		$origen  = self::get();
		$allowed = self::defaults();

		foreach ( $values as $key => $value ) {
			if ( ! array_key_exists( $key, $allowed ) || ! is_scalar( $value ) ) {
				continue;
			}
			$origen[ $key ] = sanitize_text_field( (string) $value );
		}

		if ( ! in_array( $origen['modo'], array( self::MODO_AUTO, self::MODO_FIJADA ), true ) ) {
			$origen['modo'] = self::MODO_AUTO;
		}

		if ( self::MODO_AUTO === $origen['modo'] ) {
			$origen['sucursal_codigo']    = '';
			$origen['sucursal_nombre']    = '';
			$origen['sucursal_direccion'] = '';
		}

		$origen['remitente_nombre']     = self::sanitize_nombre_remitente( $origen['remitente_nombre'] );
		$origen['direccion_confirmada'] = 'yes' === $origen['direccion_confirmada'] ? 'yes' : 'no';
		$origen['actualizado_en']       = current_time( 'mysql' );

		update_option( self::OPTION, $origen, false );

		return $origen;
	}

	/**
	 * @param mixed $valor Nombre crudo.
	 * @return string Nombre saneado y recortado, o '' si no alcanza el mínimo.
	 */
	public static function sanitize_nombre_remitente( $valor ) {
		if ( ! is_scalar( $valor ) ) {
			return '';
		}

		$nombre = sanitize_text_field( (string) $valor );

		if ( mb_strlen( $nombre, 'UTF-8' ) < self::REMITENTE_NOMBRE_MIN ) {
			return '';
		}

		return mb_substr( $nombre, 0, self::REMITENTE_NOMBRE_MAX, 'UTF-8' );
	}

	/**
	 * Persiste los campos posteados como `andreani_origen[...]` desde el formulario
	 * nativo del método de envío (nonce ya verificado por WooCommerce).
	 *
	 * @param array|null $post_data Datos crudos; por defecto $_POST.
	 * @return array Origen persistido.
	 */
	public static function save_from_post( $post_data = null ) {
		if ( null === $post_data ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verificado por WooCommerce en el formulario de configuración.
			$post_data = isset( $_POST[ self::OPTION ] ) ? wp_unslash( $_POST[ self::OPTION ] ) : array();
		}

		if ( ! is_array( $post_data ) ) {
			return self::get();
		}

		$previo = self::get();
		$values = array();

		foreach ( array( 'calle', 'numero', 'piso', 'localidad', 'provincia' ) as $campo ) {
			if ( ! isset( $post_data[ $campo ] ) || ! is_scalar( $post_data[ $campo ] ) ) {
				continue;
			}
			$values[ $campo ] = sanitize_text_field( (string) $post_data[ $campo ] );
		}

		if ( ! empty( $values ) ) {
			$cambio_direccion = false;
			foreach ( $values as $campo => $valor ) {
				if ( $valor !== $previo[ $campo ] ) {
					$cambio_direccion = true;
				}
			}
			if ( $cambio_direccion ) {
				$desde_tienda = isset( $post_data['desde_tienda'] ) && '1' === (string) $post_data['desde_tienda'];

				$values['direccion_fuente'] = $desde_tienda ? self::FUENTE_WOOCOMMERCE : self::FUENTE_MANUAL;
			}
			$values['direccion_confirmada'] = 'yes';
		}

		if ( isset( $post_data['remitente_nombre'] ) && is_scalar( $post_data['remitente_nombre'] ) ) {
			$values['remitente_nombre'] = self::sanitize_nombre_remitente( $post_data['remitente_nombre'] );
		}

		if ( ! empty( $post_data['sucursal_presente'] ) ) {
			$codigo = isset( $post_data['sucursal_codigo'] ) && is_scalar( $post_data['sucursal_codigo'] )
				? sanitize_text_field( (string) $post_data['sucursal_codigo'] )
				: '';

			if ( '' === $codigo ) {
				$values['modo']               = self::MODO_AUTO;
				$values['sucursal_codigo']    = '';
				$values['sucursal_nombre']    = '';
				$values['sucursal_direccion'] = '';
			} else {
				$catalogo = isset( $post_data['sucursales'] ) && is_array( $post_data['sucursales'] )
					? $post_data['sucursales']
					: array();

				$nombre    = '';
				$direccion = '';
				if ( isset( $catalogo[ $codigo ] ) && is_array( $catalogo[ $codigo ] ) ) {
					$entrada   = $catalogo[ $codigo ];
					$nombre    = isset( $entrada['nombre'] ) && is_scalar( $entrada['nombre'] ) ? sanitize_text_field( (string) $entrada['nombre'] ) : '';
					$direccion = isset( $entrada['direccion'] ) && is_scalar( $entrada['direccion'] ) ? sanitize_text_field( (string) $entrada['direccion'] ) : '';
				}

				if ( '' === $nombre && $codigo === (string) $previo['sucursal_codigo'] ) {
					$nombre    = $previo['sucursal_nombre'];
					$direccion = $previo['sucursal_direccion'];
				}

				$values['modo']               = self::MODO_FIJADA;
				$values['sucursal_codigo']    = $codigo;
				$values['sucursal_nombre']    = $nombre;
				$values['sucursal_direccion'] = $direccion;
			}
		}

		if ( empty( $values ) ) {
			return $previo;
		}

		return self::save( $values );
	}

	/**
	 * @param string $motivo Se registra en el log para poder rastrear por qué se soltó el pin.
	 * @return bool true si había una sucursal fijada y se liberó.
	 */
	public static function reset_pin( $motivo = '' ) {
		$origen = self::get();

		if ( self::MODO_FIJADA !== $origen['modo'] ) {
			return false;
		}

		$codigo = (string) $origen['sucursal_codigo'];

		self::save(
			array(
				'modo'               => self::MODO_AUTO,
				'sucursal_codigo'    => '',
				'sucursal_nombre'    => '',
				'sucursal_direccion' => '',
			)
		);

		if ( class_exists( 'Andreani_Utils' ) ) {
			Andreani_Utils::andreani_log(
				"[ORIGEN] Sucursal fijada {$codigo} liberada: {$motivo}",
				'info'
			);
		}

		return true;
	}

	/**
	 * Completa la dirección de origen con los datos de la tienda y el nombre del
	 * remitente con el de la cuenta de Andreani. Idempotente: solo rellena campos
	 * vacíos, nunca pisa lo que el usuario cargó.
	 *
	 * @return bool true si se sembró al menos un campo.
	 */
	const OPTION_PUSH_PENDIENTE = 'andreani_origen_push_pendiente';

	/**
	 * Marca que este origen todavía no se le informó a Andreani. La lo envía el
	 * primer `admin_init` con credencial: el upgrader no puede hacer requests.
	 */
	public static function marcar_push_pendiente() {
		update_option( self::OPTION_PUSH_PENDIENTE, 'yes' );
	}

	/**
	 * Envía el origen a Andreani. Best-effort: ante un error se conserva la marca
	 * para reintentar en la próxima carga del admin.
	 *
	 * @param string|null $cp Código postal de origen. Si es null se toma de la configuración.
	 * @return bool Si Andreani lo recibió.
	 */
	public static function push_a_andreani( $cp = null ) {
		if ( ! class_exists( 'Andreani_Api_Manager' ) || ! Andreani_Api_Manager::is_api_available() ) {
			return false;
		}

		if ( null === $cp ) {
			$cp = (string) Andreani_Settings_Service::get( 'cp_origen', '' );
		}

		if ( '' === (string) $cp ) {
			return false;
		}

		$resultado = Andreani_Api_Manager::save_origen( self::get(), $cp );

		if ( is_wp_error( $resultado ) || null === $resultado ) {
			$motivo = is_wp_error( $resultado ) ? $resultado->get_error_message() : 'sin respuesta de la API';
			Andreani_Utils::andreani_log( '[ORIGEN] No se pudo enviar el origen a Andreani: ' . $motivo, 'warning' );
			return false;
		}

		delete_option( self::OPTION_PUSH_PENDIENTE );

		return true;
	}

	/**
	 * @return array Dirección tal como está cargada hoy en WooCommerce. Puede venir incompleta.
	 */
	public static function valores_de_woocommerce() {
		$valores = array(
			'calle'     => '',
			'numero'    => '',
			'piso'      => (string) get_option( 'woocommerce_store_address_2', '' ),
			'localidad' => (string) get_option( 'woocommerce_store_city', '' ),
			'provincia' => self::resolve_provincia_from_woocommerce(),
		);

		$direccion = (string) get_option( 'woocommerce_store_address', '' );
		if ( '' !== trim( $direccion ) && class_exists( 'Andreani_Order_Mapper' ) ) {
			$parsed            = Andreani_Order_Mapper::parse_address_street_number( $direccion );
			$valores['calle']  = ! empty( $parsed['street'] ) ? (string) $parsed['street'] : '';
			$valores['numero'] = ! empty( $parsed['number'] ) ? (string) $parsed['number'] : '';
		}

		return $valores;
	}

	public static function seed_from_woocommerce() {
		$origen = self::get();
		$values = array();

		$sembrar_direccion = self::direccion_coherente_con_cp(
			get_option( 'woocommerce_store_postcode', '' ),
			self::cps_origen_configurados()
		);

		if ( $sembrar_direccion ) {
			if ( '' === (string) $origen['calle'] || '' === (string) $origen['numero'] ) {
				$direccion = (string) get_option( 'woocommerce_store_address', '' );
				if ( '' !== trim( $direccion ) && class_exists( 'Andreani_Order_Mapper' ) ) {
					$parsed = Andreani_Order_Mapper::parse_address_street_number( $direccion );
					if ( '' === (string) $origen['calle'] && ! empty( $parsed['street'] ) ) {
						$values['calle'] = $parsed['street'];
					}
					if ( '' === (string) $origen['numero'] && ! empty( $parsed['number'] ) ) {
						$values['numero'] = $parsed['number'];
					}
				}
			}

			if ( '' === (string) $origen['piso'] ) {
				$piso = (string) get_option( 'woocommerce_store_address_2', '' );
				if ( '' !== trim( $piso ) ) {
					$values['piso'] = $piso;
				}
			}

			if ( '' === (string) $origen['localidad'] ) {
				$localidad = (string) get_option( 'woocommerce_store_city', '' );
				if ( '' !== trim( $localidad ) ) {
					$values['localidad'] = $localidad;
				}
			}

			if ( '' === (string) $origen['provincia'] ) {
				$provincia = self::resolve_provincia_from_woocommerce();
				if ( '' !== $provincia ) {
					$values['provincia'] = $provincia;
				}
			}

			if ( ! empty( $values ) && '' === (string) $origen['direccion_fuente'] ) {
				$values['direccion_fuente'] = self::FUENTE_WOOCOMMERCE;
			}
		}

		if ( '' === (string) $origen['remitente_nombre'] ) {
			foreach ( self::remitente_candidatos() as $candidato ) {
				$remitente = self::sanitize_nombre_remitente( $candidato );
				if ( '' !== $remitente ) {
					$values['remitente_nombre'] = $remitente;
					break;
				}
			}
		}

		if ( empty( $values ) ) {
			return false;
		}

		self::save( $values );

		return true;
	}

	/**
	 * @return bool true si no hay ningún cp_origen configurado, o si TODOS coinciden con el CP de la tienda.
	 */
	public static function direccion_coherente_con_cp( $cp_tienda, array $cps_instancias ) {
		$configurados = array();
		foreach ( $cps_instancias as $cp ) {
			if ( ! is_scalar( $cp ) ) {
				continue;
			}
			$normalizado = Andreani_Postcode::normalize( $cp );
			if ( '' !== $normalizado ) {
				$configurados[] = $normalizado;
			}
		}

		if ( empty( $configurados ) ) {
			return true;
		}

		$tienda = is_scalar( $cp_tienda ) ? Andreani_Postcode::normalize( $cp_tienda ) : '';
		if ( '' === $tienda ) {
			return false;
		}

		foreach ( $configurados as $cp ) {
			if ( $cp !== $tienda ) {
				return false;
			}
		}

		return true;
	}

	private static function cps_origen_configurados() {
		global $wpdb;

		$instances = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_value
				FROM {$wpdb->options}
				WHERE option_name LIKE %s
				AND option_name LIKE %s",
				'woocommerce_andreani_%',
				'%_settings'
			)
		);

		$cps = array();
		foreach ( (array) $instances as $row ) {
			$settings = maybe_unserialize( $row->option_value );
			if ( is_array( $settings ) && isset( $settings['cp_origen'] ) && is_scalar( $settings['cp_origen'] ) ) {
				$cps[] = (string) $settings['cp_origen'];
			}
		}

		return $cps;
	}

	private static function remitente_candidatos() {
		$candidatos = array();
		$info       = self::get_info_cuenta();

		foreach ( array( 'nombreFantasia', 'razonSocial' ) as $key ) {
			if ( isset( $info[ $key ] ) && is_scalar( $info[ $key ] ) ) {
				$candidatos[] = (string) $info[ $key ];
			}
		}

		$candidatos[] = (string) get_bloginfo( 'name' );

		return $candidatos;
	}

	private static function get_info_cuenta() {
		if ( ! class_exists( 'Andreani_Api_Manager' ) || ! class_exists( 'Andreani_Client_Type' ) ) {
			return array();
		}

		$type_info = Andreani_Client_Type::from_id( Andreani_Api_Manager::get_client_type() );
		if ( ! $type_info ) {
			return array();
		}

		$info = get_option( $type_info->info_option_name(), array() );

		return is_array( $info ) ? $info : array();
	}

	/**
	 * @return string Nombre de la provincia según `woocommerce_default_country` (formato `AR:C`).
	 */
	private static function resolve_provincia_from_woocommerce() {
		$country = (string) get_option( 'woocommerce_default_country', '' );

		if ( false === strpos( $country, ':' ) ) {
			return '';
		}

		$code = strtoupper( trim( substr( $country, strpos( $country, ':' ) + 1 ) ) );
		if ( '' === $code ) {
			return '';
		}

		if ( ! function_exists( 'WC' ) || ! WC()->countries ) {
			return '';
		}

		$states = WC()->countries->get_states( 'AR' );
		if ( ! is_array( $states ) || ! isset( $states[ $code ] ) ) {
			return '';
		}

		return (string) $states[ $code ];
	}
}
