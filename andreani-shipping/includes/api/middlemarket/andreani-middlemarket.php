<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Middle_Market_Api extends Andreani_Base_Api {
	const SHIPPING_METHOD_PREFIX = 'andreani_middlemarket_';
	const CLIENT_INFO_OPTION     = 'andreani_middlemarket_info';
	protected static $client_type = 'middle_market';

	/**
	 * @param array $package Paquete de WooCommerce
	 * @return array|null Body para la API
	 */
	protected function build_cotizacion_body( $package ) {
		$products = Andreani_Api_Response::prepare_products_for_cotizacion( $package, $this->mostrar_sin_decimales );
		if ( empty( $products ) ) {
			return null;
		}
		$body = array(
			'postal_code_origin'      => Andreani_Postcode::normalize( $this->cp_origen ),
			'postal_code_destination' => $package['destination']['postcode'],
			'products'                => $products,
		);

		$pin = Andreani_Origen::get_pin();
		if ( '' !== $pin ) {
			$body['branch_code_origin'] = $pin;
		}

		return $body;
	}

	protected function get_contract_for_delivery_mode( $delivery_mode ) {
		$contratos = isset( $this->info_cliente['contratos'] ) && is_array( $this->info_cliente['contratos'] )
			? $this->info_cliente['contratos']
			: array();

		foreach ( $contratos as $contrato ) {
			if ( $contrato['modoDeEntregaNombre'] === $delivery_mode ) {
				$id = isset( $contrato['id'] ) ? $contrato['id'] : null;
				return new Andreani_Contract( $contrato, $id );
			}
		}

		return new WP_Error(
			'andreani_order_invalid_delivery_mode',
			'El modo de entrega no está configurado en los contratos de Andreani Middle Market'
		);
	}

	protected function assemble_order_body( array $context, Andreani_Contract $contract ) {
		$destination = $context['destination'];

		$origin = array(
			'postal_code' => Andreani_Postcode::normalize( $this->cp_origen ),
		);

		$pin = Andreani_Origen::get_pin();
		if ( '' !== $pin ) {
			$origin['code_branch'] = $pin;
		}

		return array(
			'contract'       => array(
				'id_contract' => $contract->get_id(),
			),
			'price_shipment' => $context['shipping_total'],
			'origin'         => $origin,
			'destination'    => array(
				'street'      => $destination['street'],
				'number'      => $destination['number'],
				'floor'       => ! empty( $destination['address_2'] ) ? $destination['address_2'] : '',
				'postal_code' => $destination['postcode'],
				'locality'    => $destination['city'],
				'code_branch' => $context['code_branch'],
			),
			'recipient'      => $context['recipient'],
			'products'       => $context['products'],
			'email_merchant' => get_option( 'woocommerce_email_from_address', '' ),
			'remito'         => $context['remito'],
		);
	}

	/**
	 * @param array $data Datos del cliente desde API
	 */
	protected function save_client_info( $data ) {
		update_option( self::CLIENT_INFO_OPTION, $data );
	}
}
