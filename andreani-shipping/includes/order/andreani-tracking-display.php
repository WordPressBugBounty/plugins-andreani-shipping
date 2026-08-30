<?php

/**
 * Clase para manejar los displays de tracking de Andreani
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ANDREANI_PYME_HISTORIAL_URL' ) ) {
    define( 'ANDREANI_PYME_HISTORIAL_URL', 'https://pymes.andreani.com/ver-envios?tab=integraciones' );
}
if ( ! defined( 'ANDREANI_PYME_HACER_ENVIO_URL' ) ) {
    define( 'ANDREANI_PYME_HACER_ENVIO_URL', 'https://pymes.andreani.com/hacer-envio/' );
}
if ( ! defined( 'ANDREANI_CORPO_HISTORIAL_URL' ) ) {
    define( 'ANDREANI_CORPO_HISTORIAL_URL', 'https://corporativo.andreani.com/historial' );
}

class Andreani_Tracking_Display {

    public static function render_display( $order_id, $client_type = null, $context = 'admin' ) {
        $order = wc_get_order( $order_id );

        if ( is_null( $client_type ) && $order ) {
            $client_type = $order->get_meta( '_order_andreani_client_type', true );
        }

        if ( empty( $client_type ) ) {
            $client_type = Andreani_Api_Manager::get_client_type();
        }

        $type_info = Andreani_Client_Type::from_id( $client_type );
        $state     = self::normalize_order_state( $order, $type_info );

        $html = "<div class='woocommerce-column woocommerce-column--3 woocommerce-column--andreani col-3 andreani-wrapper'>";

        if ( $state['created'] && ! empty( $state['id'] ) ) {
            $config = self::build_success_config( $state, $type_info, $context );

            if ( 'admin' === $context && $type_info && $type_info->can( 'label_pdf' ) ) {
                $config['button_action'] = 'download_label';
                unset( $config['button_url'] );
                $config['ajax_action'] = 'andreani_get_etiqueta';
                $config['ajax_url'] = admin_url( 'admin-ajax.php' );
                $config['nonce'] = wp_create_nonce( 'andreani_get_etiqueta' );
                $config['download_label_order_id'] = $order_id;
            }

            $html .= self::render_tracking_card( $config );
        } else {
            if ( 'admin' === $context ) {
                $config = self::build_error_config( $state, $type_info, $order_id );
                $html .= self::render_tracking_card( $config );
            }
        }

        $html .= "</div>";

        return $html;
    }

    private static function normalize_order_state( $order, $type_info ) {
        $empty_state = array(
            'created'       => false,
            'id'            => '',
            'id_label'      => '',
            'url'           => '',
            'url_text'      => '',
            'retry_enabled' => false,
        );

        if ( ! $order || ! $type_info ) {
            return $empty_state;
        }

        $created       = (bool) $order->get_meta( '_order_andreani_created', true );
        $retry_enabled = ! $order->get_meta( '_andreani_retry', true );

        if ( $type_info->can( 'id_orden_display' ) ) {
            $numero_interno = (string) $order->get_meta( '_order_andreani_numero_interno', true );
            if ( '' === $numero_interno ) {
                $pedido_id = (string) $order->get_meta( $type_info->tracking_meta_key(), true );
                $numero_interno = ! empty( $pedido_id ) ? '#' . $order->get_order_number() . '-' . $pedido_id : '';
            }
            $id = $numero_interno;
        } else {
            $id = (string) $order->get_meta( $type_info->tracking_meta_key(), true );
        }

        return array(
            'created'       => $created,
            'id'            => $id,
            'id_label'      => $type_info->display( 'tracking_id_label' ),
            'url'           => $type_info->display( 'historial_url' ),
            'url_text'      => $type_info->display( 'customer_action_url_text' ),
            'retry_enabled' => $retry_enabled,
        );
    }

    private static function build_success_config( $state, $type_info, $context = 'admin' ) {
        $config = array(
            'type' => 'success',
            'title' => '¡Tu envío fue generado exitosamente!',
            'order_id' => $state['id'],
            'order_id_label' => $state['id_label'],
        );

        if ( 'admin' === $context ) {
            $config['description'] = 'Podés gestionar o hacer seguimiento de tu envío desde:';
            $config['button_text'] = $state['url_text'];
            $config['button_url'] = $state['url'];

            if ( $type_info && $type_info->can( 'label_pdf' ) && ! empty( $state['id'] ) ) {
                unset( $config['description'] );
                $config['secondary_button_url'] = $type_info->display( 'historial_url' );
                $config['secondary_button_text'] = 'Gestionar mis envíos';
            }
        } else {
            $tracking_number = isset( $state['id'] ) ? (string) $state['id'] : '';
            $tracking_url = 'https://www.andreani.com/envio/' . rawurlencode( $tracking_number );

            $link = '<a href="' . esc_url( $tracking_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( 'Andreani.com' ) . '</a>';
            $config['description'] = 'Podés hacer seguimiento de tu envío en ' . $link;
        }

        return $config;
    }

    private static function build_error_config( $state, $type_info, $order_id ) {
        $config = array(
            'type' => 'warning',
            'title' => 'Hubo un error al procesar el envío',
            'description' => 'Podés reintentar el alta del envío. Si el problema persiste deberas cargarlo manualmente desde la plataforma de Andreani.',
            'button_text' => 'Reintentar',
            'button_action' => 'retry',
            'button_disabled' => ! $state['retry_enabled'],
            'ajax_action' => 'andreani_retry_order',
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'andreani_retry_order' ),
            'retry_order_andreani_id' => $order_id,
        );

        if ( $type_info ) {
            $portal_url   = $type_info->display( 'external_creation_portal_url' );
            $portal_label = $type_info->display( 'external_creation_portal_label' );
            if ( '' !== $portal_url && '' !== $portal_label ) {
                $config['secondary_button_url']  = $portal_url;
                $config['secondary_button_text'] = $portal_label;
            }
        }

        return $config;
    }

    private static function render_tracking_card( $config ) {
        $andreani_logo_url = ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani.png';
        $defaults = array(
            'type'              => 'success',
            'title'             => '',
            'order_id'          => '',
            'order_id_label'    => 'ID Orden',
            'description'       => '',
            'button_text'       => '',
            'button_url'        => '',
            'additional_info'   => '',
            'button_disabled'   => false,
            'secondary_button_url' => '',
            'secondary_button_text' => ''
        );

        $config = array_merge( $defaults, $config );

        $styles = self::get_card_styles( $config['type'] );
        $type_class = 'andreani-card--' . sanitize_html_class( $config['type'] );

        $html = "<div class='andreani-card " . $type_class . "'>";

        $html .= "<div class='andreani-card__logo'>";
        $html .= "<a href='" . esc_url( 'https://www.andreani.com' ) . "' target='_blank' rel='noopener noreferrer' title='" . esc_attr__( 'Ir a Andreani.com', 'andreani-shipping' ) . "'>";
        $html .= "<img src='" . esc_url( $andreani_logo_url ) . "' alt='" . esc_attr__( 'Andreani Logo', 'andreani-shipping' ) . "' />";
        $html .= "</a>";
        $html .= "</div>";

        $html .= "<div class='andreani-card__panel'>";

        $html .= "<div class='andreani-card__header'>";
        $html .= "<div class='andreani-card__icon'>" . $styles['icon'] . "</div>";
        $html .= "<h3 class='andreani-card__title'>" . esc_html( $config['title'] ) . "</h3>";
        $html .= "</div>";

        if ( !empty( $config['order_id'] ) ) {
            $html .= "<div class='andreani-card__order'>";
            $html .= "<p><strong>" . esc_html( $config['order_id_label'] ) . ":</strong> ";

            $label_lower = strtolower( $config['order_id_label'] );
            if ( strpos( $label_lower, 'seguimiento' ) !== false || strpos( $label_lower, 'tracking' ) !== false || strpos( $label_lower, 'nro' ) !== false ) {
                $html .= "<span class='andreani-card__order-code andreani-copy-number' data-copy-text='" . esc_attr( $config['order_id'] ) . "' title='Hacer clic para copiar'>" . esc_html( $config['order_id'] ) . "</span>";
            } else {
                $html .= "<span class='andreani-card__order-code'>" . esc_html( $config['order_id'] ) . "</span>";
            }

            $html .= "</p>";
            $html .= "</div>";
        }

        if ( !empty( $config['description'] ) ) {
            $allowed_tags = array(
                'a' => array(
                    'href' => array(),
                    'target' => array(),
                    'rel' => array(),
                ),
                'strong' => array(),
                'em' => array(),
            );

            $html .= "<p class='andreani-card__desc'>" . wp_kses( $config['description'], $allowed_tags ) . "</p>";
        }

        if ( !empty( $config['additional_info'] ) ) {
            $html .= "<div class='andreani-card__info'>";
            $html .= "<p><strong>Información importante:</strong> " . esc_html( $config['additional_info'] ) . "</p>";
            $html .= "</div>";
        }

        if ( !empty( $config['button_text'] ) && (
            !empty( $config['button_action'] ) ||
            !empty( $config['button_url'] ) ||
            !empty( $config['secondary_button_url'] )
        ) ) {
            $html .= "<div class='andreani-card__actions'>";

            if ( !empty( $config['button_action'] ) && in_array( $config['button_action'], array( 'retry', 'retry_pyme', 'download_label' ), true ) ) {
                if ( 'download_label' === $config['button_action'] ) {
                    $html .= self::render_download_label_button( $config );
                } else {
                    $html .= self::render_retry_button( $config );
                }
            } elseif ( !empty( $config['button_url'] ) ) {
                $html .= self::render_link_button( $config['button_text'], $config['button_url'] );
            }

            if ( !empty( $config['secondary_button_url'] ) && !empty( $config['secondary_button_text'] ) ) {
                $html .= ' ' . self::render_link_button( $config['secondary_button_text'], $config['secondary_button_url'] );
            }

            $html .= "</div>";
        }
        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private static function get_card_styles( $type ) {
        $icons = array(
            'success' => '✓',
            'error'   => '✕',
            'warning' => '!',
            'info'    => 'i',
        );
        $type_key = isset( $icons[$type] ) ? $type : 'success';
        return array( 'icon' => $icons[$type_key] );
    }

    private static function render_retry_button( $config ) {
        $retry_id_raw = isset( $config['retry_order_andreani_id'] ) ? $config['retry_order_andreani_id'] : '';
        if ( empty( $retry_id_raw ) ) {
            return '';
        }

        $btn_id = 'andreani-retry-btn-' . sanitize_html_class( (string) $retry_id_raw );
        $ajax_url = !empty( $config['ajax_url'] ) ? esc_url( $config['ajax_url'] ) : '';
        $nonce = !empty( $config['nonce'] ) ? esc_attr( $config['nonce'] ) : '';
        $ajax_action = !empty( $config['ajax_action'] ) ? esc_attr( $config['ajax_action'] ) : 'andreani_retry_order';
        $order_id = esc_attr( (string) $retry_id_raw );

        $disabled = !empty( $config['button_disabled'] );
        $disabled_attr = $disabled ? 'disabled' : '';

        return "<button type='button' id='" . $btn_id . "' class='button andreani-retry' data-ajax-url='" . $ajax_url . "' data-action='" . $ajax_action . "' data-nonce='" . $nonce . "' data-order-id='" . $order_id . "' $disabled_attr>" . esc_html( $config['button_text'] ) . "</button>";
    }

    private static function render_link_button( $text, $url ) {
        $external_icon = "<svg width='f4' height='14' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'/><polyline points='15,3 21,3 21,9'/><line x1='10' y1='14' x2='21' y2='3'/></svg>";
        
        return "<a href='" . esc_url( $url ) . "' target='_blank' class='button andreani-btn'>" . esc_html( $text ) . " " . $external_icon . "</a>";
    }

    private static function render_download_label_button( $config ) {
        $order_id_raw = isset( $config['download_label_order_id'] ) ? $config['download_label_order_id'] : '';
        if ( empty( $order_id_raw ) ) {
            return '';
        }

        $btn_id = 'andreani-download-label-btn-' . sanitize_html_class( (string) $order_id_raw );
        $ajax_url = !empty( $config['ajax_url'] ) ? esc_url( $config['ajax_url'] ) : '';
        $nonce = !empty( $config['nonce'] ) ? esc_attr( $config['nonce'] ) : '';
        $ajax_action = !empty( $config['ajax_action'] ) ? esc_attr( $config['ajax_action'] ) : 'andreani_get_etiqueta';
        $order_id = esc_attr( (string) $order_id_raw );

        return "<button type='button' id='" . $btn_id . "' class='button andreani-download-label andreani-btn' data-ajax-url='" . $ajax_url . "' data-action='" . $ajax_action . "' data-nonce='" . $nonce . "' data-order-id='" . $order_id . "'>" . esc_html( $config['button_text'] ) . "</button>";
    }
}
