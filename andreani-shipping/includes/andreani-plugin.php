<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Plugin {
    private static $instance = null;

    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init();
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function define_constants() {
    }

    private function includes() {
        $files = array(
            'includes/utils/andreani-utils.php',
            'includes/origen/class-andreani-origen.php',
            'includes/class-andreani-upgrader.php',
            'includes/services/andreani-settings-service.php',
            'includes/services/andreani-cache-service.php',
            'includes/api/andreani-api-manager.php',
            'includes/api/backend/andreani-shipments-api.php',
            'includes/assets/class-andreani-core-assets.php',
            'includes/checkout/andreani-checkout.php',
            'includes/checkout/class-andreani-sucursales-ajax.php',
            'includes/blocks/class-andreani-blocks.php',
            'includes/order/andreani-order.php',
            'includes/order/class-andreani-tracking-poll.php',
            'includes/order/class-andreani-tracking-sync.php',
            'includes/cotizador/class-andreani-cotizador-widget.php',
            'includes/admin/class-andreani-product-bultos.php',
        );

        foreach ( $files as $file ) {
            $file_path = ANDREANI_PLUGIN_DIR . $file;
            if ( file_exists( $file_path ) ) {
                require_once $file_path;
            } else {
                error_log( sprintf( 'Andreani Plugin: No se pudo cargar el archivo %s', $file ) );
            }
        }

        if ( is_admin() ) {
            $admin_files = array(
                'includes/admin/class-andreani-admin-menu.php',
                'includes/admin/class-andreani-shipment-detail-view.php',
                'includes/admin/class-andreani-shipments-list.php',
                'includes/admin/class-andreani-products-list.php',
                'includes/admin/class-andreani-shipments-hydrator.php',
                'includes/admin/class-andreani-order-metabox.php',
                'includes/admin/class-andreani-ajax-handler.php',
                'includes/admin/class-andreani-origen-ajax.php',
                'includes/admin/class-andreani-admin-assets.php',
            );

            foreach ( $admin_files as $file ) {
                $file_path = ANDREANI_PLUGIN_DIR . $file;
                if ( file_exists( $file_path ) ) {
                    require_once $file_path;
                } else {
                    error_log( sprintf( 'Andreani Plugin: No se pudo cargar el archivo de admin %s', $file ) );
                }
            }
        }
    }

    /**
     * Manda a Andreani el origen que dejó marcado el upgrader. Corre una sola vez,
     * fuera del upgrade, para que actualizar el plugin no dispare requests HTTP.
     */
    public function maybe_push_origen() {
        if ( ! class_exists( 'Andreani_Origen' ) ) {
            return;
        }

        if ( 'yes' !== get_option( Andreani_Origen::OPTION_PUSH_PENDIENTE, '' ) ) {
            return;
        }

        if ( wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }

        $lock = 'andreani_origen_push_lock';
        if ( get_transient( $lock ) ) {
            return;
        }
        set_transient( $lock, 1, 15 * MINUTE_IN_SECONDS );

        Andreani_Origen::push_a_andreani();
    }

    public function init() {
        add_action( 'admin_init', array( 'Andreani_Upgrader', 'maybe_upgrade' ) );
        add_action( 'admin_init', array( $this, 'maybe_push_origen' ), 20 );
        Andreani_Upgrader::register_admin_notices();

        // Invalidacion del cache de stats de envios. Hookeado a nivel global porque
        // las meta keys involucradas se tocan desde checkout, order y AJAX admin —
        // centralizar aca evita esparcir delete_transient() por todo el plugin.
        if ( class_exists( 'Andreani_Shipments_List' ) || $this->try_load_shipments_list() ) {
            add_action( 'updated_post_meta', array( 'Andreani_Shipments_List', 'invalidate_stats_cache' ), 10, 4 );
            add_action( 'added_post_meta',   array( 'Andreani_Shipments_List', 'invalidate_stats_cache' ), 10, 4 );
            add_action( 'deleted_post_meta', array( 'Andreani_Shipments_List', 'invalidate_stats_cache' ), 10, 4 );

            // Mismo patrón que stats para el cache legacy del detalle expandido: cualquier
            // mutación de meta key Andreani sobre una orden purga su transient
            // `andreani_detail_{id}` automáticamente, sin parchar cada handler AJAX.
            add_action( 'updated_post_meta', array( 'Andreani_Shipments_List', 'invalidate_detail_cache' ), 10, 4 );
            add_action( 'added_post_meta',   array( 'Andreani_Shipments_List', 'invalidate_detail_cache' ), 10, 4 );
            add_action( 'deleted_post_meta', array( 'Andreani_Shipments_List', 'invalidate_detail_cache' ), 10, 4 );
        }

        if ( isset( $_COOKIE['andreani_notice'] ) && current_user_can( 'manage_options' ) ) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $notice = sanitize_text_field( wp_unslash( $_COOKIE['andreani_notice'] ) );
            $notice = substr( $notice, 0, 500 );

            if ( ! empty( $notice ) ) {
                Andreani_Utils::set_session_data( 'andreani_notice', $notice );
                add_action( 'admin_notices', array( 'Andreani_Utils', 'show_andreani_admin_notice' ) );

                if ( ! headers_sent() ) {
                    setcookie( 'andreani_notice', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );
                }
            }
        }

        if ( ! self::is_woocommerce_active() ) {
            add_action( 'admin_notices', array( 'Andreani_Utils', 'show_woocommerce_missing_notice' ) );
            return;
        }

        add_filter( 'plugin_action_links_' . plugin_basename( ANDREANI_PLUGIN_FILE ), array( $this, 'andreani_plugin_actions_links' ) );
        add_filter( 'woocommerce_shipping_methods', array( $this, 'add_andreani_shipping_method' ) );
        add_action( 'woocommerce_shipping_init', array( $this, 'init_andreani_shipping_method' ) );
        add_action( 'wp_ajax_andreani_refresh_contratos', array( $this, 'ajax_refresh_contratos' ) );

        if ( class_exists( 'Andreani_Core_Assets' ) ) {
            Andreani_Core_Assets::get_instance();
        }

        if ( class_exists( 'Andreani_Blocks' ) ) {
            Andreani_Blocks::get_instance();
        }

        $this->init_frontend_classes();
        $this->init_admin_classes();

        // Sincronizacion de tracking en segundo plano (Action Scheduler). Incondicional:
        // su handler debe estar enganchado en cualquier contexto donde corra la accion.
        if ( class_exists( 'Andreani_Tracking_Sync' ) ) {
            Andreani_Tracking_Sync::get_instance();
        }
    }

    private function init_frontend_classes() {
        $is_frontend = ! is_admin();
        $is_ajax = wp_doing_ajax();

        if ( $is_frontend || $is_ajax ) {
            if ( class_exists( 'Andreani_Checkout' ) ) {
                Andreani_Checkout::get_instance();
            }
            if ( class_exists( 'Andreani_Sucursales_Ajax' ) ) {
                Andreani_Sucursales_Ajax::get_instance();
            }
            if ( class_exists( 'Andreani_Tracking_Poll' ) ) {
                Andreani_Tracking_Poll::get_instance();
            }
        }

        if ( $is_frontend ) {
            if ( class_exists( 'Andreani_Order' ) ) {
                Andreani_Order::get_instance();
            }
        }

        if ( $is_frontend || $is_ajax ) {
            if ( class_exists( 'Andreani_Settings_Service' ) && class_exists( 'Andreani_Cotizador_Widget' ) ) {
                if ( 'yes' === Andreani_Settings_Service::get( 'cotizador_producto', 'no' ) ) {
                    Andreani_Cotizador_Widget::get_instance();
                }
            }
        }
    }

    private function init_admin_classes() {
        if ( ! is_admin() ) {
            return;
        }

        if ( class_exists( 'Andreani_Admin_Menu' ) ) {
            Andreani_Admin_Menu::get_instance();
        }

        if ( class_exists( 'Andreani_Order_Metabox' ) ) {
            Andreani_Order_Metabox::get_instance();
        }

        if ( class_exists( 'Andreani_Ajax_Handler' ) ) {
            Andreani_Ajax_Handler::get_instance();
        }

        if ( class_exists( 'Andreani_Origen_Ajax' ) ) {
            Andreani_Origen_Ajax::get_instance();
        }

        if ( class_exists( 'Andreani_Admin_Assets' ) ) {
            Andreani_Admin_Assets::get_instance();
        }

        if ( class_exists( 'Andreani_Product_Bultos' ) ) {
            Andreani_Product_Bultos::get_instance();
        }
    }

    private static function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Carga la clase Andreani_Shipments_List on-demand fuera de admin.
     * Necesario para que el hook de invalidacion de cache de stats funcione tambien
     * cuando una orden se procesa en frontend (thank-you page) y muta su meta.
     *
     * @return bool true si la clase quedo cargada.
     */
    private function try_load_shipments_list() {
        if ( class_exists( 'Andreani_Shipments_List' ) ) {
            return true;
        }
        $file = ANDREANI_PLUGIN_DIR . 'includes/admin/class-andreani-shipments-list.php';
        if ( file_exists( $file ) ) {
            require_once $file;
            return class_exists( 'Andreani_Shipments_List' );
        }
        return false;
    }

    public function init_andreani_shipping_method() {
        if ( ! class_exists( 'Andreani_Shipping' ) ) {
            $shipping_file = ANDREANI_PLUGIN_DIR . 'includes/shipping/andreani-shipping.php';
            if ( file_exists( $shipping_file ) ) {
                require_once $shipping_file;
            }
        }
    }

    public function add_andreani_shipping_method( $methods ) {
        $this->init_andreani_shipping_method();

        if ( class_exists( 'Andreani_Shipping' ) ) {
            $methods[ANDREANI_SHIPPING_METHOD_ID] = 'Andreani_Shipping';
        }

        return $methods;
    }

    public static function activation_check() {
        if ( ! self::is_woocommerce_active() ) {
            self::deactivate_with_message( 'El plugin Grupo Logístico Andreani requiere WooCommerce para funcionar.' );
        }

        $country_raw  = get_option( 'woocommerce_default_country', '' );
        $base_country = is_string( $country_raw ) ? strtoupper( $country_raw ) : '';
        if ( strpos( $base_country, ':' ) !== false ) {
            $base_country = substr( $base_country, 0, strpos( $base_country, ':' ) );
        }
        if ( 'AR' !== $base_country ) {
            self::deactivate_with_message( 'Este plugin solo puede activarse para tiendas con país base Argentina (AR). Vaya a WooCommerce > Ajustes > General y configure Ubicación de la tienda en Argentina.' );
        }

        $conflicting_plugins = array(
            'wanderlust-andreani-shipping/wanderlust-andreani-shipping.php',
        );
        foreach ( $conflicting_plugins as $plugin ) {
            if ( in_array( $plugin, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
                $plugin_name = strpos( $plugin, '/' ) !== false ? substr( $plugin, 0, strpos( $plugin, '/' ) ) : $plugin;
                self::deactivate_with_message( 'El plugin Andreani Shipping no se puede activar debido a conflicto con otro plugin de Andreani. Por favor desactive ' . $plugin_name . ' primero.' );
            }
        }
    }

    private static function deactivate_with_message( $message ) {
        deactivate_plugins( plugin_basename( ANDREANI_PLUGIN_FILE ) );
        wp_die(
            esc_html( $message ),
            'Error de activación',
            array( 'back_link' => true )
        );
    }

    public function andreani_plugin_actions_links( $links ) {
        $plugin_links = array(
            '<a href="http://www.andreani.com">' . __( 'Soporte', 'andreani-shipping' ) . '</a>',
        );
        return array_merge( $plugin_links, $links );
    }

    public function ajax_refresh_contratos() {
        check_ajax_referer( 'andreani_refresh_contratos', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error(
                array( 'message' => __( 'No tienes permisos para realizar esta acción.', 'andreani-shipping' ) ),
                403
            );
        }

        $lock_key = 'andreani_refresh_contratos_lock';
        if ( get_transient( $lock_key ) ) {
            wp_send_json_error(
                array( 'message' => __( 'Por favor esperá un momento antes de volver a actualizar.', 'andreani-shipping' ) ),
                429
            );
        }
        set_transient( $lock_key, 1, 60 );

        $hash_andreani = Andreani_Settings_Service::get( 'hash_andreani', '' );
        $tipo_cliente  = Andreani_Settings_Service::get_client_type();

        if ( empty( $hash_andreani ) || 'corporativo' !== $tipo_cliente ) {
            wp_send_json_error( 
                array( 'message' => __( 'Configuración no válida para refrescar contratos.', 'andreani-shipping' ) ),
                400
            );
        }

        if ( ! class_exists( 'Andreani_Corpo_Api' ) ) {
            require_once ANDREANI_PLUGIN_DIR . 'includes/api/corpo/andreani-corpo.php';
        }
        $corpo_api = Andreani_Corpo_Api::get_instance();
        $result = $corpo_api->validate_hash( $hash_andreani );

        if ( ! $result ) {
            wp_send_json_error( 
                array( 'message' => __( 'Error al conectar con Andreani. Verifica tu credencial.', 'andreani-shipping' ) ),
                502
            );
        }

        Andreani_Api_Manager::clear_settings_cache();

        wp_send_json_success( array( 'message' => __( 'Contratos actualizados correctamente.', 'andreani-shipping' ) ) );
    }

}
