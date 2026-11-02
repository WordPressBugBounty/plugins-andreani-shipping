<?php
/**
 * Mail "Andreani: tu envío está en camino" (texto plano).
 * Se puede personalizar copiando este archivo a tu-tema/woocommerce/emails/plain/customer-andreani-tracking.php
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

echo '= ' . esc_html( wp_strip_all_tags( $email_heading ) ) . " =\n\n";

/* translators: %s: nombre del comprador */
echo sprintf( esc_html__( 'Hola %s,', 'andreani-shipping' ), esc_html( $order->get_billing_first_name() ) ) . "\n\n";

/* translators: %s: número de pedido */
echo sprintf( esc_html__( 'Tu pedido #%s ya está en camino con Andreani.', 'andreani-shipping' ), esc_html( $order->get_order_number() ) ) . "\n\n";

echo esc_html__( 'N° de seguimiento:', 'andreani-shipping' ) . ' ' . esc_html( $tracking_number ) . "\n";
echo esc_html__( 'Seguí tu envío en:', 'andreani-shipping' ) . ' ' . esc_url( $tracking_url ) . "\n\n";

if ( '' !== $sucursal_nombre ) {
	echo esc_html__( 'Punto de retiro:', 'andreani-shipping' ) . ' ' . esc_html( $sucursal_nombre ) . "\n";
	if ( '' !== $sucursal_direccion ) {
		echo esc_html( $sucursal_direccion ) . "\n";
	}
	echo "\n";
}

echo "----------------------------------------\n\n";

do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n----------------------------------------\n\n";

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo "\n\n" . esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n\n";
}

echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
