<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/services/andreani-settings-service.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/services/andreani-cache-service.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/origen/class-andreani-origen.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/andreani-api-interface.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-client-type.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-contract.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-postcode.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-order-mapper.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-response.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-base-api.php';
require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-utils.php';

class Andreani_Api_Manager {
    private static $api_instance = null;
    private static $last_validation_error = null;

    public static function get_api() {
        if ( null === self::$api_instance ) {
            self::initialize_from_settings();
        }
        return self::$api_instance;
    }

    public static function is_api_available() {
        return self::get_api() !== null;
    }

    private static function initialize_from_settings() {
        $settings = Andreani_Settings_Service::get_settings();

        if ( empty( $settings ) ) {
            Andreani_Utils::andreani_log( '[CONFIG] No hay configuración activa de Andreani', 'warning' );
            return;
        }

        try {
            self::$api_instance = self::create_api_instance( $settings );
        } catch ( Exception $e ) {
            Andreani_Utils::andreani_log( '[CONFIG] Error al inicializar API: ' . $e->getMessage(), 'error' );
            self::$api_instance = null;
        }
    }

    public static function get_client_type() {
        return Andreani_Settings_Service::get_client_type();
    }

    private static function create_api_instance( $settings ) {
        if ( empty( $settings['hash_andreani'] ) ) {
            Andreani_Utils::andreani_log( '[CONFIG] Credencial ID no configurada', 'error' );
            return null;
        }

        $tipo_cliente = isset( $settings['tipo_cliente'] ) && ! empty( $settings['tipo_cliente'] )
            ? $settings['tipo_cliente']
            : Andreani_Utils::detect_client_type_from_hash( $settings['hash_andreani'] );

        if ( empty( $tipo_cliente ) ) {
            Andreani_Utils::andreani_log( '[CONFIG] No se pudo detectar tipo de cliente', 'error' );
            return null;
        }

        $config = array(
            'hash_andreani'         => $settings['hash_andreani'],
            'cp_origen'             => isset( $settings['cp_origen'] ) ? $settings['cp_origen'] : '',
            'mostrar_sin_decimales' => isset( $settings['mostrar_sin_decimales'] ) ? $settings['mostrar_sin_decimales'] === 'yes' : false,
        );

        try {
            if ( $tipo_cliente === 'pyme' ) {
                if ( ! class_exists( 'Andreani_Pyme_Api' ) ) {
                    require_once ANDREANI_PLUGIN_DIR . 'includes/api/pyme/andreani-pyme.php';
                }
                $api = Andreani_Pyme_Api::get_instance();
            } elseif ( $tipo_cliente === 'middle_market' ) {
                if ( ! class_exists( 'Andreani_Middle_Market_Api' ) ) {
                    require_once ANDREANI_PLUGIN_DIR . 'includes/api/middlemarket/andreani-middlemarket.php';
                }
                $api = Andreani_Middle_Market_Api::get_instance();
            } else {
                if ( ! class_exists( 'Andreani_Corpo_Api' ) ) {
                    require_once ANDREANI_PLUGIN_DIR . 'includes/api/corpo/andreani-corpo.php';
                }
                $api = Andreani_Corpo_Api::get_instance();
            }

            $api->init_with_credentials( $config );
            return $api;

        } catch ( Exception $e ) {
            Andreani_Utils::andreani_log( "[CONFIG] Error API ({$tipo_cliente}): " . $e->getMessage(), 'error' );
            return null;
        }
    }

    public static function __callStatic( $method, $args ) {
        $cache_result = Andreani_Cache_Service::get( $method, $args );
        if ( $cache_result !== null ) {
            return $cache_result;
        }

        $api = self::get_api();
        if ( ! $api ) {
            return null;
        }

        $result = call_user_func_array( array( $api, $method ), $args );
        Andreani_Cache_Service::set( $method, $args, $result );

        return $result;
    }

    /**
     * El hash distingue solo Pyme vs Corpo. Si es Pyme, refinamos a Middle Market
     * leyendo `clientType` del response (normalizado en snake_case).
     *
     * @return string|false Tipo resuelto ('pyme'|'middle_market'|'corporativo') o false.
     */
    public static function validate_hash( $hash, $tipo_cliente = null ) {
        self::$last_validation_error = null;

        if ( empty( $tipo_cliente ) ) {
            $tipo_cliente = Andreani_Utils::detect_client_type_from_hash( $hash );
        }

        if ( empty( $tipo_cliente ) ) {
            return false;
        }

        $temp_settings = array(
            'tipo_cliente'   => $tipo_cliente,
            'hash_andreani'  => $hash,
            'cp_origen'      => '',
        );

        $temp_api = self::create_api_instance( $temp_settings );
        if ( ! $temp_api ) {
            return false;
        }

        $valid = $temp_api->validate_hash( $hash );
        if ( ! $valid ) {
            self::$last_validation_error = $temp_api->get_last_validation_error();
            return false;
        }

        if ( 'pyme' === $tipo_cliente ) {
            $pyme_info = get_option( Andreani_Pyme_Api::CLIENT_INFO_OPTION, null );
            $resolved  = Andreani_Utils::detect_client_type_from_login_response( $pyme_info );
            if ( 'middle_market' === $resolved ) {
                if ( ! class_exists( 'Andreani_Middle_Market_Api' ) ) {
                    require_once ANDREANI_PLUGIN_DIR . 'includes/api/middlemarket/andreani-middlemarket.php';
                }
                update_option( Andreani_Middle_Market_Api::CLIENT_INFO_OPTION, $pyme_info );
                delete_option( Andreani_Pyme_Api::CLIENT_INFO_OPTION );
                self::$api_instance = null;
                return Andreani_Client_Type::MIDDLE_MARKET;
            }
        }

        return $tipo_cliente;
    }

    /**
     * @return WP_Error|null Motivo del ultimo validate_hash fallido.
     */
    public static function get_last_validation_error() {
        return self::$last_validation_error;
    }

    public static function clear_settings_cache() {
        Andreani_Settings_Service::clear_cache();
        self::$api_instance = null;
    }
}
