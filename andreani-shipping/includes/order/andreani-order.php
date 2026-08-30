<?php

/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/order/andreani-tracking-display.php';

class Andreani_Order {

    private static $instance = null;

    private function __construct() {
        $this->init_hooks();
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function init_hooks() {
        add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_dni_in_admin' ), 10, 1 );

        add_action( 'woocommerce_thankyou', array( $this, 'send_order_to_andreani' ) );

        add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'display_sucursal_info' ) );

        add_action( 'woocommerce_email_order_meta', array( $this, 'display_sucursal_in_email' ), 20, 3 );

        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_front_assets' ) );
    }

    public function send_order_to_andreani( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        if ( $order->get_meta( '_order_andreani_created', true ) ) {
            return;
        }

        $response = Andreani_Api_Manager::create_orden( $order_id );
        if ( is_wp_error( $response ) ) {
            Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Error al crear envio: " . $response->get_error_message(), 'error' );
            $order->update_meta_data( '_andreani_last_error', $response->get_error_message() );
            $order->save();
            return;
        }
    }

    public function display_dni_in_admin( $order ) {
        $dni = $order->get_meta( '_billing_dni', true );
        if ( $dni ) {
            echo '<p><strong>' . esc_html__( 'DNI', 'andreani-shipping' ) . ':</strong> ' . esc_html( $dni ) . '</p>';
        }
    }

    /**
     * @param WC_Order $order
     * @return array|null Datos del envío o null si no hay info Andreani
     */
    private function get_shipping_data( $order ) {
        $type_info          = Andreani_Client_Type::from_order( $order );
        $order_created      = (bool) $order->get_meta( '_order_andreani_created', true );
        $sucursal_nombre    = $order->get_meta( '_andreani_sucursal_nombre', true );
        $sucursal_direccion = $order->get_meta( '_andreani_sucursal_direccion', true );
        $tracking_number    = $order->get_meta( '_order_andreani_tracking_number', true );
        $pedido_id          = $order->get_meta( '_order_andreani_pedido_id', true );
        $has_sucursal       = ! empty( $sucursal_nombre );

        if ( ! $type_info ) {
            return $has_sucursal ? $this->build_minimal_sucursal_data( $sucursal_nombre, $sucursal_direccion ) : null;
        }

        $type_shipped = (bool) $order->get_meta( $type_info->shipped_meta_key(), true );
        $manual_tracking = $type_info->manual_tracking_meta_key()
            ? $order->get_meta( $type_info->manual_tracking_meta_key(), true )
            : '';

        if ( ! $has_sucursal && ! $type_info->is_visible_for_customer( $order_created, $tracking_number ) ) {
            return null;
        }

        $uses_manual_tracking = $type_info->can( 'manual_tracking' );
        $uses_label_pdf       = $type_info->can( 'label_pdf' );

        $shows_orden_id_block = $uses_manual_tracking && $order_created;
        $shows_tracking_block = ! empty( $tracking_number );

        $final_tracking     = '';
        $final_tracking_url = '';

        if ( $shows_tracking_block ) {
            $final_tracking     = $tracking_number;
            $final_tracking_url = 'https://www.andreani.com/envio/' . $tracking_number;
        }

        $orden_id = '';
        if ( $shows_orden_id_block ) {
            $orden_id = (string) $order->get_meta( '_order_andreani_numero_interno', true );
            if ( '' === $orden_id && ! empty( $pedido_id ) ) {
                $orden_id = '#' . $order->get_order_number() . '-' . $pedido_id;
            }
        }

        return array(
            'title'              => __( 'Envío Andreani', 'andreani-shipping' ),
            'is_pyme'            => $shows_orden_id_block,
            'is_pyme_shipped'    => $shows_orden_id_block && $type_shipped,
            'is_corpo'           => $shows_tracking_block,
            'has_sucursal'       => $has_sucursal,
            'sucursal_nombre'    => $sucursal_nombre,
            'sucursal_direccion' => $sucursal_direccion,
            'tracking_number'    => $final_tracking,
            'tracking_url'       => $final_tracking_url,
            'orden_id'           => $orden_id,
        );
    }

    private function build_minimal_sucursal_data( $nombre, $direccion ) {
        return array(
            'title'              => __( 'Envío Andreani', 'andreani-shipping' ),
            'is_pyme'            => false,
            'is_pyme_shipped'    => false,
            'is_corpo'           => false,
            'has_sucursal'       => true,
            'sucursal_nombre'    => $nombre,
            'sucursal_direccion' => $direccion,
            'tracking_number'    => '',
            'tracking_url'       => '',
            'orden_id'           => '',
        );
    }

    public function display_sucursal_info( $order ) {
        $data             = $this->get_shipping_data( $order );
        $display_tracking = $data ? (string) $data['tracking_number'] : '';
        $display_url      = $data ? (string) $data['tracking_url'] : '';
        $awaiting         = '' === $display_tracking && (bool) $order->get_meta( '_order_andreani_created', true );

        if ( ! $data && ! $awaiting ) {
            return;
        }

        if ( $awaiting ) {
            $this->enqueue_live_tracking( $order );
        }

        $title = $data ? $data['title'] : __( 'Envío Andreani', 'andreani-shipping' );

        echo '<section class="woocommerce-shipping-details andreani-shipping-details">';
        echo '<h2 class="woocommerce-column__title">' . esc_html( $title ) . '</h2>';

        echo '<dl class="andreani-details-list">';

        if ( $data && $data['has_sucursal'] ) {
            echo '<div class="andreani-details-row">';
            echo '<dt>' . esc_html__( 'Punto de retiro', 'andreani-shipping' ) . '</dt>';
            echo '<dd>';
            echo '<strong>' . esc_html( $data['sucursal_nombre'] ) . '</strong>';
            if ( ! empty( $data['sucursal_direccion'] ) ) {
                echo '<br><span class="andreani-address">' . esc_html( $data['sucursal_direccion'] ) . '</span>';
            }
            echo '</dd>';
            echo '</div>';
        }

        if ( $data && ! empty( $data['orden_id'] ) ) {
            echo '<div class="andreani-details-row">';
            echo '<dt>' . esc_html__( 'Orden', 'andreani-shipping' ) . '</dt>';
            echo '<dd><code>' . esc_html( $data['orden_id'] ) . '</code></dd>';
            echo '</div>';
        }

        if ( '' !== $display_tracking ) {
            echo '<div class="andreani-details-row andreani-live-tracking is-ready">';
            $this->render_tracking_row( $display_tracking, $display_url );
            echo '</div>';
        } elseif ( $awaiting ) {
            echo '<div class="andreani-details-row andreani-live-tracking is-pending">';
            echo '<dt>' . esc_html__( 'N° de seguimiento', 'andreani-shipping' ) . '</dt>';
            echo '<dd><span class="andreani-pending">' . esc_html__( 'Generando tu número de seguimiento…', 'andreani-shipping' ) . '</span></dd>';
            echo '</div>';
        }

        echo '</dl>';
        echo '</section>';
    }

    private function render_tracking_row( $tracking, $url = '' ) {
        if ( '' === $url ) {
            $url = 'https://www.andreani.com/envio/' . rawurlencode( $tracking );
        }
        echo '<dt>' . esc_html__( 'N° de seguimiento', 'andreani-shipping' ) . '</dt>';
        echo '<dd>';
        echo '<code>' . esc_html( $tracking ) . '</code>';
        echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="andreani-track-link" title="' . esc_attr__( 'Seguir envío', 'andreani-shipping' ) . '">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="10" r="3"/><path d="M12 2a8 8 0 0 0-8 8c0 1.892.402 3.13 1.5 4.5L12 22l6.5-7.5c1.098-1.37 1.5-2.608 1.5-4.5a8 8 0 0 0-8-8z"/></svg>';
        echo '</a>';
        echo '</dd>';
    }

    private function enqueue_live_tracking( $order ) {
        $handle = 'andreani-tracking-live';
        if ( wp_script_is( $handle, 'enqueued' ) ) {
            return;
        }

        wp_enqueue_script( 'heartbeat' );
        wp_enqueue_script(
            $handle,
            ANDREANI_PLUGIN_URL . 'includes/assets/js/tracking-live.js',
            array( 'jquery', 'heartbeat' ),
            ANDREANI_PLUGIN_VERSION,
            true
        );
        wp_localize_script( $handle, 'andreaniTracking', array(
            'orderId'    => $order->get_id(),
            'orderKey'   => $order->get_order_key(),
            'labelReady' => __( 'N° de seguimiento', 'andreani-shipping' ),
            'trackText'  => __( 'Seguir envío', 'andreani-shipping' ),
        ) );
    }

    public function display_sucursal_in_email( $order, $sent_to_admin, $plain_text ) {
        $data = $this->get_shipping_data( $order );
        if ( ! $data ) {
            return;
        }

        if ( $plain_text ) {
            $this->render_email_plain_text( $data );
        } else {
            $this->render_email_html( $data );
        }
    }

    private function render_email_plain_text( $data ) {
        echo "\n\n";
        echo "==========\n";
        echo esc_html( mb_strtoupper( $data['title'] ) ) . "\n";
        echo "==========\n\n";

        if ( $data['has_sucursal'] ) {
            echo esc_html__( 'Punto de retiro:', 'andreani-shipping' ) . ' ' . esc_html( $data['sucursal_nombre'] ) . "\n";
            if ( ! empty( $data['sucursal_direccion'] ) ) {
                echo esc_html( $data['sucursal_direccion'] ) . "\n";
            }
            echo "\n";
        }

        if ( ! empty( $data['orden_id'] ) ) {
            echo esc_html__( 'Orden:', 'andreani-shipping' ) . ' ' . esc_html( $data['orden_id'] ) . "\n\n";
        }

        if ( ! empty( $data['tracking_number'] ) ) {
            echo esc_html__( 'N° de seguimiento:', 'andreani-shipping' ) . ' ' . esc_html( $data['tracking_number'] ) . "\n";
            echo esc_html__( 'Seguí tu envío en:', 'andreani-shipping' ) . ' ' . esc_url( $data['tracking_url'] ) . "\n";
        }
    }

    private function render_email_html( $data ) {
        echo '<div style="margin: 20px 0; padding: 15px; background-color: #f8f8f8; border-radius: 4px;">';
        echo '<h3 style="margin: 0 0 10px;">' . esc_html( $data['title'] ) . '</h3>';

        if ( $data['has_sucursal'] ) {
            echo '<p style="margin: 0 0 8px;">';
            echo esc_html__( 'Punto de retiro:', 'andreani-shipping' ) . ' ';
            echo '<strong>' . esc_html( $data['sucursal_nombre'] ) . '</strong>';
            if ( ! empty( $data['sucursal_direccion'] ) ) {
                echo '<br>' . esc_html( $data['sucursal_direccion'] );
            }
            echo '</p>';
        }

        if ( ! empty( $data['orden_id'] ) ) {
            echo '<p style="margin: 0 0 8px;">';
            echo esc_html__( 'Orden:', 'andreani-shipping' ) . ' ';
            echo '<code style="font-family: monospace;">' . esc_html( $data['orden_id'] ) . '</code>';
            echo '</p>';
        }

        if ( ! empty( $data['tracking_number'] ) ) {
            echo '<p style="margin: 0;">';
            echo esc_html__( 'N° de seguimiento:', 'andreani-shipping' ) . ' ';
            echo '<a href="' . esc_url( $data['tracking_url'] ) . '">' . esc_html( $data['tracking_number'] ) . '</a>';
            echo '</p>';
        }

        echo '</div>';
    }

    public function enqueue_front_assets() {
        $should_enqueue = false;
        
        if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
            $should_enqueue = true;
        }

        if ( function_exists( 'is_wc_endpoint_url' ) && is_account_page() && is_wc_endpoint_url( 'view-order' ) ) {
            $should_enqueue = true;
        }

        if ( function_exists( 'is_woocommerce' ) && is_woocommerce() ) {
            global $post;
            if ( $post && get_post_type( $post->ID ) === 'shop_order' ) {
                $should_enqueue = true;
            }
        }

        if ( ! $should_enqueue ) {
            $current_url = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
            if ( strpos( $current_url, 'view-order' ) !== false ||
                strpos( $current_url, 'order-received' ) !== false ||
                strpos( $current_url, 'checkout/order-received' ) !== false ) {
                $should_enqueue = true;
            }
        }

        if ( $should_enqueue ) {
            $this->enqueue_front_styles();
        }
    }

    private function enqueue_front_styles() {
        $handle = 'andreani-order';

        if ( ! wp_style_is( $handle, 'enqueued' ) ) {
            wp_enqueue_style(
                $handle,
                ANDREANI_PLUGIN_URL . 'includes/assets/css/views/frontend-order.css',
                array( Andreani_Core_Assets::HANDLE_BASE ),
                ANDREANI_PLUGIN_VERSION
            );
        }
    }
}
