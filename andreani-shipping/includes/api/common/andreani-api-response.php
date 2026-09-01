<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Api_Response {

	public static function decode( $response ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$decoded = json_decode( $response, true );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			Andreani_Utils::andreani_log( '[API] Respuesta no es JSON válido: ' . json_last_error_msg() . ' - Respuesta: ' . substr( $response, 0, 200 ), 'error' );
			return new WP_Error( 'invalid_json', 'Respuesta de API no válida' );
		}

		return $decoded;
	}

	public static function process_sucursales( $response ) {
		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[SUCURSALES] Error al obtener listado: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'sucursales_error', 'Error al obtener las sucursales, por favor intente nuevamente.' );
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) || ! isset( $response['response']['data'] ) ) {
			$debug_info = is_array( $response ) ? array_keys( $response ) : gettype( $response );
			Andreani_Utils::andreani_log( '[SUCURSALES] Respuesta con formato inesperado - Estructura: ' . wp_json_encode( $debug_info ), 'error' );
			return new WP_Error( 'sucursales_invalid_response', 'Respuesta de sucursales no válida.' );
		}

		$branches = $response['response']['data'];
		if ( empty( $branches ) ) {
			Andreani_Utils::andreani_log( '[SUCURSALES] La API retornó lista de sucursales vacía', 'warning' );
			return new WP_Error( 'sucursales_no_branches', 'No se encontraron sucursales.' );
		}

		if ( ! is_array( $branches ) ) {
			Andreani_Utils::andreani_log( '[SUCURSALES] Formato de datos inválido - Tipo: ' . gettype( $branches ), 'error' );
			return new WP_Error( 'invalid_response', 'Respuesta de API no es un array válido' );
		}

		$options = array();
		$details = array();

		foreach ( $branches as $sucursal ) {
			$key = isset( $sucursal['Codigo'] ) ? $sucursal['Codigo'] : '';
			if ( '' === $key ) {
				continue;
			}

			$descripcion = isset( $sucursal['Descripcion'] ) ? $sucursal['Descripcion'] : '';

			$options[ $key ] = $descripcion;

			$details[ $key ] = array_merge(
				array(
					'descripcion' => $descripcion,
					'direccion'   => self::format_branch_address( $sucursal ),
				),
				self::extract_branch_extras( $sucursal )
			);
		}

		if ( empty( $options ) ) {
			Andreani_Utils::andreani_log( '[SUCURSALES] No se encontraron sucursales disponibles para el código postal', 'warning' );
			return new WP_Error( 'sucursales_no_branches', 'No se encontraron sucursales.' );
		}

		Andreani_Utils::andreani_log( '[SUCURSALES] ' . count( $options ) . ' sucursales encontradas', 'debug' );

		return array(
			'options' => $options,
			'details' => $details,
		);
	}

	/**
	 * Normaliza la sucursal que Andreani asigna por defecto para un código postal.
	 * Tolera que venga suelta, dentro de `data` o como primer elemento de una lista.
	 *
	 * @param array|WP_Error $response Respuesta ya decodificada.
	 * @return array|WP_Error { codigo, descripcion, direccion, ... } o WP_Error.
	 */
	public static function process_origen_predeterminado( $response ) {
		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[ORIGEN] Error al obtener la sucursal predeterminada: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'origen_default_error', 'No se pudo obtener la sucursal de origen predeterminada.' );
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) ) {
			return new WP_Error( 'origen_default_invalid_response', 'Respuesta de sucursal predeterminada no válida.' );
		}

		$branch = $response['response'];

		if ( is_array( $branch ) && isset( $branch['data'] ) ) {
			$branch = $branch['data'];
		}

		// La API la devuelve envuelta: response.data.sucursal.
		if ( is_array( $branch ) && isset( $branch['sucursal'] ) && is_array( $branch['sucursal'] ) ) {
			$branch = $branch['sucursal'];
		}

		if ( is_array( $branch ) && isset( $branch[0] ) ) {
			$branch = $branch[0];
		}

		if ( ! is_array( $branch ) || empty( $branch ) ) {
			return new WP_Error( 'origen_default_empty', 'Andreani no devolvió una sucursal de origen predeterminada.' );
		}

		$codigo = self::pick_field( $branch, array( 'Codigo', 'codigo', 'code' ) );

		if ( '' === $codigo ) {
			return new WP_Error( 'origen_default_empty', 'Andreani no devolvió una sucursal de origen predeterminada.' );
		}

		return array_merge(
			array(
				'codigo'      => $codigo,
				'descripcion' => self::pick_field( $branch, array( 'Descripcion', 'descripcion', 'description' ) ),
				'direccion'   => self::format_branch_address( $branch ),
			),
			self::extract_branch_extras( $branch )
		);
	}

	/**
	 * @param array $branch Sucursal cruda de la API.
	 * @return string Dirección legible (calle número, localidad, CP).
	 */
	private static function format_branch_address( $branch ) {
		$direccion = isset( $branch['Direccion'] ) && is_array( $branch['Direccion'] )
			? $branch['Direccion']
			: ( isset( $branch['direccion'] ) && is_array( $branch['direccion'] ) ? $branch['direccion'] : array() );

		$calle     = self::pick_field( $direccion, array( 'Calle', 'calle' ) );
		$numero    = self::pick_field( $direccion, array( 'Numero', 'numero' ) );
		$localidad = self::pick_field( $direccion, array( 'Localidad', 'localidad' ) );
		$provincia = self::pick_field( $direccion, array( 'Provincia', 'provincia' ) );
		$cp        = self::pick_field( $direccion, array( 'CodigoPostal', 'codigoPostal' ) );

		$address_parts = array();
		if ( '' !== $calle && '' !== $numero ) {
			$address_parts[] = $calle . ' ' . $numero;
		} elseif ( '' !== $calle ) {
			$address_parts[] = $calle;
		}

		if ( '' !== $localidad ) {
			$address_parts[] = $localidad;
		} elseif ( '' !== $provincia ) {
			$address_parts[] = $provincia;
		}

		if ( '' !== $cp ) {
			$address_parts[] = 'CP ' . $cp;
		}

		return implode( ', ', $address_parts );
	}

	/**
	 * Campos que el back sumó para el origen configurable. Retrocompatible:
	 * si la respuesta todavía no los trae, quedan vacíos.
	 *
	 * @param array $branch Sucursal cruda de la API.
	 * @return array
	 */
	private static function extract_branch_extras( $branch ) {
		$coordenadas = array();
		foreach ( array( 'coordenadas', 'Coordenadas' ) as $clave ) {
			if ( isset( $branch[ $clave ] ) && is_array( $branch[ $clave ] ) ) {
				$coordenadas = $branch[ $clave ];
				break;
			}
		}

		return array(
			'id_api'      => self::pick_field( $branch, array( 'idApi', 'IdApi', 'id_api' ) ),
			'tipo'        => self::pick_field( $branch, array( 'tipo', 'Tipo' ) ),
			'horario'     => self::pick_field( $branch, array( 'horarioDeAtencion', 'HorarioDeAtencion' ) ),
			'coordenadas' => $coordenadas,
		);
	}

	/**
	 * @param array $data  Estructura donde buscar.
	 * @param array $keys  Nombres aceptados, en orden de preferencia.
	 * @return string Valor escalar encontrado, o ''.
	 */
	private static function pick_field( $data, array $keys ) {
		if ( ! is_array( $data ) ) {
			return '';
		}

		foreach ( $keys as $key ) {
			if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
				return (string) $data[ $key ];
			}
		}

		return '';
	}

	public static function process_cotizacion( $response ) {
		if ( is_wp_error( $response ) ) {
			Andreani_Utils::andreani_log( '[COTIZACION] Error al obtener tarifas: ' . $response->get_error_message(), 'error' );
			return new WP_Error( 'cotizacion_error', 'Error al obtener la cotizacion, por favor intente nuevamente.' );
		}

		if ( ! is_array( $response ) || ! isset( $response['response'] ) || ! isset( $response['response']['rates'] ) ) {
			Andreani_Utils::andreani_log( '[COTIZACION] Respuesta con formato inválido de la API', 'error' );
			return new WP_Error( 'cotizacion_invalid_response', 'Respuesta de cotización no válida.' );
		}

		$rates = isset( $response['response']['rates'] ) ? $response['response']['rates'] : array();
		if ( empty( $rates ) ) {
			Andreani_Utils::andreani_log( '[COTIZACION] No hay tarifas disponibles para esta combinación origen/destino', 'warning' );
			return new WP_Error( 'cotizacion_no_rates', 'No se encontraron tarifas para la cotización.' );
		}

		$rate_summary = array_map( function( $r ) { return $r['code'] . ': $' . $r['total']; }, $rates );
		Andreani_Utils::andreani_log( '[COTIZACION] Tarifas obtenidas: ' . implode( ', ', $rate_summary ), 'info' );

		return $rates;
	}

	public static function build_shipping_rates( $rates, $prefix, $cart_total = 0 ) {
		$shipping_rates = array();
		$codes_used     = array();

		$config_json     = Andreani_Settings_Service::get( 'config_por_modo', '{}' );
		$config_por_modo = json_decode( $config_json, true ) ?: array();

		foreach ( $rates as $rate ) {
			$code = $rate['code'];

			if ( in_array( $code, $codes_used, true ) ) {
				continue;
			}

			$code_key = self::normalize_modo_key( $code );
			if ( '' !== $code_key && isset( $config_por_modo[ $code_key ] ) ) {
				$modo_config = $config_por_modo[ $code_key ];
			} elseif ( isset( $config_por_modo[ $code ] ) ) {
				$modo_config = $config_por_modo[ $code ];
			} else {
				$modo_config = array();
			}
			$is_enabled = isset( $modo_config['enabled'] ) ? $modo_config['enabled'] : true;

			if ( ! $is_enabled ) {
				continue;
			}

			$envio_gratis       = isset( $modo_config['envio_gratis'] ) ? $modo_config['envio_gratis'] : false;
			$envio_gratis_monto = isset( $modo_config['envio_gratis_monto'] ) ? floatval( $modo_config['envio_gratis_monto'] ) : 0;

			$aplicar_gratis = false;
			if ( $envio_gratis ) {
				$aplicar_gratis = ( $envio_gratis_monto <= 0 ) || ( $cart_total >= $envio_gratis_monto );
			}

			$cost = $aplicar_gratis ? 0 : $rate['total'];

			$label = 'Andreani (' . $rate['code'] . ')';

			if ( $aplicar_gratis ) {
				$label .= ' - ¡Envío gratis!';
			}

			$shipping_rates[] = array(
				'id'       => $prefix . $code,
				'label'    => $label,
				'cost'     => $cost,
				'calc_tax' => 'per_item',
				'sort'     => 0,
			);
			$codes_used[] = $code;
		}

		return $shipping_rates;
	}

	public static function normalize_modo_key( $modo_nombre ) {
		if ( ! is_string( $modo_nombre ) || '' === $modo_nombre ) {
			return '';
		}
		return sanitize_title( remove_accents( $modo_nombre ) );
	}

	public static function prepare_products_for_cotizacion( $package, $mostrar_sin_decimales = false ) {
		$products = array();

		foreach ( $package['contents'] as $values ) {
			$product_data = $values['data'];

			if ( ! $product_data->needs_shipping() ) {
				continue;
			}

			$weight     = floatval( $product_data->get_weight() );
			$raw_width  = Andreani_Order_Mapper::convert_dimension_to_cm( $product_data->get_width() );
			$raw_height = Andreani_Order_Mapper::convert_dimension_to_cm( $product_data->get_height() );
			$raw_depth  = Andreani_Order_Mapper::convert_dimension_to_cm( $product_data->get_length() );

			if ( $weight <= 0 || $raw_width <= 0 || $raw_height <= 0 || $raw_depth <= 0 ) {
				$faltantes = array();
				if ( $weight <= 0 ) {
					$faltantes[] = 'peso';
				}
				if ( $raw_width <= 0 ) {
					$faltantes[] = 'ancho';
				}
				if ( $raw_height <= 0 ) {
					$faltantes[] = 'alto';
				}
				if ( $raw_depth <= 0 ) {
					$faltantes[] = 'largo';
				}

				$sku = $product_data->get_sku();

				Andreani_Utils::andreani_log(
					sprintf(
						'[COTIZACION] No se cotiza el carrito: al producto "%s"%s le falta %s. Cargalos en el producto para que Andreani pueda cotizar.',
						$product_data->get_name(),
						$sku ? ' (SKU ' . $sku . ')' : '',
						implode( ', ', $faltantes )
					),
					'error'
				);

				return array();
			}

			$width  = max( 1, (int) round( $raw_width ) );
			$height = max( 1, (int) round( $raw_height ) );
			$depth  = max( 1, (int) round( $raw_depth ) );

			$price = $mostrar_sin_decimales ? round( floatval( $product_data->get_price() ) ) : floatval( $product_data->get_price() );

			$product_id         = $product_data->get_id();
			$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product_id );

			if ( empty( $bultos_adicionales ) && $product_data->is_type( 'variation' ) ) {
				$bultos_adicionales = Andreani_Order_Mapper::get_bultos_adicionales( $product_data->get_parent_id() );
			}

			$total_bultos    = 1 + count( $bultos_adicionales );
			$price_per_bulto = (int) ( $price / $total_bultos );

			$products[] = array(
				'quantity'   => (int) $values['quantity'],
				'price'      => $price_per_bulto,
				'dimensions' => array(
					'width'  => $width,
					'height' => $height,
					'depth'  => $depth,
					'grams'  => Andreani_Order_Mapper::convert_weight_to_unit( $product_data->get_weight(), 'gr' ),
				),
			);

			foreach ( $bultos_adicionales as $bulto ) {
				$b_raw_width  = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['width'] );
				$b_raw_height = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['height'] );
				$b_raw_depth  = Andreani_Order_Mapper::convert_dimension_to_cm( $bulto['length'] );
				$b_weight     = floatval( $bulto['weight'] );

				if ( $b_raw_width <= 0 || $b_raw_height <= 0 || $b_raw_depth <= 0 || $b_weight <= 0 ) {
					continue;
				}

				$b_width  = max( 1, (int) round( $b_raw_width ) );
				$b_height = max( 1, (int) round( $b_raw_height ) );
				$b_depth  = max( 1, (int) round( $b_raw_depth ) );

				$products[] = array(
					'quantity'   => (int) $values['quantity'],
					'price'      => $price_per_bulto,
					'dimensions' => array(
						'width'  => $b_width,
						'height' => $b_height,
						'depth'  => $b_depth,
						'grams'  => Andreani_Order_Mapper::convert_weight_to_unit( $b_weight, 'gr' ),
					),
				);
			}
		}

		return $products;
	}
}
