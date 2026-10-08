<?php
/**
 * Cajas con las que se arma un pedido, con la misma lógica de bultos del alta.
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Order_Packing {

	const NONCE = 'andreani_order_packing';

	/**
	 * @param array  $lines         Líneas del pedido: product, product_id, name, quantity y url de edición.
	 * @param string $delivery_mode Modo de entrega con el que quedó el envío; vacío si todavía no se definió.
	 * @return array{packages:array,units:int,total:int,missing:array,bigger:bool}
	 */
	public static function build( array $lines, $delivery_mode = '' ) {
		$packages = array();
		$missing  = array();
		$units    = 0;
		$total    = 0;

		foreach ( $lines as $line ) {
			$product  = isset( $line['product'] ) ? $line['product'] : null;
			$quantity = (int) $line['quantity'];

			if ( $quantity <= 0 || ( $product && ! $product->needs_shipping() ) ) {
				continue;
			}

			if ( ! $product || self::lacks_measures( $product ) ) {
				$missing[] = array(
					'id'   => (int) $line['product_id'],
					'name' => (string) $line['name'],
					'url'  => isset( $line['url'] ) ? (string) $line['url'] : '',
				);
				continue;
			}

			foreach ( Andreani_Package_Builder::draw_package_groups_for_product( $product, $quantity ) as $package ) {
				$package['name'] = (string) $line['name'];
				$packages[]      = $package;
				$total          += $package['count'];
			}

			$units += $quantity;
		}

		return array(
			'packages' => $packages,
			'units'    => $units,
			'total'    => $total,
			'missing'  => $missing,
			'bigger'   => self::is_bigger( $packages, $delivery_mode ),
		);
	}

	private static function lacks_measures( $product ) {
		foreach ( array( $product->get_width(), $product->get_height(), $product->get_length() ) as $dimension ) {
			if ( Andreani_Order_Mapper::convert_dimension_to_cm( $dimension ) <= 0 ) {
				return true;
			}
		}

		return Andreani_Order_Mapper::convert_weight_to_unit( $product->get_weight(), 'kg' ) <= 0;
	}

	private static function is_bigger( array $packages, $delivery_mode ) {
		$mode = strtolower( trim( (string) $delivery_mode ) );

		if ( '' !== $mode ) {
			return false !== strpos( $mode, 'bigger' );
		}

		if ( empty( $packages ) ) {
			return false;
		}

		$first  = array_shift( $packages );
		$bultos = array();

		foreach ( $packages as $package ) {
			$bultos[] = array(
				'weight' => $package['kg'] * $package['count'] * 1000,
				'width'  => $package['w'],
				'height' => $package['h'],
				'depth'  => $package['d'],
			);
		}

		$evaluation = Andreani_Product_Bultos::bigger_from_values( $first['kg'] * $first['count'], $first['w'], $first['h'], $first['d'], $bultos, array() );

		return $evaluation['is_bigger'];
	}
}
