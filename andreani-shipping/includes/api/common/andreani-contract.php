<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Contract {

	private $id;
	private $number;
	private $delivery_mode;
	private $shipping_type;
	private $template;
	private $raw;

	public function __construct( array $data, $id_override = null ) {
		$this->raw           = $data;
		$this->id            = null !== $id_override ? $id_override : ( isset( $data['id'] ) ? $data['id'] : null );
		$this->number        = isset( $data['numeroDeContrato'] ) ? (string) $data['numeroDeContrato'] : '';
		$this->delivery_mode = isset( $data['modoDeEntregaNombre'] ) ? (string) $data['modoDeEntregaNombre'] : '';
		$this->shipping_type = isset( $data['tipoDeEnvioNombre'] ) ? (string) $data['tipoDeEnvioNombre'] : '';
		$this->template      = isset( $data['plantilla'] ) ? (string) $data['plantilla'] : '';
	}

	public function get_id() {
		return $this->id;
	}

	public function get_number() {
		return $this->number;
	}

	public function get_delivery_mode() {
		return $this->delivery_mode;
	}

	public function get_shipping_type() {
		return $this->shipping_type;
	}

	public function get_template() {
		return $this->template;
	}

	public function is_sucursal() {
		return 'sucursal' === $this->delivery_mode;
	}

	public function get_raw() {
		return $this->raw;
	}
}
