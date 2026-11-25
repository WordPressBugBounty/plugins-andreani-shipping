<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Email_Ready_Pickup extends Andreani_Email_Tracking {

	protected function email_meta() {
		return array(
			'id'          => Andreani_Tracking_Notifier::PICKUP_EMAIL_ID,
			'title'       => __( 'Andreani: tu pedido está listo para retirar', 'andreani-shipping' ),
			'description' => __( 'Le avisa al comprador, una sola vez, cuando su envío a sucursal o punto HOP llegó y está listo para retirar.', 'andreani-shipping' ),
			'template'    => 'customer-andreani-ready-pickup.php',
		);
	}

	public function get_default_subject() {
		return __( 'Tu pedido #{order_number} ya está listo para retirar', 'andreani-shipping' );
	}

	public function get_default_heading() {
		return __( 'Tu pedido está listo para retirar', 'andreani-shipping' );
	}

	public function get_default_additional_content() {
		return __( 'Llevá tu DNI y el número de seguimiento al retirar tu pedido. Gracias por tu compra.', 'andreani-shipping' );
	}
}
