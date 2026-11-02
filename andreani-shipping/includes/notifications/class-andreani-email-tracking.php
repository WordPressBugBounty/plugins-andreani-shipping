<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Email_Tracking extends WC_Email {

	public $tracking_number = '';

	public function __construct() {
		$this->id             = Andreani_Tracking_Notifier::EMAIL_ID;
		$this->customer_email = true;
		$this->title          = __( 'Andreani: tu envío está en camino', 'andreani-shipping' );
		$this->description    = __( 'Le avisa al comprador, una sola vez, cuando su envío de Andreani tiene número de seguimiento.', 'andreani-shipping' );
		$this->template_html  = 'emails/customer-andreani-tracking.php';
		$this->template_plain = 'emails/plain/customer-andreani-tracking.php';
		$this->template_base  = ANDREANI_PLUGIN_DIR . 'templates/';
		$this->placeholders   = array(
			'{order_date}'      => '',
			'{order_number}'    => '',
			'{tracking_number}' => '',
		);

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Tu pedido #{order_number} ya está en camino', 'andreani-shipping' );
	}

	public function get_default_heading() {
		return __( 'Tu pedido ya está en camino', 'andreani-shipping' );
	}

	public function get_default_additional_content() {
		return __( 'Con este número podés seguir tu envío en cualquier momento. Gracias por tu compra.', 'andreani-shipping' );
	}

	/**
	 * @param WC_Order $order
	 * @param string   $tracking
	 * @return bool
	 */
	public function trigger( $order, $tracking = '' ) {
		$this->setup_locale();

		$sent = false;

		if ( is_a( $order, 'WC_Order' ) ) {
			$this->object                         = $order;
			$this->tracking_number                = (string) $tracking;
			$this->recipient                      = $order->get_billing_email();
			$this->placeholders['{order_date}']   = wc_format_datetime( $order->get_date_created() );
			$this->placeholders['{order_number}'] = $order->get_order_number();
			$this->placeholders['{tracking_number}'] = $this->tracking_number;

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$sent = (bool) $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();

		return $sent;
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->template_args( true ), '', $this->template_base );
	}

	private function template_args( $plain_text ) {
		$data = Andreani_Order::get_shipping_data( $this->object );

		return array(
			'order'              => $this->object,
			'email_heading'      => $this->get_heading(),
			'additional_content' => $this->get_additional_content(),
			'tracking_number'    => $this->tracking_number,
			'tracking_url'       => 'https://www.andreani.com/envio/' . rawurlencode( $this->tracking_number ),
			'sucursal_nombre'    => ( $data && $data['has_sucursal'] ) ? (string) $data['sucursal_nombre'] : '',
			'sucursal_direccion' => ( $data && $data['has_sucursal'] ) ? (string) $data['sucursal_direccion'] : '',
			'logo_url'           => ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani.png',
			'sent_to_admin'      => false,
			'plain_text'         => $plain_text,
			'email'              => $this,
		);
	}

	public function init_form_fields() {
		$placeholder_text = sprintf(
			/* translators: %s: lista de placeholders */
			__( 'Variables disponibles: %s', 'andreani-shipping' ),
			'<code>' . esc_html( implode( '</code>, <code>', array_keys( $this->placeholders ) ) ) . '</code>'
		);

		$this->form_fields = array(
			'enabled'            => array(
				'title'   => __( 'Activar/Desactivar', 'andreani-shipping' ),
				'type'    => 'checkbox',
				'label'   => __( 'Activar este mail', 'andreani-shipping' ),
				'default' => 'no',
			),
			'subject'            => array(
				'title'       => __( 'Asunto', 'andreani-shipping' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_subject(),
				'default'     => '',
			),
			'heading'            => array(
				'title'       => __( 'Encabezado del mail', 'andreani-shipping' ),
				'type'        => 'text',
				'desc_tip'    => true,
				'description' => $placeholder_text,
				'placeholder' => $this->get_default_heading(),
				'default'     => '',
			),
			'additional_content' => array(
				'title'       => __( 'Texto adicional', 'andreani-shipping' ),
				'description' => __( 'Texto que aparece debajo del detalle del pedido.', 'andreani-shipping' ) . ' ' . $placeholder_text,
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'andreani-shipping' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			),
			'email_type'         => array(
				'title'       => __( 'Formato del mail', 'andreani-shipping' ),
				'type'        => 'select',
				'description' => __( 'Elegí el formato en que se envía el mail.', 'andreani-shipping' ),
				'default'     => 'html',
				'class'       => 'email_type wc-enhanced-select',
				'options'     => $this->get_email_type_options(),
				'desc_tip'    => true,
			),
		);
	}
}
