<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

trait Andreani_Ajax_Helpers_Trait {

	protected function user_can_manage_orders() {
		return current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' );
	}

	protected function get_order_id_from_request() {
		return isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
	}

	protected function resolve_type_info( $order ) {
		$type_info = Andreani_Client_Type::from_order( $order );
		if ( $type_info ) {
			return $type_info;
		}
		return Andreani_Client_Type::from_id( Andreani_Api_Manager::get_client_type() );
	}

	protected function fix_shipping_method_prefix( $order ) {
		$chosen = Andreani_Api_Utils::get_order_shipping_method( $order );
		if ( empty( $chosen ) || strpos( $chosen, 'andreani' ) === false ) {
			return;
		}

		// El retry se ejecuta SIEMPRE contra el cliente activo del plugin, no contra el client_type histórico de la orden.
		$type_info = Andreani_Client_Type::from_id( Andreani_Api_Manager::get_client_type() );
		if ( ! $type_info ) {
			return;
		}

		$current_prefix = $type_info->prefix();
		$old_prefixes   = Andreani_Client_Type::all_prefixes();

		foreach ( $old_prefixes as $old_prefix ) {
			if ( $old_prefix === $current_prefix ) {
				continue;
			}
			if ( strpos( $chosen, $old_prefix ) === 0 ) {
				$delivery_mode = str_replace( $old_prefix, '', $chosen );
				$new_chosen    = $current_prefix . $delivery_mode;

				$order->update_meta_data( '_chosen_shipping', $new_chosen );

				foreach ( $order->get_items( 'shipping' ) as $item ) {
					if ( $item instanceof WC_Order_Item_Shipping && ANDREANI_SHIPPING_METHOD_ID === $item->get_method_id() ) {
						$item->update_meta_data( '_andreani_rate_id', $new_chosen );
						$item->save();
					}
				}

				$order->save();
				Andreani_Utils::andreani_log(
					"[ORDEN #{$order->get_id()}] Corregido prefix de envio: {$chosen} -> {$new_chosen}",
					'info'
				);
				return;
			}
		}
	}

	protected function get_row_html( $order_id ) {
		$list_table = new Andreani_Shipments_List();
		return $list_table->get_row_html( $order_id );
	}
}
