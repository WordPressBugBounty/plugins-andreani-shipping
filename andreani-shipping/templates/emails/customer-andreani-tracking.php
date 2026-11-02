<?php
/**
 * Mail "Andreani: tu envío está en camino".
 * Se puede personalizar copiando este archivo a tu-tema/woocommerce/emails/customer-andreani-tracking.php
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$base_color = get_option( 'woocommerce_email_base_color', '#7f54b3' );
$base_color = $base_color ? $base_color : '#7f54b3';
$text_color = wc_light_or_dark( $base_color, '#202020', '#ffffff' );

do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?php printf( esc_html__( 'Hola %s,', 'andreani-shipping' ), esc_html( $order->get_billing_first_name() ) ); ?></p>
<p>
	<?php
	printf(
		/* translators: %s: número de pedido */
		esc_html__( 'Tu pedido #%s ya está en camino con Andreani. Este es tu número de seguimiento:', 'andreani-shipping' ),
		esc_html( $order->get_order_number() )
	);
	?>
</p>

<table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 0 0 24px;">
	<tr>
		<td align="center" style="padding: 24px 16px; border: 1px solid #e5e5e5; border-radius: 4px;">
			<img src="<?php echo esc_url( $logo_url ); ?>" alt="Andreani" width="136" height="25" style="display: block; margin: 0 auto 16px; border: 0; width: 136px; height: 25px;" />
			<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #757575; margin: 0 0 8px;"><?php esc_html_e( 'N° de seguimiento', 'andreani-shipping' ); ?></div>
			<div style="font-size: 28px; line-height: 34px; font-weight: bold; margin: 0 0 20px; word-break: break-all;"><?php echo esc_html( $tracking_number ); ?></div>
			<table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
				<tr>
					<td align="center" bgcolor="<?php echo esc_attr( $base_color ); ?>" style="border-radius: 4px;">
						<a href="<?php echo esc_url( $tracking_url ); ?>" target="_blank" style="display: inline-block; padding: 12px 28px; font-size: 16px; font-weight: bold; text-decoration: none; color: <?php echo esc_attr( $text_color ); ?>; background-color: <?php echo esc_attr( $base_color ); ?>; border-radius: 4px;"><?php esc_html_e( 'Seguí tu envío', 'andreani-shipping' ); ?></a>
					</td>
				</tr>
			</table>
		</td>
	</tr>
</table>

<?php if ( '' !== $sucursal_nombre ) : ?>
	<p style="margin: 0 0 24px;">
		<?php esc_html_e( 'Punto de retiro:', 'andreani-shipping' ); ?>
		<strong><?php echo esc_html( $sucursal_nombre ); ?></strong>
		<?php if ( '' !== $sucursal_direccion ) : ?>
			<br /><?php echo esc_html( $sucursal_direccion ); ?>
		<?php endif; ?>
	</p>
<?php endif; ?>

<?php
do_action( 'woocommerce_email_order_details', $order, $sent_to_admin, $plain_text, $email );

do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );

if ( $additional_content ) {
	echo '<p>' . wp_kses_post( wptexturize( $additional_content ) ) . '</p>';
}

do_action( 'woocommerce_email_footer', $email );
