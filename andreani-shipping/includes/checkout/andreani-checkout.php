<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Checkout {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'add_checkout_scripts' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_defer_attribute' ), 10, 2 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'update_andreani_order_meta' ), 10, 1 );
        add_action( 'woocommerce_checkout_create_order_shipping_item', array( $this, 'persist_andreani_rate_id' ), 10, 4 );
        add_action( 'woocommerce_checkout_init', array( $this, 'update_cp_from_session' ) );
        add_action( 'woocommerce_after_calculate_totals', array( $this, 'process_after_shipping_calculator' ), 10 );
        add_action( 'woocommerce_checkout_update_order_review', array( $this, 'capture_postcode_from_checkout_update' ), 5 );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_sucursal_field' ) );
        add_action( 'woocommerce_cart_calculate_fees', array( $this, 'agregar_costo_adicional' ) );

        // En modo automático (default) los hooks de WooCommerce inyectan el selector de
        // sucursales y los campos de DNI directamente en el checkout clásico.
        $checkout_modo = Andreani_Settings_Service::get( 'checkout_modo', 'auto' );
        if ( 'auto' === $checkout_modo ) {
            add_action( 'woocommerce_review_order_after_shipping', array( $this, 'get_andreani_sucursales' ), 10 );
            add_filter( 'woocommerce_checkout_fields', array( $this, 'update_checkout_fields' ) );
        }

        // Shortcodes siempre disponibles para modo manual (Elementor / page builders).
        add_shortcode( 'andreani_sucursales', array( $this, 'render_sucursales_shortcode' ) );
        add_shortcode( 'andreani_dni_field', array( $this, 'render_dni_field_shortcode' ) );
    }

    /**
     * Devuelve los args base del campo DNI para un contexto dado (billing / shipping).
     *
     * @param string $context 'billing' o 'shipping'.
     * @param array  $args    Argumentos adicionales para sobrescribir los defaults.
     * @return array
     */
    public function render_dni_field_markup( $context, $args = array() ) {
        $defaults = array(
            'label'       => __( 'DNI', 'andreani-shipping' ),
            'placeholder' => _x( 'DNI', 'placeholder', 'andreani-shipping' ),
            'required'    => true,
            'class'       => array( 'form-row-wide' ),
            'clear'       => true,
            'priority'    => 25,
            'type'        => 'text',
        );

        return wp_parse_args( $args, $defaults );
    }

    public function update_checkout_fields( $fields ) {
        $dni_field_names = array( 'billing_dni', 'billing_vat', 'billing_cedula', 'billing_document', 'billing_cuit' );

        $billing_dni_exists = false;
        foreach ( $dni_field_names as $field_name ) {
            if ( isset( $fields['billing'][ $field_name ] ) ) {
                $billing_dni_exists = true;
                $fields['billing'][ $field_name ]['required'] = true;
                break;
            }
        }

        if ( ! $billing_dni_exists ) {
            $fields['billing']['billing_dni'] = $this->render_dni_field_markup( 'billing' );
        }

        $shipping_dni_field_names = array( 'shipping_dni', 'shipping_vat', 'shipping_cedula', 'shipping_document', 'shipping_cuit' );
        $shipping_dni_exists      = false;
        foreach ( $shipping_dni_field_names as $field_name ) {
            if ( isset( $fields['shipping'][ $field_name ] ) ) {
                $shipping_dni_exists = true;
                $fields['shipping'][ $field_name ]['required'] = true;
                break;
            }
        }

        if ( ! $shipping_dni_exists ) {
            $fields['shipping']['shipping_dni'] = $this->render_dni_field_markup( 'shipping' );
        }

        $fields['shipping']['shipping_phone'] = array(
            'label'    => __( 'Teléfono', 'andreani-shipping' ),
            'required' => true,
            'class'    => array( 'form-row-wide' ),
            'clear'    => true,
            'priority' => 110,
            'type'     => 'tel',
        );

        if ( isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['required'] = true;
        }

        if ( isset( $fields['shipping']['shipping_phone'] ) ) {
            $fields['shipping']['shipping_phone']['required'] = true;
        }

        return $fields;
    }

    public function update_cp_from_session() {
        $cp_destino = Andreani_Utils::get_session_data('cp_destino', '');
        if (!empty($cp_destino)) {
            $current_billing_postcode = WC()->customer->get_billing_postcode();
            if (empty($current_billing_postcode) || $current_billing_postcode !== $cp_destino) {
                WC()->customer->set_billing_postcode($cp_destino);
            }
            $current_shipping_postcode = WC()->customer->get_shipping_postcode();
            if (empty($current_shipping_postcode) || $current_shipping_postcode !== $cp_destino) {
                WC()->customer->set_shipping_postcode($cp_destino);
            }
            WC()->customer->save();
        }
    }

    private function is_andreani_sucursal_method( $method ) {
        $method_lower = strtolower( $method );
        return strpos( $method_lower, 'andreani' ) !== false
            && strpos( $method_lower, 'sucursal' ) !== false;
    }

    /**
     * Genera el markup HTML del selector de sucursales y los campos ocultos asociados.
     *
     * La primera instancia (instance_id = 0) usa los IDs legacy para mantener
     * compatibilidad con el JS existente. Las instancias subsiguientes agregan sufijo.
     *
     * @param int $instance_id Número de instancia; 0 = primera (checkout clásico).
     * @return string HTML del bloque de sucursales.
     */
    private function build_sucursales_markup( $instance_id = 0 ) {
        $cp_destino = $this->get_current_postcode();

        $suffix        = ( 0 === $instance_id ) ? '' : '_' . $instance_id;
        $select_id     = 'sucursales_andreani' . $suffix;
        $nombre_id     = 'sucursal_nombre' . $suffix;
        $direccion_id  = 'sucursal_direccion' . $suffix;
        $details_id    = 'andreani-sucursal-details' . $suffix;

        $option_label = empty( $cp_destino )
            ? esc_html__( 'Ingrese un código postal para ver las sucursales disponibles', 'andreani-shipping' )
            : esc_html__( 'Seleccione una sucursal', 'andreani-shipping' );

        $html  = '<select name="' . esc_attr( $select_id ) . '" id="' . esc_attr( $select_id ) . '" class="select andreani-sucursales-select" required>';
        $html .= '<option value="0">' . $option_label . '</option>';
        $html .= '</select>';
        $html .= '<input type="hidden" name="' . esc_attr( $nombre_id ) . '" id="' . esc_attr( $nombre_id ) . '" value="">';
        $html .= '<input type="hidden" name="' . esc_attr( $direccion_id ) . '" id="' . esc_attr( $direccion_id ) . '" value="">';
        $html .= '<div id="' . esc_attr( $details_id ) . '" class="andreani-sucursal-details andreani-sucursales-details" style="display:none;"></div>';

        /**
         * Permite reemplazar o envolver el markup del selector de sucursales.
         *
         * @param string $html        HTML completo del selector + campos ocultos + detalles.
         * @param int    $instance_id Índice de la instancia (0 = hook clásico, 1+ = shortcodes).
         * @param string $cp_destino  Código postal actual (puede estar vacío).
         */
        return apply_filters( 'andreani_sucursales_markup', $html, $instance_id, $cp_destino );
    }

    public function get_andreani_sucursales() {
        $chosen_methods  = WC()->session->get( 'chosen_shipping_methods' );
        $show_sucursales = false;

        if ( ! empty( $chosen_methods ) ) {
            foreach ( $chosen_methods as $method ) {
                if ( $this->is_andreani_sucursal_method( $method ) ) {
                    $show_sucursales = true;
                    break;
                }
            }
        }

        if ( ! $show_sucursales ) {
            return;
        }

        echo '<tr class="andreani-sucursales-row">';
        echo '<th>' . esc_html__( 'Sucursal', 'andreani-shipping' ) . ' <span class="required">*</span></th>';
        echo '<td>';
        echo $this->build_sucursales_markup( 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaping se realiza dentro de build_sucursales_markup
        echo '</td>';
        echo '</tr>';
    }

    private function is_ship_to_different_address() {
        if ( ! empty( $_POST ) ) {
            $nonce_field = 'security';
            $nonce_action = 'update-order-review';
            if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
                $customer = WC()->customer;
                if ($customer) {
                    $billing_postcode = $customer->get_billing_postcode();
                    $shipping_postcode = $customer->get_shipping_postcode();
                    return !empty($shipping_postcode) && $shipping_postcode !== $billing_postcode;
                }
                return false;
            }
        }

        if (isset($_POST['ship_to_different_address']) && !empty($_POST)) {
            return !empty(sanitize_text_field(wp_unslash($_POST['ship_to_different_address'])));
        }

        $customer = WC()->customer;
        if ($customer) {
            $billing_postcode = $customer->get_billing_postcode();
            $shipping_postcode = $customer->get_shipping_postcode();

            return !empty($shipping_postcode) && $shipping_postcode !== $billing_postcode;
        }

        return false;
    }

    private function get_current_postcode() {
        $cp_session = Andreani_Utils::get_session_data('cp_destino', '');
        if (!empty($cp_session)) {
            return $cp_session;
        }

        $is_ship_to_different = $this->is_ship_to_different_address();

        if ($is_ship_to_different) {
            $shipping_postcode = WC()->customer->get_shipping_postcode();
            if (!empty($shipping_postcode)) {
                return $shipping_postcode;
            }

            if ( ! empty( $_POST ) ) {
                $nonce_field = 'security';
                $nonce_action = 'update-order-review';
                if ( isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
                    if (isset($_POST['shipping_postcode']) && !empty($_POST['shipping_postcode'])) {
                        return sanitize_text_field(wp_unslash($_POST['shipping_postcode']));
                    }
                }
            }
        } else {
            $billing_postcode = WC()->customer->get_billing_postcode();
            if (!empty($billing_postcode)) {
                return $billing_postcode;
            }

            if ( ! empty( $_POST ) ) {
                $nonce_field = 'security';
                $nonce_action = 'update-order-review';
                if ( isset( $_POST[ $nonce_field ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
                    if (isset($_POST['billing_postcode']) && !empty($_POST['billing_postcode'])) {
                        return sanitize_text_field(wp_unslash($_POST['billing_postcode']));
                    }
                }
            }
        }

        return '';
    }

    public function capture_postcode_from_checkout_update($post_data) {
        parse_str($post_data, $form_data);

        $current_cp = Andreani_Utils::get_session_data('cp_destino', '');
        $new_cp = '';

        $is_ship_to_different = !empty($form_data['ship_to_different_address']);

        if ($is_ship_to_different) {
            if (!empty($form_data['shipping_postcode'])) {
                $new_cp = sanitize_text_field($form_data['shipping_postcode']);
            }
        } else {
            if (!empty($form_data['billing_postcode'])) {
                $new_cp = sanitize_text_field($form_data['billing_postcode']);
            }
        }

        if (!empty($new_cp) && strlen($new_cp) >= 4 && $new_cp !== $current_cp) {
            Andreani_Utils::set_session_data('cp_destino', $new_cp);
        }
    }

    /**
     * Encola el estilo y el script del checkout, más localización JS.
     * Llamado tanto por el hook de enqueue como por shortcodes (lazy).
     */
    private function do_enqueue_checkout_assets() {
        wp_enqueue_style(
            'andreani-checkout',
            ANDREANI_PLUGIN_URL . 'includes/assets/css/views/frontend-checkout.css',
            array( Andreani_Core_Assets::HANDLE_BASE ),
            ANDREANI_PLUGIN_VERSION
        );

        wp_enqueue_script(
            'andreani-checkout-js',
            ANDREANI_PLUGIN_URL . '/includes/assets/js/checkout.js',
            array( 'jquery' ),
            ANDREANI_PLUGIN_VERSION,
            true
        );

        wp_localize_script(
            'andreani-checkout-js',
            'andreaniCheckout',
            array(
                'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
                'nonce'        => wp_create_nonce( 'andreani_checkout_nonce' ),
                'checkoutModo' => Andreani_Settings_Service::get( 'checkout_modo', 'auto' ),
                'i18n'         => array(
                    'loading_sucursales'       => __( 'Cargando sucursales…', 'andreani-shipping' ),
                    'error_cp'                 => __( 'Ingresá un código postal válido para ver las sucursales.', 'andreani-shipping' ),
                    'select_sucursal'          => __( 'Seleccione una sucursal', 'andreani-shipping' ),
                    'error_sucursal_required'  => __( 'Por favor seleccione una sucursal para el envío.', 'andreani-shipping' ),
                ),
            )
        );
    }

    /**
     * Encola los assets del checkout en el camino "eager" — cuando sabemos de antemano
     * que el checkout se va a renderizar (checkout clásico de WC) o cuando el admin
     * activó el escape hatch `checkout_force_enqueue` para builders atípicos.
     *
     * En modo manual (shortcodes), los assets se encolan on-render desde cada shortcode
     * vía enqueue_assets_for_shortcode(). Este método NO intenta detectar shortcodes.
     */
    public function add_checkout_scripts() {
        $force_enqueue = Andreani_Settings_Service::get( 'checkout_force_enqueue', 'no' ) === 'yes';

        $razon = '';
        if ( is_checkout() ) {
            $razon = 'is_checkout';
        } elseif ( $force_enqueue ) {
            $razon = 'force_enqueue';
        }

        /**
         * Permite forzar o bloquear el encolado eager de assets del checkout.
         * Útil para integraciones custom (headless, CheckoutWC, etc.).
         *
         * @param bool   $should_enqueue Resolución del plugin.
         * @param string $razon          is_checkout | force_enqueue | ''.
         */
        $should_enqueue = (bool) apply_filters( 'andreani_should_enqueue_checkout', '' !== $razon, $razon );

        if ( ! $should_enqueue ) {
            return;
        }

        if ( '' === $razon ) {
            $razon = 'filter';
        }

        $this->do_enqueue_checkout_assets();

        if ( Andreani_Settings_Service::get( 'modo_debug', 'no' ) === 'yes' ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            Andreani_Utils::andreani_log( "[CHECKOUT ENQUEUE] razón={$razon} url={$url}", 'info' );
        }
    }

    /**
     * Encola assets del checkout bajo demanda al momento de renderizar un shortcode.
     * Es el camino "lazy" — no depende de pre-detección de builders ni metas de page
     * builders. Si el shortcode se ejecuta (Elementor, Divi, Bricks, el que sea),
     * los assets se encolan y WordPress los imprime en el footer.
     *
     * Evita encolar doble si ya estaban encolados (checkout clásico, etc.).
     */
    private function enqueue_assets_for_shortcode() {
        if ( wp_script_is( 'andreani-checkout-js', 'enqueued' ) ) {
            return;
        }
        $this->do_enqueue_checkout_assets();

        if ( Andreani_Settings_Service::get( 'modo_debug', 'no' ) === 'yes' ) {
            $url = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            Andreani_Utils::andreani_log( "[CHECKOUT ENQUEUE] razón=shortcode url={$url}", 'info' );
        }
    }

    public function add_defer_attribute( $tag, $handle ) {
        if ( 'andreani-checkout-js' === $handle ) {
            return str_replace( ' src', ' defer src', $tag );
        }
        return $tag;
    }

    public function process_after_shipping_calculator() {
        if ( empty( $_POST ) || ( ! is_cart() && ! is_checkout() ) ) {
            return;
        }

        $nonce_action = is_checkout() ? 'update-order-review' : 'woocommerce-cart';
        $nonce_field = is_checkout() ? 'security' : 'woocommerce-cart-nonce';
        
        if ( ! isset( $_POST[ $nonce_field ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ), $nonce_action ) ) {
            return;
        }

        $current_cp = Andreani_Utils::get_session_data('cp_destino', '');

        $new_cp = '';
        if ( isset( $_POST['calc_shipping_postcode'] ) && ! empty( $_POST['calc_shipping_postcode'] ) ) {
            $new_cp = sanitize_text_field( wp_unslash( $_POST['calc_shipping_postcode'] ) );
        } elseif ( isset( $_POST['shipping_postcode'] ) && ! empty( $_POST['shipping_postcode'] ) && $this->is_ship_to_different_address() ) {
            $new_cp = sanitize_text_field( wp_unslash( $_POST['shipping_postcode'] ) );
        } elseif ( isset( $_POST['billing_postcode'] ) && ! empty( $_POST['billing_postcode'] ) ) {
            $new_cp = sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) );
        }

        if (!empty($new_cp) && strlen($new_cp) >= 4 && $new_cp !== $current_cp) {
            Andreani_Utils::set_session_data('cp_destino', $new_cp);
        }

        if ( is_cart() && isset( $_POST['calc_shipping_postcode'] ) ) {
            Andreani_Utils::set_session_data('cp_destino', sanitize_text_field( wp_unslash( $_POST['calc_shipping_postcode'] ) ) );
        }

        if ( is_checkout() ) {
            $sucursal_code = $this->get_selected_sucursal_from_post();
            if ( ! empty( $sucursal_code ) ) {
                Andreani_Utils::set_session_data( 'codigo_sucursal', $sucursal_code );
            }
        }
    }

    public function update_andreani_order_meta( $order_id ) {
        if ( ! $order_id ) {
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $meta_updated = false;

        $chosen_shipping = WC()->session->get( 'chosen_shipping_methods' );
        if ( ! empty( $chosen_shipping ) && is_array( $chosen_shipping ) ) {
            $shipping_method = sanitize_text_field( $chosen_shipping[0] );
            $order->update_meta_data( '_chosen_shipping', $shipping_method );
            $meta_updated = true;

            if ( strpos( $shipping_method, 'andreani' ) !== false ) {
                Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Checkout completado - Método de envío: {$shipping_method}", 'info' );
            }
        }

        $billing_dni_fields = array('billing_dni', 'billing_vat', 'billing_cedula', 'billing_document', 'billing_cuit');
        $billing_dni = '';
        foreach ($billing_dni_fields as $field_name) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! empty( $_POST[$field_name] ) ) {
                $billing_dni = sanitize_text_field( wp_unslash( $_POST[$field_name] ) );
                break;
            }
        }
        if ( ! empty( $billing_dni ) && $this->validate_dni( $billing_dni ) ) {
            $order->update_meta_data( '_billing_dni', $billing_dni );
            $meta_updated = true;
        }

        $shipping_dni_fields = array('shipping_dni', 'shipping_vat', 'shipping_cedula', 'shipping_document', 'shipping_cuit');
        $shipping_dni = '';
        foreach ($shipping_dni_fields as $field_name) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( ! empty( $_POST[$field_name] ) ) {
                $shipping_dni = sanitize_text_field( wp_unslash( $_POST[$field_name] ) );
                break;
            }
        }
        if ( ! empty( $shipping_dni ) && $this->validate_dni( $shipping_dni ) ) {
            $order->update_meta_data( '_shipping_dni', $shipping_dni );
            $meta_updated = true;
        }

        $sucursal = $this->get_selected_sucursal_from_post();

        if ( ! empty( $sucursal ) ) {
            $order->update_meta_data( 'sucursal_andreani', $sucursal );
            $order->update_meta_data( '_shipping_branch_code', $sucursal );
            $meta_updated = true;

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $nombre = ! empty( $_POST['sucursal_nombre'] ) ? sanitize_text_field( wp_unslash( $_POST['sucursal_nombre'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( empty( $nombre ) && ! empty( $_POST['sucursal_nombre_sync'] ) ) {
                $nombre = sanitize_text_field( wp_unslash( $_POST['sucursal_nombre_sync'] ) );
            }
            if ( ! empty( $nombre ) ) {
                $order->update_meta_data( '_andreani_sucursal_nombre', $nombre );
            }

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $direccion = ! empty( $_POST['sucursal_direccion'] ) ? sanitize_text_field( wp_unslash( $_POST['sucursal_direccion'] ) ) : '';
            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( empty( $direccion ) && ! empty( $_POST['sucursal_direccion_sync'] ) ) {
                $direccion = sanitize_text_field( wp_unslash( $_POST['sucursal_direccion_sync'] ) );
            }
            if ( ! empty( $direccion ) ) {
                $order->update_meta_data( '_andreani_sucursal_direccion', $direccion );
            }

            Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Sucursal seleccionada: {$sucursal}", 'info' );
        }

        if ( $meta_updated ) {
            $order->save();
        }
    }

    public function persist_andreani_rate_id( $item, $package_key, $package, $order ) {
        $method_id = $item->get_method_id();
        if ( ANDREANI_SHIPPING_METHOD_ID !== $method_id ) {
            return;
        }

        $chosen = WC()->session ? WC()->session->get( 'chosen_shipping_methods' ) : null;
        if ( empty( $chosen ) || ! is_array( $chosen ) ) {
            return;
        }

        $rate_id = isset( $chosen[ $package_key ] ) ? $chosen[ $package_key ] : reset( $chosen );
        if ( empty( $rate_id ) ) {
            return;
        }

        $item->add_meta_data( '_andreani_rate_id', sanitize_text_field( $rate_id ), true );
    }

    /**
     * Shortcode [andreani_sucursales] — permite incrustar el selector de sucursales
     * en cualquier página o builder externo (ej: Elementor) sin depender del hook
     * woocommerce_review_order_after_shipping.
     *
     * @param array $atts Atributos del shortcode (no utilizados actualmente).
     * @return string HTML del bloque de sucursales envuelto en un div standalone.
     */
    public function render_sucursales_shortcode( $atts ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return '';
        }

        // Contador estático para asignar IDs únicos en múltiples instancias.
        // Arranca en 1 porque el instance_id=0 queda reservado para el hook clásico
        // (build_sucursales_markup(0) emitido en woocommerce_review_order_after_shipping),
        // evitando colisión de IDs si alguien mezcla modo auto + shortcode en la misma página.
        static $counter = 1;
        $instance_id = $counter;
        $counter++;

        $this->enqueue_assets_for_shortcode();

        return '<div class="andreani-sucursales-standalone">' . $this->build_sucursales_markup( $instance_id ) . '</div>';
    }

    /**
     * Shortcode [andreani_dni_field context="billing|shipping"] — renderiza el campo DNI
     * como un form-row independiente, útil en page builders o checkouts personalizados.
     *
     * @param array $atts Atributos del shortcode. Acepta: context (billing|shipping).
     * @return string HTML del campo DNI.
     */
    public function render_dni_field_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'context' => 'billing' ), $atts, 'andreani_dni_field' );

        $context = in_array( $atts['context'], array( 'billing', 'shipping' ), true ) ? $atts['context'] : 'billing';

        $this->enqueue_assets_for_shortcode();

        $field_args = $this->render_dni_field_markup( $context );
        $field_name = $context . '_dni';
        $field_id   = esc_attr( $field_name );
        $label      = isset( $field_args['label'] ) ? $field_args['label'] : __( 'DNI', 'andreani-shipping' );
        $required   = ! empty( $field_args['required'] );
        $placeholder = isset( $field_args['placeholder'] ) ? $field_args['placeholder'] : '';

        $html  = '<p class="form-row form-row-wide andreani-dni-field-shortcode">';
        $html .= '<label for="' . $field_id . '">';
        $html .= esc_html( $label );
        if ( $required ) {
            $html .= ' <abbr class="required" title="' . esc_attr__( 'requerido', 'andreani-shipping' ) . '">*</abbr>';
        }
        $html .= '</label>';
        $html .= '<input type="text" name="' . $field_id . '" id="' . $field_id . '" class="input-text" placeholder="' . esc_attr( $placeholder ) . '"' . ( $required ? ' required' : '' ) . '>';
        $html .= '</p>';

        /**
         * Permite reemplazar el markup del campo DNI renderizado por el shortcode.
         *
         * @param string $html       HTML completo del form-row + label + input.
         * @param string $context    'billing' o 'shipping'.
         * @param array  $field_args Args resueltos por render_dni_field_markup() (label, required, placeholder, etc.).
         */
        return apply_filters( 'andreani_dni_field_markup', $html, $context, $field_args );
    }


    private function get_selected_sucursal_from_post() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $sucursal_code = isset( $_POST['sucursales_andreani'] ) && $_POST['sucursales_andreani'] !== '0'
            ? sanitize_text_field( wp_unslash( $_POST['sucursales_andreani'] ) )
            : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( empty( $sucursal_code ) && ! empty( $_POST['sucursales_andreani_sync'] ) && $_POST['sucursales_andreani_sync'] !== '0' ) {
            $sucursal_code = sanitize_text_field( wp_unslash( $_POST['sucursales_andreani_sync'] ) );
        }
        return $sucursal_code;
    }

    /**
     * Valida el formato de un DNI (7 u 8 dígitos, tolera puntos y espacios).
     */
    public static function is_valid_dni( $value ) {
        $dni = preg_replace( '/[.\s]/', '', (string) $value );

        return (bool) preg_match( '/^\d{7,8}$/', $dni );
    }

    private function validate_dni( $dni ) {
        return self::is_valid_dni( $dni );
    }

    public function validate_sucursal_field() {
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        $needs_sucursal = false;

        if (!empty($chosen_methods)) {
            foreach ($chosen_methods as $method) {
                if ( $this->is_andreani_sucursal_method( $method ) ) {
                    $needs_sucursal = true;
                    break;
                }
            }
        }

        if ($needs_sucursal) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce maneja la verificación del nonce
            $sucursal_id = isset($_POST['sucursales_andreani']) ? sanitize_text_field(wp_unslash($_POST['sucursales_andreani'])) : '';

            // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if (empty($sucursal_id) || $sucursal_id === '0') {
                $sucursal_id = isset($_POST['sucursales_andreani_sync']) ? sanitize_text_field(wp_unslash($_POST['sucursales_andreani_sync'])) : '';
            }

            if (empty($sucursal_id) || $sucursal_id === '0') {
                wc_add_notice(__('Por favor seleccione una sucursal de Andreani para continuar con su pedido.', 'andreani-shipping'), 'error');
            }
        }
    }

    public function agregar_costo_adicional() {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        $chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
        if ( empty( $chosen_methods ) ) {
            return;
        }

        $method = $chosen_methods[0];
        if ( strpos( $method, 'andreani' ) === false ) {
            return;
        }

        $modo = $this->extraer_modo_de_metodo( $method );
        if ( ! $modo ) {
            return;
        }

        $config_json = Andreani_Settings_Service::get( 'config_por_modo', '{}' );
        $config_por_modo = json_decode( $config_json, true );
        if ( ! is_array( $config_por_modo ) ) {
            $config_por_modo = array();
        }

        // Buscar primero por slug normalizado (1.5.0+), fallback a key cruda para config vieja.
        $modo_key = Andreani_Api_Response::normalize_modo_key( $modo );
        if ( '' !== $modo_key && isset( $config_por_modo[ $modo_key ] ) ) {
            $modo_config = $config_por_modo[ $modo_key ];
        } elseif ( isset( $config_por_modo[ $modo ] ) ) {
            $modo_config = $config_por_modo[ $modo ];
        } else {
            $modo_config = array();
        }
        $costo_adicional_enabled = isset( $modo_config['costo_adicional_enabled'] ) ? $modo_config['costo_adicional_enabled'] : false;

        if ( ! $costo_adicional_enabled ) {
            return;
        }

        $costo = isset( $modo_config['costo_adicional'] ) ? floatval( $modo_config['costo_adicional'] ) : 0;

        if ( $costo <= 0 ) {
            return;
        }

        $motivo = isset( $modo_config['motivo'] ) && ! empty( $modo_config['motivo'] )
            ? sanitize_text_field( $modo_config['motivo'] )
            : '';

        $fee_label = __( 'Costos adicionales', 'andreani-shipping' );
        if ( ! empty( $motivo ) ) {
            $fee_label .= ' (' . $motivo . ')';
        }

        WC()->cart->add_fee( $fee_label, $costo );
    }

    private function extraer_modo_de_metodo( $method ) {
        foreach ( Andreani_Client_Type::all_prefixes() as $prefix ) {
            if ( strpos( $method, $prefix ) === 0 ) {
                return str_replace( $prefix, '', $method );
            }
        }
        Andreani_Utils::andreani_log( "[CHECKOUT] No se pudo extraer modo de entrega del método: {$method}", 'warning' );
        return null;
    }
}