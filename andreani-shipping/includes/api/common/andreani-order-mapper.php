<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Order_Mapper {

	public static function get_destination_data( $order ) {
		$order_id = $order->get_id();

		$use_shipping = ! empty( $order->get_shipping_address_1() ) && (
			$order->get_shipping_address_1() !== $order->get_billing_address_1() ||
			$order->get_shipping_postcode() !== $order->get_billing_postcode()
		);

		$destination_address    = $use_shipping ? $order->get_shipping_address_1() : $order->get_billing_address_1();
		$destination_address_2  = $use_shipping ? $order->get_shipping_address_2() : $order->get_billing_address_2();
		$destination_postcode   = $use_shipping ? $order->get_shipping_postcode() : $order->get_billing_postcode();
		$destination_city       = $use_shipping ? $order->get_shipping_city() : $order->get_billing_city();
		$destination_state_code = $use_shipping ? $order->get_shipping_state() : $order->get_billing_state();

		if ( empty( $destination_address ) || empty( $destination_postcode ) || empty( $destination_city ) ) {
			$missing = array();
			if ( empty( $destination_address ) ) $missing[] = 'dirección';
			if ( empty( $destination_postcode ) ) $missing[] = 'código postal';
			if ( empty( $destination_city ) ) $missing[] = 'ciudad';
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Datos de destino incompletos - Faltan: " . implode( ', ', $missing ), 'error' );
			return new WP_Error(
				'andreani_destination_incomplete',
				sprintf( 'Datos de destino incompletos: faltan %s', implode( ', ', $missing ) ),
				array(
					'missing'      => $missing,
					'use_shipping' => $use_shipping,
				)
			);
		}

		$parsed_address = self::parse_address_street_number( $destination_address );

		$ar_states              = WC()->countries->get_states( 'AR' );
		$destination_state_name = ! empty( $destination_state_code ) && isset( $ar_states[ $destination_state_code ] )
			? $ar_states[ $destination_state_code ]
			: '';

		return array(
			'use_shipping' => $use_shipping,
			'address'      => $destination_address,
			'street'       => $parsed_address['street'],
			'number'       => $parsed_address['number'],
			'address_2'    => $destination_address_2,
			'postcode'     => Andreani_Postcode::normalize( $destination_postcode ),
			'city'         => $destination_city,
			'state'        => $destination_state_name,
		);
	}

	public static function get_recipient_data( $order, $use_shipping = false ) {
		$recipient_name     = $use_shipping ? $order->get_shipping_first_name() : $order->get_billing_first_name();
		$recipient_lastname = $use_shipping ? $order->get_shipping_last_name() : $order->get_billing_last_name();

		$dni = $use_shipping ? $order->get_meta( '_shipping_dni' ) : '';
		if ( empty( $dni ) ) {
			$dni = $order->get_meta( '_billing_dni' );
		}
		if ( empty( $dni ) ) {
			$dni = self::get_blocks_dni( $order, $use_shipping );
		}

		$phone = '';
		if ( $use_shipping && method_exists( $order, 'get_shipping_phone' ) ) {
			$phone = (string) $order->get_shipping_phone();
		}
		if ( '' === $phone ) {
			$phone = (string) $order->get_billing_phone();
		}

		return array(
			'name'         => $recipient_name,
			'last_name'    => $recipient_lastname,
			'phone_number' => $phone,
			'dni'          => $dni,
			'email'        => $order->get_billing_email(),
		);
	}

	private static function get_blocks_dni( $order, $use_shipping ) {
		$groups = $use_shipping ? array( 'shipping', 'billing' ) : array( 'billing', 'shipping' );

		foreach ( $groups as $group ) {
			$dni = self::get_blocks_field( $order, 'andreani/dni', $group );
			if ( '' !== $dni ) {
				return $dni;
			}
		}

		return '';
	}

	private static function get_blocks_field( $order, $key, $group ) {
		$package = '\Automattic\WooCommerce\Blocks\Package';
		$service = '\Automattic\WooCommerce\Blocks\Domain\Services\CheckoutFields';

		if ( class_exists( $package ) && class_exists( $service ) && method_exists( $package, 'container' ) ) {
			try {
				$fields = call_user_func( array( $package, 'container' ) )->get( $service );
				if ( is_object( $fields ) && method_exists( $fields, 'get_field_from_object' ) ) {
					$value = $fields->get_field_from_object( $key, $order, $group );
					if ( is_scalar( $value ) && '' !== trim( (string) $value ) ) {
						return trim( (string) $value );
					}
				}
			} catch ( \Throwable $e ) {
				Andreani_Utils::andreani_log( '[ORDEN] No se pudo leer el campo ' . $key . ' del checkout por bloques: ' . $e->getMessage(), 'warning' );
			}
		}

		return trim( (string) $order->get_meta( '_wc_' . $group . '/' . $key, true ) );
	}

	public static function get_branch_code_for_order( $order_id ) {
		$order       = wc_get_order( $order_id );
		$code_branch = $order ? $order->get_meta( '_shipping_branch_code', true ) : '';

		if ( empty( $code_branch ) ) {
			$code_branch = Andreani_Utils::get_session_data( 'codigo_sucursal', '' );
		}

		return $code_branch;
	}

	public static function prepare_products_for_orden( $order, $mostrar_sin_decimales = false ) {
		$products           = array();
		$skipped            = array();
		$missing_dimensions = array();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product_id = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
			$product    = wc_get_product( $product_id );
			if ( ! $product ) {
				$skipped[] = array(
					'item_id' => (int) $item_id,
					'name'    => $item->get_name(),
					'reason'  => 'product_not_found',
				);
				continue;
			}
			if ( ! $product->needs_shipping() ) {
				$skipped[] = array(
					'product_id' => (int) $product->get_id(),
					'name'       => $product->get_name(),
					'reason'     => 'needs_shipping_false',
				);
				continue;
			}

			$quantity = $item->get_quantity();
			$width    = self::convert_dimension_to_cm( $product->get_width() ) ?: 1;
			$height   = self::convert_dimension_to_cm( $product->get_height() ) ?: 1;
			$depth    = self::convert_dimension_to_cm( $product->get_length() ) ?: 1;
			$weight   = $product->get_weight() ?: 1;
			$price    = $mostrar_sin_decimales ? round( floatval( $product->get_price() ) ) : floatval( $product->get_price() );

			if ( ! $product->get_weight() ) {
				Andreani_Utils::andreani_log( "[ORDEN] Producto sin peso: \"{$product->get_name()}\" (ID: {$product->get_id()}) - usando peso por defecto", 'warning' );
			}

			$missing_fields = array();
			if ( ! $product->get_weight() ) $missing_fields[] = 'peso';
			if ( ! $product->get_width() )  $missing_fields[] = 'ancho';
			if ( ! $product->get_height() ) $missing_fields[] = 'alto';
			if ( ! $product->get_length() ) $missing_fields[] = 'largo';
			if ( ! empty( $missing_fields ) ) {
				$missing_dimensions[] = array(
					'product_id' => (int) $product->get_id(),
					'name'       => $product->get_name(),
					'missing'    => $missing_fields,
				);
			}

			$bultos_adicionales = self::get_bultos_adicionales( $product_id );

			if ( empty( $bultos_adicionales ) && $product->is_type( 'variation' ) ) {
				$bultos_adicionales = self::get_bultos_adicionales( $product->get_parent_id() );
			}

			$total_bultos    = 1 + count( $bultos_adicionales );
			$price_per_bulto = floatval( $price ) / $total_bultos;

			$products[] = array(
				'price'    => floatval( $price_per_bulto ),
				'quantity' => intval( $quantity ),
				'kgrams'   => floatval( self::convert_weight_to_unit( $weight, 'kg' ) ),
				'width'    => floatval( $width ),
				'depth'    => floatval( $depth ),
				'height'   => floatval( $height ),
			);

			foreach ( $bultos_adicionales as $bulto ) {
				$b_width  = self::convert_dimension_to_cm( $bulto['width'] ) ?: 1;
				$b_height = self::convert_dimension_to_cm( $bulto['height'] ) ?: 1;
				$b_depth  = self::convert_dimension_to_cm( $bulto['length'] ) ?: 1;
				$b_weight = floatval( $bulto['weight'] ) ?: 1;

				$products[] = array(
					'price'    => floatval( $price_per_bulto ),
					'quantity' => intval( $quantity ),
					'kgrams'   => floatval( self::convert_weight_to_unit( $b_weight, 'kg' ) ),
					'width'    => $b_width,
					'depth'    => $b_depth,
					'height'   => $b_height,
				);
			}
		}

		if ( empty( $products ) ) {
			$order_id = $order->get_id();
			Andreani_Utils::andreani_log(
				"[ORDEN #{$order_id}] Sin productos válidos para envío - todos los items no requieren envío o no se pudieron resolver",
				'error'
			);
			return new WP_Error(
				'andreani_no_shippable_products',
				'No hay productos válidos para envío en la orden',
				array(
					'skipped'            => $skipped,
					'missing_dimensions' => $missing_dimensions,
				)
			);
		}

		return $products;
	}

	public static function parse_address_street_number( $address ) {
		$address = trim( $address );

		if ( empty( $address ) ) {
			return array(
				'street' => '',
				'number' => 'S/N',
			);
		}

		if ( preg_match( '/^(.+)\s+(\d{2,}(?:[-\/]?\w*)?)\s*(?:piso|depto|dto|dpto|pb|pa|of|oficina|local|casa|lote|mza?|uf)?.*$/iu', $address, $matches ) ) {
			$street = trim( $matches[1] );
			$number = trim( $matches[2] );

			if ( ! empty( $street ) && ! empty( $number ) ) {
				return array(
					'street' => $street,
					'number' => $number,
				);
			}
		}

		return array(
			'street' => $address,
			'number' => 'S/N',
		);
	}

	public static function get_bultos_adicionales( $product_id ) {
		$json = get_post_meta( $product_id, '_andreani_bultos_adicionales', true );

		if ( empty( $json ) ) {
			return array();
		}

		$bultos = json_decode( $json, true );

		if ( ! is_array( $bultos ) ) {
			return array();
		}

		return $bultos;
	}

	public static function convert_weight_to_unit( $weight, $unit = 'kg' ) {
		$weight  = floatval( $weight );
		$wc_unit = get_option( 'woocommerce_weight_unit', 'kg' );

		if ( $wc_unit === $unit ) {
			return $weight;
		}

		$weight_in_kg = $weight;
		switch ( $wc_unit ) {
			case 'g':
				$weight_in_kg = $weight / 1000;
				break;
			case 'lbs':
				$weight_in_kg = $weight * 0.453592;
				break;
			case 'oz':
				$weight_in_kg = $weight * 0.0283495;
				break;
			case 'kg':
			default:
				$weight_in_kg = $weight;
				break;
		}

		if ( 'gr' === $unit || 'g' === $unit ) {
			return $weight_in_kg * 1000;
		}

		return $weight_in_kg;
	}

	public static function convert_dimension_to_cm( $dimension ) {
		$dimension = floatval( $dimension );
		$wc_unit   = get_option( 'woocommerce_dimension_unit', 'cm' );

		switch ( $wc_unit ) {
			case 'm':
				return $dimension * 100;
			case 'mm':
				return $dimension / 10;
			case 'in':
				return $dimension * 2.54;
			case 'yd':
				return $dimension * 91.44;
			case 'cm':
			default:
				return $dimension;
		}
	}

	public static function convert_cm_to_dimension_unit( $cm ) {
		$cm      = floatval( $cm );
		$wc_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

		switch ( $wc_unit ) {
			case 'm':
				return $cm / 100;
			case 'mm':
				return $cm * 10;
			case 'in':
				return $cm / 2.54;
			case 'yd':
				return $cm / 91.44;
			case 'cm':
			default:
				return $cm;
		}
	}

	public static function convert_kg_to_weight_unit( $kg ) {
		$kg      = floatval( $kg );
		$wc_unit = get_option( 'woocommerce_weight_unit', 'kg' );

		switch ( $wc_unit ) {
			case 'g':
				return $kg * 1000;
			case 'lbs':
				return $kg / 0.453592;
			case 'oz':
				return $kg / 0.0283495;
			case 'kg':
			default:
				return $kg;
		}
	}
}
