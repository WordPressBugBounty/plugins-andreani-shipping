<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Sucursales_Ajax {

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_andreani_get_sucursales', array( $this, 'handle' ) );
		add_action( 'wp_ajax_nopriv_andreani_get_sucursales', array( $this, 'handle' ) );
	}

	public function handle() {
		// Dual-nonce: acepta tanto el nonce propio de Andreani (introducido en v1.5.0)
		// como el nonce legacy de WooCommerce ('update-order-review').
		$new_ok    = isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'andreani_checkout_nonce' );
		$legacy_ok = isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'update-order-review' );

		if ( ! $new_ok && ! $legacy_ok ) {
			wp_send_json_error( array( 'message' => __( 'Nonce inválido', 'andreani-shipping' ) ), 403 );
			return;
		}

		if ( ! isset( $_POST['postcode'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Código postal requerido', 'andreani-shipping' ) ) );
			return;
		}

		$postcode = sanitize_text_field( wp_unslash( $_POST['postcode'] ) );

		if ( ! Andreani_Postcode::is_valid( $postcode ) ) {
			wp_send_json_error( array(
				'message' => __( 'Formato de código postal inválido. Debe ser de 4 dígitos o formato CPA (ej: C1425ABC).', 'andreani-shipping' ),
			) );
			return;
		}

		Andreani_Utils::set_session_data( 'cp_destino', $postcode );
		Andreani_Utils::andreani_log( "[CHECKOUT] Solicitando sucursales para CP: {$postcode}", 'debug' );

		$result = Andreani_Api_Manager::get_sucursales( $postcode );

		if ( is_wp_error( $result ) ) {
			Andreani_Utils::andreani_log( "[CHECKOUT] Error al obtener sucursales para CP {$postcode}: " . $result->get_error_message(), 'error' );
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		if ( empty( $result ) ) {
			Andreani_Utils::andreani_log( "[CHECKOUT] No se encontraron sucursales para CP {$postcode}", 'warning' );
			wp_send_json_success( array(
				'options'  => array( '0' => 'No se encontraron sucursales para el código postal ' . $postcode ),
				'details'  => array(),
				'postcode' => $postcode,
			) );
			return;
		}

		if ( isset( $result['options'] ) ) {
			$options = $result['options'];
			$details = isset( $result['details'] ) ? $result['details'] : array();
		} else {
			$options = $result;
			$details = array();
		}

		$count = is_array( $options ) ? count( $options ) : 0;
		Andreani_Utils::andreani_log( "[CHECKOUT] Enviando {$count} sucursales al frontend para CP {$postcode}", 'debug' );

		wp_send_json_success( array(
			'options'  => $options,
			'details'  => $details,
			'postcode' => $postcode,
		) );
	}
}
