<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Api_Utils {

	public static function validate_package( $package ) {
		if ( ! isset( $package['contents'] ) || empty( $package['contents'] ) ) {
			return new WP_Error(
				'andreani_empty_package',
				__( 'El carrito no tiene productos para cotizar.', 'andreani-shipping' )
			);
		}

		$has_shipping_products = false;

		foreach ( $package['contents'] as $item_id => $values ) {
			if ( ! $values['data']->needs_shipping() ) {
				continue;
			}

			$has_shipping_products = true;
		}

		if ( ! $has_shipping_products ) {
			return new WP_Error(
				'andreani_no_shipping_products',
				__( 'Ningún producto del carrito requiere envío.', 'andreani-shipping' )
			);
		}

		return true;
	}

	public static function get_delivery_mode_from_shipping_method( $shipping_method, $prefix ) {
		$delivery_mode = str_replace( $prefix, '', $shipping_method );
		$delivery_mode = str_replace( '-', ' ', $delivery_mode );
		return $delivery_mode;
	}

	public static function get_order_shipping_method( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return '';
		}

		foreach ( $order->get_items( 'shipping' ) as $item ) {
			if ( ! $item instanceof WC_Order_Item_Shipping ) {
				continue;
			}
			if ( ANDREANI_SHIPPING_METHOD_ID !== $item->get_method_id() ) {
				continue;
			}
			$rate_id = $item->get_meta( '_andreani_rate_id', true );
			if ( ! empty( $rate_id ) ) {
				return $rate_id;
			}
		}

		return (string) $order->get_meta( '_chosen_shipping', true );
	}

	public static function validate_order_for_shipping( $order_id, $shipping_prefix, $client_type = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] No encontrada en WooCommerce", 'error' );
			return false;
		}

		$chosen_shipping = self::get_order_shipping_method( $order );
		if ( strpos( $chosen_shipping, $shipping_prefix ) === false ) {
			return false;
		}

		return true;
	}
}
