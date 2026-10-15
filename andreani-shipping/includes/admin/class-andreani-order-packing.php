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
	 * @return array{packages:array,units:int,items:array,total:int,missing:array,bigger:bool,store_box:?array}
	 */
	public static function build( array $lines, $delivery_mode = '' ) {
		$groups  = array();
		$names   = array();
		$missing = array();
		$units   = 0;
		$counted = array();
		$items   = array();

		foreach ( $lines as $index => $line ) {
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

			$drawn = Andreani_Package_Builder::annotate_groups( Andreani_Package_Builder::draw_package_groups_for_product( $product, $quantity ), $index, $product, $quantity );

			foreach ( $drawn as $package ) {
				$package['name']    = (string) $line['name'];
				$groups[ $index ][] = $package;
			}

			$item = array( 'name' => (string) $line['name'], 'quantity' => $quantity );

			if ( ! empty( $drawn[0]['assigned'] ) ) {
				$item['assigned'] = $drawn[0]['assigned'];
			}

			$names[ $index ]   = (string) $line['name'];
			$units            += $quantity;
			$counted[ $index ] = array( 'product' => $product, 'quantity' => $quantity );
			$items[]           = $item;
		}

		$decision = Andreani_Package_Builder::cart_decision( $counted );
		$packages = Andreani_Package_Builder::with_bundle( $groups, $names, $decision );
		$total    = array_sum( array_column( $packages, 'count' ) );

		return array(
			'packages' => $packages,
			'units'    => $units,
			'items'    => $items,
			'total'    => $total,
			'missing'  => $missing,
			'bigger'   => self::is_bigger( $packages, $delivery_mode, $decision['force_bigger'] ),
			'store_box' => Andreani_Package_Builder::store_box_of( $decision ),
		);
	}

	private static function lacks_measures( $product ) {
		$measures = Andreani_Package_Builder::unit_measures( $product );

		return $measures['width'] <= 0 || $measures['height'] <= 0 || $measures['depth'] <= 0 || $measures['weight_kg'] <= 0;
	}

	private static function is_bigger( array $packages, $delivery_mode, $force = false ) {
		$mode = strtolower( trim( (string) $delivery_mode ) );

		if ( '' !== $mode ) {
			return false !== strpos( $mode, 'bigger' );
		}

		if ( $force ) {
			return true;
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
