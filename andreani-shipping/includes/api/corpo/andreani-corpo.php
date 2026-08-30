<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

require_once ANDREANI_PLUGIN_DIR . 'includes/api/common/andreani-api-config.php';

class Andreani_Corpo_Api extends Andreani_Base_Api {
	const SHIPPING_METHOD_PREFIX = 'andreani_corporativo_';
	const CLIENT_INFO_OPTION     = 'andreani_corporativo_info';
	protected static $client_type = 'corporativo';

	/**
	 * @param array $package Paquete de WooCommerce
	 * @return array|null Body para la API, null si no hay contratos
	 */
	protected function build_cotizacion_body( $package ) {
		$contratos = isset( $this->info_cliente['contratos'] ) ? $this->info_cliente['contratos'] : array();

		if ( empty( $contratos ) ) {
			return null;
		}

		$products = Andreani_Api_Response::prepare_products_for_cotizacion( $package, $this->mostrar_sin_decimales );
		if ( empty( $products ) ) {
			return null;
		}

		$body = array(
			'cl'                      => $this->info_cliente['cl'],
			'postal_code_origin'      => Andreani_Postcode::normalize( $this->cp_origen ),
			'postal_code_destination' => $package['destination']['postcode'],
			'products'                => $products,
			'Contracts'               => array(),
		);

		$pin = Andreani_Origen::get_pin();
		if ( '' !== $pin ) {
			$body['branch_code_origin'] = $pin;
		}

		foreach ( $contratos as $contrato ) {
			$body['Contracts'][] = array(
				'contract'      => $contrato['numeroDeContrato'],
				'delivery_mode' => $contrato['modoDeEntregaNombre'],
			);
		}

		return $body;
	}

	protected function get_contract_for_delivery_mode( $delivery_mode ) {
		foreach ( $this->info_cliente['contratos'] as $item ) {
			if ( $item['modoDeEntregaNombre'] === $delivery_mode ) {
				return new Andreani_Contract( $item );
			}
		}

		return new WP_Error(
			'andreani_order_invalid_delivery_mode',
			'El modo de entrega no está configurado en los contratos de Andreani Corporativo'
		);
	}

	protected function assemble_order_body( array $context, Andreani_Contract $contract ) {
		$destination = $context['destination'];

		$origen = Andreani_Origen::get();

		$origin = array(
			'postal_code' => Andreani_Postcode::normalize( $this->cp_origen ),
		);

		$pin = Andreani_Origen::get_pin();
		if ( '' !== $pin ) {
			$origin['code_branch'] = $pin;
		}

		$mapa_direccion = array(
			'street'   => 'calle',
			'number'   => 'numero',
			'floor'    => 'piso',
			'city'     => 'localidad',
			'province' => 'provincia',
		);

		foreach ( $mapa_direccion as $clave_api => $campo ) {
			$valor = trim( (string) $origen[ $campo ] );
			if ( '' !== $valor ) {
				$origin[ $clave_api ] = $valor;
			}
		}

		$body = array(
			'price_shipment' => $context['shipping_total'],
			'remito'         => $context['remito'],
			'origin'         => $origin,
			'destination'    => array(
				'street'      => $destination['street'],
				'number'      => $destination['number'],
				'floor'       => ! empty( $destination['address_2'] ) ? $destination['address_2'] : '',
				'postal_code' => $destination['postcode'],
				'locality'    => $destination['city'],
				'province'    => $destination['state'],
				'code_branch' => $context['code_branch'],
			),
			'recipient'      => $context['recipient'],
			'products'       => $context['products'],
			'userAO'         => array_merge(
				array(
					'clientId' => $this->info_cliente['clienteId'],
				),
				Andreani_Utils::extract_name_parts( $this->info_cliente['name'] ),
				array(
					'dni'         => $this->info_cliente['cuit'],
					'email'       => $this->info_cliente['email'],
					'phoneNumber' => $this->info_cliente['phoneNumber'] ? $this->info_cliente['phoneNumber'] : '-',
				)
			),
			'contract'       => array(
				'contract_number' => $contract->get_number(),
				'shipping_type'   => $contract->get_shipping_type(),
				'delivery_mode'   => $contract->get_delivery_mode(),
				'description'     => $contract->get_template(),
			),
		);

		$remitente = trim( (string) $origen['remitente_nombre'] );
		if ( '' !== $remitente ) {
			$body['sender'] = array( 'name' => $remitente );
		}

		return $body;
	}

	/**
	 * Filtra contratos a encomienda/paquetes/bigger y normaliza nombres de modos de entrega.
	 *
	 * @param array $data Datos del cliente desde API
	 */
	protected function save_client_info( $data ) {
		if ( isset( $data['contratos'] ) && is_array( $data['contratos'] ) ) {
			$data['contratos'] = array_values(
				array_filter( $data['contratos'], function( $contrato ) {
					$tipo_envio = isset( $contrato['tipoDeEnvioNombre'] )
						? strtolower( $contrato['tipoDeEnvioNombre'] )
						: '';
					return in_array( $tipo_envio, array( 'encomienda', 'paquetes', 'bigger' ), true );
				} )
			);

			foreach ( $data['contratos'] as &$contrato ) {
				$tipo_envio = isset( $contrato['tipoDeEnvioNombre'] ) ? strtolower( $contrato['tipoDeEnvioNombre'] ) : '';
				if ( 'bigger' === $tipo_envio ) {
					$contrato['modoDeEntregaNombre'] = 'bigger';
				} elseif ( isset( $contrato['modoDeEntregaNombre'] ) ) {
					$contrato['modoDeEntregaNombre'] = $this->normalize_modo_entrega_nombre(
						$contrato['modoDeEntregaNombre']
					);
				}
			}
		}

		update_option( self::CLIENT_INFO_OPTION, $data );
	}

	/**
	 * Convierte nombres de la API ('A domicilio', 'A sucursal', etc.) a nombres internos del plugin.
	 *
	 * @param string $modo_entrega_nombre Nombre desde la API
	 * @return string Nombre normalizado
	 */
	private function normalize_modo_entrega_nombre( $modo_entrega_nombre ) {
		$delivery_mode_mapping = array(
			'A domicilio' => 'estándar',
			'A sucursal'  => 'sucursal',
			'Llega hoy'   => 'llega hoy',
		);

		if ( isset( $delivery_mode_mapping[ $modo_entrega_nombre ] ) ) {
			return $delivery_mode_mapping[ $modo_entrega_nombre ];
		}

		return strtolower( $modo_entrega_nombre );
	}
}
