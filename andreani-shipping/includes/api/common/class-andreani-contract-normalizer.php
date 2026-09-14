<?php
/**
 * Normalización de los contratos que devuelve la API de Andreani.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Contract_Normalizer {

	const MODO_ENTREGA_MAPPING = array(
		'A domicilio' => 'estándar',
		'A sucursal'  => 'sucursal',
		'Llega hoy'   => 'llega hoy',
	);

	const TIPO_ENVIO_BIGGER = 'bigger';

	public static function normalize_contrato( $contrato ) {
		if ( ! is_array( $contrato ) ) {
			return $contrato;
		}

		$tipo_envio = isset( $contrato['tipoDeEnvioNombre'] )
			? strtolower( $contrato['tipoDeEnvioNombre'] )
			: '';

		if ( self::TIPO_ENVIO_BIGGER === $tipo_envio ) {
			$contrato['modoDeEntregaNombre'] = self::TIPO_ENVIO_BIGGER;
		} elseif ( isset( $contrato['modoDeEntregaNombre'] ) ) {
			$contrato['modoDeEntregaNombre'] = self::normalize_modo_entrega( $contrato['modoDeEntregaNombre'] );
		}

		return $contrato;
	}

	public static function normalize_contratos( $contratos ) {
		if ( ! is_array( $contratos ) ) {
			return array();
		}

		return array_map( array( __CLASS__, 'normalize_contrato' ), $contratos );
	}

	public static function normalize_modo_entrega( $modo_entrega_nombre ) {
		if ( isset( self::MODO_ENTREGA_MAPPING[ $modo_entrega_nombre ] ) ) {
			return self::MODO_ENTREGA_MAPPING[ $modo_entrega_nombre ];
		}

		return strtolower( (string) $modo_entrega_nombre );
	}
}
