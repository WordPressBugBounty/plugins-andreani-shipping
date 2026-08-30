<?php
defined( 'ABSPATH' ) || exit;

class Andreani_Utils {

    /**
     * Realizar petición HTTP genérica
     *
     * @param string $method   Método HTTP (GET, POST, PUT, DELETE)
     * @param string $endpoint URL del endpoint
     * @param array  $body     Datos del cuerpo (opcional)
     * @param array  $headers  Headers HTTP (opcional)
     * @param int    $retries  Número de reintentos en caso de error (opcional, por defecto 0)
     * @param int    $timeout  Timeout en segundos (opcional, por defecto 15)
     * @return array|WP_Error
     */
    public static function make_request( $method, $endpoint, $body = array(), $headers = array('Content-Type' => 'application/json'), $retries = 0, $timeout = null ) {
        if ( null === $timeout || $timeout <= 0 ) {
            $timeout = 30;
        }

        $args = array(
            'method'  => $method,
            'headers' => $headers,
            'timeout' => $timeout,
            'httpversion' => '1.1',
        );

        if ( ! empty( $body ) && 'GET' !== $method ) {
            $args['body'] = wp_json_encode( $body );
        }

        $endpoint_path = wp_parse_url( $endpoint, PHP_URL_PATH );
        $endpoint_path = $endpoint_path ? basename( $endpoint_path ) : 'unknown';

        // Los errores se registran aunque el modo debug este apagado, pero el body lleva
        // datos personales del comprador (DNI, email, telefono): solo se adjunta con debug.
        $body_log = '';
        if ( ! empty( $body ) && 'GET' !== $method && self::is_debug_mode_enabled() ) {
            $body_log = ' | Body: ' . wp_json_encode( $body );
        }

        $attempt = 0;
        $start_time = microtime( true );

        while ( $attempt <= $retries ) {
            $response = wp_remote_request( $endpoint, $args );
            $elapsed_ms = round( ( microtime( true ) - $start_time ) * 1000 );

            if ( is_wp_error( $response ) ) {
                $error_code = $response->get_error_code();
                $error_msg = $response->get_error_message();
                self::andreani_log( "[HTTP] {$method} /{$endpoint_path} - Error de conexión: [{$error_code}] {$error_msg} (intento " . ( $attempt + 1 ) . ", {$elapsed_ms}ms){$body_log}", 'error' );
                if ( ++$attempt > $retries ) return $response;
                self::sleep_retry($attempt);
                $start_time = microtime( true );
                continue;
            }

            $response_code = wp_remote_retrieve_response_code( $response );
            if ( $response_code < 200 || $response_code >= 300 ) {
                $response_body = wp_remote_retrieve_body( $response );
                $error_detail = self::extract_api_error_message( $response_body );
                self::andreani_log( "[HTTP] {$method} /{$endpoint_path} - Error {$response_code}: {$error_detail} (intento " . ( $attempt + 1 ) . ", {$elapsed_ms}ms){$body_log}", 'error' );
                if ( ++$attempt > $retries ) return new WP_Error( 'http_error', "Error HTTP {$response_code}: {$error_detail}", array( 'status' => $response_code ) );
                self::sleep_retry($attempt);
                $start_time = microtime( true );
                continue;
            }

            $response_body = wp_remote_retrieve_body( $response );
            self::andreani_log( "[HTTP] {$method} /{$endpoint_path} - OK {$response_code} ({$elapsed_ms}ms){$body_log}", 'debug' );

            return $response_body;
        }
        return new WP_Error( 'request_failed', 'La petición falló después de varios intentos.' );
    }

    private static function sleep_retry($attempt) {
        if ( ! is_admin() && ! ( defined( 'DOING_CRON' ) && DOING_CRON ) ) {
            return;
        }

        if ( $attempt > 1 ) {
            $sleep = min( 3 * pow(2, $attempt - 2), 10 );
            sleep($sleep);
        }
    }

    /**
     * Extrae el mensaje de error de una respuesta de API
     *
     * @param string $response_body Cuerpo de la respuesta
     * @return string Mensaje de error legible
     */
    private static function extract_api_error_message( $response_body ) {
        if ( empty( $response_body ) ) {
            return 'Sin respuesta del servidor';
        }

        $decoded = json_decode( $response_body, true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return $response_body;
        }

        if ( isset( $decoded['message'] ) && is_string( $decoded['message'] ) ) {
            return $decoded['message'];
        }
        if ( isset( $decoded['error']['message'] ) && is_string( $decoded['error']['message'] ) ) {
            return $decoded['error']['message'];
        }
        if ( isset( $decoded['response']['message'] ) && is_string( $decoded['response']['message'] ) ) {
            return $decoded['response']['message'];
        }
        if ( isset( $decoded['errors'] ) && is_array( $decoded['errors'] ) ) {
            return self::format_errors_array( $decoded['errors'] );
        }
        if ( isset( $decoded['title'] ) && is_string( $decoded['title'] ) ) {
            return $decoded['title'];
        }

        return 'Error desconocido';
    }

    private static function format_errors_array( $errors ) {
        $messages = array();

        foreach ( $errors as $key => $value ) {
            if ( is_array( $value ) ) {
                if ( isset( $value['message'] ) && is_string( $value['message'] ) ) {
                    $messages[] = $value['message'];
                    continue;
                }
                $flat = array();
                foreach ( $value as $msg ) {
                    if ( is_string( $msg ) ) {
                        $flat[] = $msg;
                    }
                }
                if ( ! empty( $flat ) ) {
                    $messages[] = is_string( $key ) ? "{$key}: " . implode( ', ', $flat ) : implode( ', ', $flat );
                }
                continue;
            }
            if ( is_string( $value ) ) {
                $messages[] = is_string( $key ) ? "{$key}: {$value}" : $value;
            }
        }

        return empty( $messages ) ? 'Error desconocido' : implode( ' | ', $messages );
    }

    private static function is_debug_mode_enabled() {
        if ( ! function_exists( 'WC' ) ) {
            return false;
        }

        if ( ! class_exists( 'Andreani_Settings_Service' ) ) {
            return false;
        }

        return Andreani_Settings_Service::is_debug_enabled();
    }

    /**
     * Función para logging usando WooCommerce Logger
     *
     * @param string $message Mensaje a loggear
     * @param string $type Nivel del log (info, error, debug, warning)
     */
    public static function andreani_log( $message, $type = 'debug' ) {
        // No condicionar 'error' al Modo Debug: no se puede guardar mientras la credencial
        // no valide (validate_settings aborta el guardado) y el comercio queda sin registros.
        if ( 'error' !== $type && ! self::is_debug_mode_enabled() ) {
            return;
        }

        if ( ! function_exists( 'wc_get_logger' ) ) {
            return;
        }

        $logger = wc_get_logger();
        $context = array( 'source' => 'andreani-shipping' );

        switch ( $type ) {
            case 'error':
                $logger->error( $message, $context );
                break;
            case 'warning':
                $logger->warning( $message, $context );
                break;
            case 'info':
                $logger->info( $message, $context );
                break;
            case 'debug':
            default:
                $logger->debug( $message, $context );
                break;
        }
    }

    public static function show_error_message( $message, $is_dismissible = true ) {
        self::show_admin_notice( $message, 'error', $is_dismissible );
    }

    public static function show_info_message( $message, $is_dismissible = true ) {
        self::show_admin_notice( $message, 'info', $is_dismissible );
    }

    public static function show_warning_message( $message, $is_dismissible = true ) {
        self::show_admin_notice( $message, 'warning', $is_dismissible );
    }

    public static function show_success_message( $message, $is_dismissible = true ) {
        self::show_admin_notice( $message, 'success', $is_dismissible );
    }

    /**
     * Obtener datos de sesión usando WooCommerce session (más eficiente)
     *
     * @param string $key Clave de sesión a obtener
     * @param mixed  $default Valor por defecto si no existe
     * @return mixed Valor de sesión o default
     */
    public static function get_session_data( $key, $default = null ) {
        $key = sanitize_key( 'andreani_' . $key );

        if ( function_exists( 'WC' ) && WC()->session ) {
            $session_value = WC()->session->get( $key, $default );
        } else {
            if ( ! session_id() && ! headers_sent() ) {
                session_start();
            }
            $session_value = isset( $_SESSION[$key] ) ? $_SESSION[$key] : $default;
        }

        if ( is_null( $session_value ) ) {
            return $default;
        }

        if ( is_string( $session_value ) ) {
            return sanitize_text_field( $session_value );
        }

        if ( is_array( $session_value ) ) {
            return array_map( 'sanitize_text_field', $session_value );
        }

        if ( is_numeric( $session_value ) ) {
            return absint( $session_value );
        }

        if ( is_bool( $session_value ) ) {
            return (bool) $session_value;
        }

        return $default;
    }

    /**
     * Establecer datos en sesión usando WooCommerce session
     *
     * @param string $key Clave de sesión
     * @param mixed  $value Valor a guardar
     * @return bool True si se guardó correctamente
     */
    public static function set_session_data( $key, $value ) {
        $key = sanitize_key( 'andreani_' . $key );

        if ( is_object( $value ) ) {
            return false;
        }

        if ( is_string( $value ) ) {
            $value = sanitize_text_field( $value );
        } elseif ( is_array( $value ) ) {
            $value = array_map( 'sanitize_text_field', $value );
        } elseif ( is_numeric( $value ) ) {
            $value = absint( $value );
        } elseif ( ! is_bool( $value ) && ! is_null( $value ) ) {
            return false;
        }

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( $key, $value );
        } else {
            if ( ! session_id() && ! headers_sent() ) {
                session_start();
            }
            $_SESSION[$key] = $value;
        }

        return true;
    }

    public static function unset_session_data( $key ) {
        $key = sanitize_key( 'andreani_' . $key );

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set( $key, null );
            return true;
        }

        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        if ( ! isset( $_SESSION[$key] ) ) {
            return false;
        }

        unset( $_SESSION[$key] );
        return true;
    }

    public static function show_woocommerce_missing_notice() {
        self::show_error_message(
            __('El plugin Andreani Shipping requiere WooCommerce para funcionar.', 'andreani-shipping'),
            false
        );
    }

    public static function show_andreani_admin_notice() {
        $notice = self::get_session_data('andreani_notice');

        if ( ! empty( $notice ) ) {
            $notice = sanitize_text_field( $notice );
            self::show_error_message( $notice );
        }
    }

    /**
     * Mostrar mensaje de admin
     *
     * @param string $message Mensaje a mostrar
     * @param string $type Tipo de mensaje (error, info, warning, success)
     * @param bool   $is_dismissible Si el mensaje se puede cerrar
     */
    private static function show_admin_notice( $message, $type = 'info', $is_dismissible = true ) {
        $dismissible_class = $is_dismissible ? 'is-dismissible' : '';
        $notice_class = "notice notice-{$type} {$dismissible_class}";

        ?>
        <div class="<?php echo esc_attr( $notice_class ); ?>">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
        <?php
    }

    /**
     * Detecta el tipo de cliente desde la credencial ID (hash)
     * La credencial decodificada tiene formato: TipoCliente|hash_real
     * Ejemplo: Pyme|08h5R0YKMu1ImFf8GbNJJgGfW2uxAanKjuqGpHbR9ng=
     *
     * @param string $hash Credencial ID en base64
     * @return string|null 'pyme', 'corporativo' o null si no se puede detectar
     */
    public static function detect_client_type_from_hash( $hash ) {
        if ( empty( $hash ) ) {
            return null;
        }

        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Necesario para decodificar credencial de Andreani
        $decoded = base64_decode( $hash, true );
        if ( false === $decoded ) {
            return null;
        }

        $pipe_pos = strpos( $decoded, '|' );
        if ( false === $pipe_pos ) {
            return null;
        }

        $type_raw = substr( $decoded, 0, $pipe_pos );
        $type_lower = strtolower( trim( $type_raw ) );

        if ( 'pyme' === $type_lower ) {
            return 'pyme';
        }

        if ( 'corpo' === $type_lower || 'corporativo' === $type_lower ) {
            return 'corporativo';
        }

        return null;
    }

    /**
     * El servicio API normaliza el tipo en `clientType` (snake_case).
     * El hash no permite distinguir Pyme de Middle Market — se resuelve con el response.
     *
     * @return string|null Tipo conocido ('pyme'|'middle_market'|'corporativo') o null si no se puede determinar.
     */
    public static function detect_client_type_from_login_response( $login_response ) {
        if ( ! is_array( $login_response ) || empty( $login_response['clientType'] ) ) {
            return null;
        }
        $type = strtolower( trim( (string) $login_response['clientType'] ) );
        $known_types = array( 'pyme', 'middle_market', 'corporativo' );
        return in_array( $type, $known_types, true ) ? $type : null;
    }

    public static function extract_name_parts($full_name) {
        if (empty($full_name)) {
            return array('firstName' => '', 'lastName' => '-');
        }

        $name_parts = explode(' ', trim($full_name));
        
        if (count($name_parts) === 1) {
            return array(
                'firstName' => $name_parts[0],
                'lastName' => '-'
            );
        }

        $last_name = array_pop($name_parts);
        $first_name = implode(' ', $name_parts);

        return array(
            'firstName' => $first_name,
            'lastName' => $last_name
        );
    }
}