<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Origen_Ajax {

	const NONCE_SUCURSALES = 'andreani_origen_sucursales';
	const NONCE_DEFAULT    = 'andreani_origen_default';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_andreani_origen_sucursales', array( $this, 'handle_sucursales' ) );
		add_action( 'wp_ajax_andreani_origen_default', array( $this, 'handle_default' ) );
	}

	public function handle_sucursales() {
		$postcode = $this->guard( self::NONCE_SUCURSALES );

		$result = Andreani_Api_Manager::get_sucursales_origen( $postcode );

		if ( null === $result ) {
			wp_send_json_error(
				array( 'message' => __( 'Todavía no podemos consultar las sucursales: revisá tu credencial.', 'andreani-shipping' ) ),
				409
			);
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 502 );
		}

		$details = isset( $result['details'] ) && is_array( $result['details'] ) ? $result['details'] : array();

		$sucursales = array();
		foreach ( $details as $codigo => $detalle ) {
			$sucursales[] = array(
				'codigo'    => (string) $codigo,
				'nombre'    => isset( $detalle['descripcion'] ) ? (string) $detalle['descripcion'] : '',
				'direccion' => isset( $detalle['direccion'] ) ? (string) $detalle['direccion'] : '',
				'tipo'      => isset( $detalle['tipo'] ) ? (string) $detalle['tipo'] : '',
				'horario'   => isset( $detalle['horario'] ) ? (string) $detalle['horario'] : '',
			);
		}

		wp_send_json_success(
			array(
				'postcode'   => $postcode,
				'sucursales' => $sucursales,
			)
		);
	}

	public function handle_default() {
		$postcode = $this->guard( self::NONCE_DEFAULT );

		$result = Andreani_Api_Manager::get_origen_predeterminado( $postcode );

		if ( null === $result || is_wp_error( $result ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No pudimos averiguar desde qué sucursal salen hoy tus envíos.', 'andreani-shipping' ) ),
				502
			);
		}

		wp_send_json_success(
			array(
				'postcode'  => $postcode,
				'codigo'    => isset( $result['codigo'] ) ? (string) $result['codigo'] : '',
				'nombre'    => isset( $result['descripcion'] ) ? (string) $result['descripcion'] : '',
				'direccion' => isset( $result['direccion'] ) ? (string) $result['direccion'] : '',
			)
		);
	}

	/**
	 * Verifica nonce y permisos y devuelve el CP saneado. Corta la request si algo falla.
	 *
	 * @param string $action Acción del nonce.
	 * @return string Código postal normalizado.
	 */
	private function guard( $action ) {
		check_ajax_referer( $action, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No tenés permisos para realizar esta acción.', 'andreani-shipping' ) ),
				403
			);
		}

		$postcode = isset( $_POST['postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['postcode'] ) ) : '';

		if ( ! Andreani_Postcode::is_valid( $postcode ) ) {
			wp_send_json_error(
				array( 'message' => __( 'El código postal no tiene un formato válido. Debe ser de 4 dígitos (ej: 1425) o formato CPA (ej: C1425ABC).', 'andreani-shipping' ) ),
				400
			);
		}

		return Andreani_Postcode::normalize( $postcode );
	}
}
