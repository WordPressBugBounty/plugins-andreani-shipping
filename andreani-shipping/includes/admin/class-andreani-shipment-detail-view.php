<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Shipment_Detail_View {

	/**
	 * Render del detalle expandido de un envío.
	 *
	 * @param int        $order_id ID de la orden WC.
	 * @param array|null $hydrated_item Item ya hidratado por Andreani_Shipments_Hydrator
	 *                                  (incluye keys `source`, `api_status`, `last_error`,
	 *                                  `api_updated_at`, etc.). Si es null, todo viene del WC
	 *                                  order — comportamiento legacy preservado.
	 */
	public static function render( $order_id, $hydrated_item = null ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return '';
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return '';
		}

		$source        = is_array( $hydrated_item ) && isset( $hydrated_item['source'] )
			? (string) $hydrated_item['source']
			: 'wc';
		$is_from_api   = 'api' === $source;
		$api_updated   = is_array( $hydrated_item ) && ! empty( $hydrated_item['api_updated_at'] )
			? (string) $hydrated_item['api_updated_at']
			: '';

		$data             = Andreani_Shipments_List::get_order_shipment_data( $order );
		$client_type      = $data['client_type'];
		$type_info        = Andreani_Client_Type::from_id( $client_type );
		$display_tracking = $data['display_tracking'];
		$delivery_mode    = $data['delivery_mode'];
		$status           = $data['andreani_status'];

		$customer_name  = $order->get_formatted_billing_full_name();
		$dni            = $order->get_meta( '_billing_dni', true );
		$order_total    = (float) $order->get_total();
		$header_city    = $order->get_shipping_city();
		if ( '' === $header_city ) {
			$header_city = $order->get_billing_city();
		}
		$header_postcode = $order->get_shipping_postcode();
		if ( '' === $header_postcode ) {
			$header_postcode = $order->get_billing_postcode();
		}

		$cp_origen     = '';
		if ( class_exists( 'Andreani_Settings_Service' ) ) {
			$cp_origen = (string) Andreani_Settings_Service::get( 'cp_origen', '' );
		}
		$store_address = trim( get_option( 'woocommerce_store_address', '' ) . ' ' . get_option( 'woocommerce_store_address_2', '' ) );
		$store_city    = (string) get_option( 'woocommerce_store_city', '' );
		$origen_parts  = array_filter( array(
			$store_address !== '' ? $store_address : null,
			$store_city !== '' ? $store_city : null,
		) );
		$origen_text = ! empty( $origen_parts ) ? implode( ', ', $origen_parts ) : '';
		if ( '' !== $cp_origen ) {
			$origen_text = $origen_text !== ''
				? sprintf( '%s — CP %s', $origen_text, $cp_origen )
				: 'CP ' . $cp_origen;
		}
		if ( '' === $origen_text ) {
			$origen_text = '—';
		}

		$phone = method_exists( $order, 'get_shipping_phone' ) ? $order->get_shipping_phone() : '';
		if ( empty( $phone ) ) {
			$phone = $order->get_billing_phone();
		}
		$email = $order->get_billing_email();

		$weight_unit  = (string) get_option( 'woocommerce_weight_unit', 'kg' );
		$total_weight = 0.0;
		$dim_unit     = (string) get_option( 'woocommerce_dimension_unit', 'cm' );
		$items_data   = array();

		foreach ( $order->get_items() as $item ) {
			$product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
			if ( ! $product ) {
				continue;
			}
			$qty    = (int) $item->get_quantity();
			$weight = (float) $product->get_weight();
			if ( $weight > 0 && $qty > 0 ) {
				$total_weight += $weight * $qty;
			}

			$l = (float) $product->get_length();
			$w = (float) $product->get_width();
			$h = (float) $product->get_height();
			$dims = ( $l > 0 && $w > 0 && $h > 0 )
				? sprintf( '%s×%s×%s %s', self::format_number( $l ), self::format_number( $w ), self::format_number( $h ), $dim_unit )
				: '';

			$line_total = (float) $item->get_total();
			$unit_price = $qty > 0 ? $line_total / $qty : $line_total;

			$items_data[] = array(
				'qty'        => $qty,
				'name'       => $item->get_name(),
				'dims'       => $dims,
				'unit_price' => $unit_price,
			);
		}

		$dimensions_str = '—';
		$first_with_dims_index = null;
		foreach ( $items_data as $idx => $d ) {
			if ( '' !== $d['dims'] ) {
				$first_with_dims_index = $idx;
				break;
			}
		}
		if ( null !== $first_with_dims_index ) {
			$first = $items_data[ $first_with_dims_index ];
			$dimensions_str = $first['dims'];
			if ( $first['qty'] > 1 ) {
				$dimensions_str .= ' ×' . $first['qty'];
			}
			$other_items_count = count( $items_data ) - 1;
			if ( $other_items_count > 0 ) {
				$dimensions_str .= sprintf(
					/* translators: %d: cantidad de productos adicionales en el envío */
					_n( ' + %d más', ' + %d más', $other_items_count, 'andreani-shipping' ),
					$other_items_count
				);
			}
		}

		if ( $total_weight > 0 ) {
			$weight_in_grams = ( 'kg' === strtolower( $weight_unit ) )
				? $total_weight * 1000
				: $total_weight;
			$weight_str = sprintf( '%sg', number_format( $weight_in_grams, 0, ',', '.' ) );
		} else {
			$weight_str = '—';
		}

		$valor_declarado_raw = (float) $order->get_subtotal();

		$ship_addr1 = $order->get_shipping_address_1();
		if ( empty( $ship_addr1 ) ) {
			$ship_addr1 = $order->get_billing_address_1();
			$addr_parts = array_filter( array(
				trim( $ship_addr1 . ' ' . $order->get_billing_address_2() ),
				$order->get_billing_city(),
				$order->get_billing_state(),
				$order->get_billing_postcode(),
				$order->get_billing_country(),
			) );
		} else {
			$addr_parts = array_filter( array(
				trim( $ship_addr1 . ' ' . $order->get_shipping_address_2() ),
				$order->get_shipping_city(),
				$order->get_shipping_state(),
				$order->get_shipping_postcode(),
				$order->get_shipping_country(),
			) );
		}
		$destino_full = ! empty( $addr_parts ) ? implode( ', ', $addr_parts ) : '—';

		$sucursal_nombre           = (string) $order->get_meta( '_andreani_sucursal_nombre', true );
		$sucursal_direccion        = (string) $order->get_meta( '_andreani_sucursal_direccion', true );
		$sucursal_origen_nombre    = (string) $order->get_meta( '_order_andreani_sucursal_origen_nombre', true );
		$sucursal_origen_direccion = (string) $order->get_meta( '_order_andreani_sucursal_origen_direccion', true );

		$numero_interno_display = is_array( $hydrated_item ) && ! empty( $hydrated_item['internal_number'] )
			? (string) $hydrated_item['internal_number']
			: (string) $order->get_meta( '_order_andreani_numero_interno', true );
		if ( '' === $numero_interno_display && $type_info && $type_info->can( 'id_orden_display' ) && ! empty( $data['pedido_id'] ) ) {
			$numero_interno_display = '#' . $data['order_number'] . '-' . $data['pedido_id'];
		}

		$contract_number = (string) $order->get_meta( '_order_andreani_contract_number', true );
		if ( '' === $contract_number && ! empty( $delivery_mode ) && $type_info ) {
			$info_data = get_option( $type_info->info_option_name(), array() );
			if ( ! empty( $info_data['contratos'] ) && is_array( $info_data['contratos'] ) ) {
				foreach ( $info_data['contratos'] as $contrato ) {
					if ( isset( $contrato['modoDeEntregaNombre'] ) && $contrato['modoDeEntregaNombre'] === $delivery_mode ) {
						$contract_number = isset( $contrato['numeroDeContrato'] ) ? (string) $contrato['numeroDeContrato'] : '';
						break;
					}
				}
			}
		}

		$servicio_label = ! empty( $delivery_mode ) ? ucfirst( $delivery_mode ) : '—';
		$tipo_label     = $type_info ? $type_info->label() : 'Pyme';
		$tipo_class     = $type_info ? $type_info->display( 'badge_css_suffix' ) : 'pyme';

		$andreani_id_display = '';
		if ( $type_info && $type_info->can( 'id_orden_display' ) && ! empty( $data['pedido_id'] ) ) {
			$andreani_id_display = $numero_interno_display;
		} elseif ( ! empty( $display_tracking ) ) {
			$andreani_id_display = (string) $display_tracking;
		}

		$shipping_status  = ( is_array( $hydrated_item ) && ! empty( $hydrated_item['shipping_status'] ) )
			? (string) $hydrated_item['shipping_status']
			: Andreani_Shipments_List::compute_shipping_status( $order, (bool) $data['created'], (bool) $data['shipped'] );
		$has_payment_step = $type_info ? $type_info->has_payment_step() : false;
		$status_config    = Andreani_Shipments_List::get_shipping_status_config( $shipping_status, $has_payment_step );
		$status_label    = $status_config['label'];
		$status_class    = str_replace( 'andr-status--', '', $status_config['class'] );

		$date_created = $order->get_date_created();
		$created_at   = $date_created ? $date_created->date_i18n( 'd/m/Y H:i' ) : '';

		$cutoff_ts      = defined( 'ANDREANI_API_CUTOFF' ) ? strtotime( ANDREANI_API_CUTOFF ) : 0;
		$order_ts       = $date_created ? $date_created->getTimestamp() : 0;
		$is_post_cutoff = $cutoff_ts && $order_ts >= $cutoff_ts;

		$last_error      = is_array( $hydrated_item ) && ! empty( $hydrated_item['last_error'] )
			? (string) $hydrated_item['last_error']
			: (string) $order->get_meta( '_andreani_last_error', true );
		$last_error_body = (string) $order->get_meta( '_andreani_last_error_body', true );

		$items       = $order->get_items();
		$items_count = 0;
		foreach ( $items as $it ) {
			$items_count += (int) $it->get_quantity();
		}
		$subtotal       = (float) $order->get_subtotal();
		$shipping_total = (float) $order->get_shipping_total();

		$icon_pin      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>';
		$icon_user     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="7" r="4"/><path d="M5.5 21a8 8 0 0 1 13 0"/></svg>';
		$icon_idcard   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="9" cy="11" r="2"/><line x1="15" y1="9" x2="19" y2="9"/><line x1="15" y1="13" x2="19" y2="13"/></svg>';
		$icon_dollar   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>';
		$icon_building = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="9" width="18" height="12" rx="1"/><path d="M3 9l3-6h12l3 6"/><line x1="9" y1="14" x2="9" y2="21"/></svg>';
		$icon_phone    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
		$icon_mail     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/></svg>';
		$icon_package  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="16.5" y1="9.4" x2="7.5" y2="4.21"/><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>';
		$icon_scale    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg>';
		$icon_file     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>';
		$icon_hash     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="9" x2="20" y2="9"/><line x1="4" y1="15" x2="20" y2="15"/><line x1="10" y1="3" x2="8" y2="21"/><line x1="16" y1="3" x2="14" y2="21"/></svg>';
		$icon_barcode  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 5v14"/><path d="M8 5v14"/><path d="M12 5v14"/><path d="M17 5v14"/><path d="M21 5v14"/></svg>';
		$icon_calendar = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';
		$icon_chevron  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';

		$html  = '<div class="andreani-detail" data-order-id="' . esc_attr( $order_id ) . '">';

		if ( ! $is_post_cutoff ) {
			$sync_state   = 'legacy';
			$sync_tooltip = esc_attr__( 'Envío legacy (anterior a la integración en línea)', 'andreani-shipping' );
		} elseif ( $is_from_api && ! empty( $data['created'] ) ) {
			$sync_state   = 'ok';
			$sync_tooltip = $api_updated !== ''
				? esc_attr( sprintf( /* translators: %s: timestamp ISO */ __( 'Sincronizado · Última actualización: %s', 'andreani-shipping' ), $api_updated ) )
				: esc_attr__( 'Sincronizado con Andreani', 'andreani-shipping' );
		} else {
			$sync_state   = 'pending';
			$sync_tooltip = esc_attr__( 'Pendiente de sincronización', 'andreani-shipping' );
		}

		$html .= '<header class="andreani-detail__header">';
		$html .=   '<h3 class="andreani-detail__order">';
		$html .=     '<span class="andreani-detail__sync-dot andreani-detail__sync-dot--' . esc_attr( $sync_state ) . '" title="' . $sync_tooltip . '" aria-label="' . $sync_tooltip . '"></span>';
		$html .=     sprintf( /* translators: %s: número del pedido */ esc_html__( 'Pedido #%s', 'andreani-shipping' ), esc_html( $data['order_number'] ) );
		$html .=   '</h3>';
		$html .= '</header>';

		$body_pretty = '';
		if ( ! empty( $last_error_body ) ) {
			$decoded = json_decode( $last_error_body, true );
			$body_pretty = ( null !== $decoded )
				? wp_json_encode( $decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
				: $last_error_body;
		}
		$has_error = ! empty( $last_error );
		$layout_class = $has_error ? ' andreani-detail__layout--has-error' : '';

		$icon_card = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>';

		$pedido_id_display = is_array( $hydrated_item ) && ! empty( $hydrated_item['pedido_id'] )
			? (string) $hydrated_item['pedido_id']
			: (string) $data['pedido_id'];

		$shipping_total_formatted = number_format( $shipping_total, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
		$valor_declarado_formatted = number_format( $valor_declarado_raw, wc_get_price_decimals(), wc_get_price_decimal_separator(), wc_get_price_thousand_separator() );
		$created_short = $date_created ? $date_created->date_i18n( 'd/m/Y' ) : '—';

		$tracking_updated_iso   = ( is_array( $hydrated_item ) && ! empty( $hydrated_item['tracking_updated_at'] ) ) ? (string) $hydrated_item['tracking_updated_at'] : '';
		$tracking_updated_short = '' !== $tracking_updated_iso ? date_i18n( 'd/m/Y', strtotime( $tracking_updated_iso ) ) : '';

		$tracking_status = ( is_array( $hydrated_item ) && ! empty( $hydrated_item['tracking_status'] ) )
			? (string) $hydrated_item['tracking_status']
			: (string) $order->get_meta( '_order_andreani_tracking_status', true );
		if ( ! empty( $display_tracking ) && '' !== $tracking_status ) {
			$icon_clock_big = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
			$icon_truck     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h15v13H1z"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>';
			$icon_store     = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m2 7 2-4h16l2 4"/><path d="M4 7v13a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V7"/><path d="M2 7h20"/><path d="M9 21V12h6v9"/></svg>';
			$icon_flag      = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 22V4"/><rect x="4" y="4" width="16" height="12"/><line x1="4" y1="10" x2="20" y2="10"/><line x1="9.33" y1="4" x2="9.33" y2="16"/><line x1="14.66" y1="4" x2="14.66" y2="16"/></svg>';
			$icon_x         = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

			// "Listo para retirar" solo existe para sucursal; a domicilio el paso intermedio no aplica.
			$rank_map     = array( 'pending_entry' => 1, 'in_transit' => 2, 'ready_pickup' => 3, 'delivered' => 4, 'not_delivered' => 4 );
			$current_rank = isset( $rank_map[ $shipping_status ] ) ? $rank_map[ $shipping_status ] : 1;
			$is_failed    = ( 'not_delivered' === $shipping_status );
			$is_sucursal  = false !== strpos( strtolower( (string) $delivery_mode ), 'sucursal' );

			$step_defs = array(
				array( 'rank' => 1, 'label' => __( 'Pendiente de ingreso', 'andreani-shipping' ), 'icon' => $icon_clock_big ),
				array( 'rank' => 2, 'label' => __( 'En camino', 'andreani-shipping' ),            'icon' => $icon_truck ),
			);
			if ( $is_sucursal ) {
				$step_defs[] = array( 'rank' => 3, 'label' => __( 'Listo para retirar', 'andreani-shipping' ), 'icon' => $icon_store );
			}
			$step_defs[] = $is_failed
				? array( 'rank' => 4, 'label' => __( 'No entregado', 'andreani-shipping' ), 'icon' => $icon_x )
				: array( 'rank' => 4, 'label' => __( 'Entregado', 'andreani-shipping' ), 'icon' => $icon_flag );

			$timeline_steps = array();
			foreach ( $step_defs as $def ) {
				if ( $def['rank'] < $current_rank ) {
					$state = 'done';
				} elseif ( $def['rank'] === $current_rank ) {
					$state = $is_failed ? 'failed' : 'active';
				} else {
					$state = 'pending';
				}

				// El paso 1 lleva la fecha de creación; el paso ACTIVO, la del último cambio de
				// estado (trackingUpdatedAt). Los intermedios ya superados quedan sin fecha porque
				// el back todavía no expone el historial por evento.
				if ( 1 === $def['rank'] ) {
					$step_date = $created_short;
				} elseif ( $def['rank'] === $current_rank && '' !== $tracking_updated_short ) {
					$step_date = $tracking_updated_short;
				} else {
					$step_date = '—';
				}

				$timeline_steps[] = array(
					'label' => $def['label'],
					'date'  => $step_date,
					'state' => $state,
					'icon'  => $def['icon'],
				);
			}

			$html .= '<section class="andreani-detail__timeline" aria-label="' . esc_attr__( 'Seguimiento del envío', 'andreani-shipping' ) . '">';
			$html .=   '<h4 class="andreani-detail__section-title andreani-detail__timeline-title">';
			$html .=     '<span class="andreani-detail__timeline-title-label">' . esc_html__( 'Seguimiento envío Nº:', 'andreani-shipping' ) . '</span>';
			$html .=     '<span class="andreani-detail__timeline-title-value andreani-copy-click" data-copy-text="' . esc_attr( $display_tracking ) . '" title="' . esc_attr__( 'Click para copiar', 'andreani-shipping' ) . '" role="button" tabindex="0">' . esc_html( $display_tracking ) . '</span>';
			$html .=   '</h4>';
			$html .=   '<ol class="andreani-detail__steps">';
			foreach ( $timeline_steps as $step ) {
				$step_class = 'andreani-detail__step andreani-detail__step--' . $step['state'];
				$html .= '<li class="' . esc_attr( $step_class ) . '">';
				$html .=   '<span class="andreani-detail__step-marker" aria-hidden="true">' . $step['icon'] . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
				$html .=   '<span class="andreani-detail__step-text">';
				$html .=     '<span class="andreani-detail__step-label">' . esc_html( $step['label'] ) . '</span>';
				$html .=     '<span class="andreani-detail__step-date">' . esc_html( $step['date'] ) . '</span>';
				$html .=   '</span>';
				$html .= '</li>';
			}
			$html .=   '</ol>';
			$html .=   '<p class="andreani-detail__timeline-note">'
				. esc_html__( 'El detalle de fechas por evento estará disponible próximamente.', 'andreani-shipping' )
				. '</p>';
			$html .= '</section>';
		}

		$html .= '<div class="andreani-detail__layout' . $layout_class . '">';
		$html .= '<div class="andreani-detail__main">';

		$html .= '<div class="andreani-detail__cards-grid">';

		$html .= '<section class="andreani-detail__card">';
		$html .=   '<h4 class="andreani-detail__card-title">' . esc_html__( 'Direcciones', 'andreani-shipping' ) . '</h4>';
		$html .=   '<div class="andreani-detail__route">';

		$html .=     '<div class="andreani-detail__route-point andreani-detail__route-point--origin">';
		$html .=       '<span class="andreani-detail__route-marker" aria-hidden="true">' . $icon_pin . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=       '<div class="andreani-detail__route-info">';
		$html .=         '<div class="andreani-detail__route-label">' . esc_html__( 'Origen', 'andreani-shipping' ) . '</div>';
		$html .=         '<div class="andreani-detail__route-address">' . esc_html( $origen_text ) . '</div>';
		if ( '' !== $sucursal_origen_nombre ) {
			$sucursal_origen_text = $sucursal_origen_nombre;
			if ( '' !== $sucursal_origen_direccion ) {
				$sucursal_origen_text .= ' · ' . $sucursal_origen_direccion;
			}
			$html .=     '<div class="andreani-detail__route-extra">' . esc_html( $sucursal_origen_text ) . '</div>';
		}
		$html .=       '</div>';
		$html .=     '</div>';

		$html .=     '<div class="andreani-detail__route-point andreani-detail__route-point--destination">';
		$html .=       '<span class="andreani-detail__route-marker" aria-hidden="true">' . $icon_pin . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=       '<div class="andreani-detail__route-info">';
		$html .=         '<div class="andreani-detail__route-label">' . esc_html__( 'Destino', 'andreani-shipping' ) . '</div>';
		$html .=         '<div class="andreani-detail__route-address">' . esc_html( $destino_full ) . '</div>';
		if ( ! empty( $sucursal_nombre ) ) {
			$sucursal_text = $sucursal_nombre;
			if ( ! empty( $sucursal_direccion ) ) {
				$sucursal_text .= ' · ' . $sucursal_direccion;
			}
			$html .=       '<div class="andreani-detail__route-extra">' . esc_html( $sucursal_text ) . '</div>';
		}
		$html .=       '</div>';
		$html .=     '</div>';

		$html .=   '</div>';
		$html .= '</section>';

		$last_error = (string) $order->get_meta( '_andreani_last_error', true );
		// Editable solo si faltan campos del destinatario; cubre legacy sin error_code.
		$missing_recipient = '' === trim( (string) $phone ) || '' === trim( (string) $dni );
		$is_editable       = empty( $data['created'] ) && '' !== $last_error && $missing_recipient;

		$icons_for_form = array(
			'user'   => $icon_user,
			'phone'  => $icon_phone,
			'mail'   => $icon_mail,
			'idcard' => $icon_idcard,
		);
		$html .= self::render_recipient_card( $order, $is_editable, $customer_name, $phone, $email, $dni, $icons_for_form );

		$has_items   = ! empty( $items );
		$count_label = $has_items
			? sprintf( /* translators: %d: cantidad de items */ _n( '%d item', '%d items', $items_count, 'andreani-shipping' ), $items_count )
			: '';

		$html .= '<section class="andreani-detail__card">';
		$html .=   '<h4 class="andreani-detail__card-title">' . esc_html__( 'Contenido del envío', 'andreani-shipping' ) . '</h4>';
		$html .=   '<ul class="andreani-detail__shipment">';
		$html .=     '<li class="andreani-detail__shipment-row">' . $icon_package . '<span>' . esc_html( $dimensions_str ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=     '<li class="andreani-detail__shipment-row">' . $icon_scale . '<span>' . esc_html( $weight_str ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=     '<li class="andreani-detail__shipment-row">' . $icon_dollar . '<span>$ ' . esc_html( $valor_declarado_formatted ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=   '</ul>';
		if ( $has_items ) {
			$html .=   '<details class="andreani-detail__shipment-toggle">';
			$html .=     '<summary class="andreani-detail__shipment-summary">';
			$html .=       '<span>' . esc_html( $count_label ) . '</span>';
			$html .=       '<span class="andreani-detail__shipment-chevron">' . $icon_chevron . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=     '</summary>';
			$html .=     '<ul class="andreani-detail__shipment-products">';
			foreach ( $items_data as $row ) {
				$dims_display = '' !== $row['dims'] ? $row['dims'] : '—';
				$html .= '<li>';
				$html .=   '<span class="andreani-detail__shipment-product-qty">' . esc_html( $row['qty'] ) . '×</span>';
				$html .=   '<span class="andreani-detail__shipment-product-name">' . esc_html( $row['name'] ) . '</span>';
				$html .=   '<span class="andreani-detail__shipment-product-price">' . wp_kses_post( wc_price( $row['unit_price'] ) ) . '</span>';
				$html .=   '<span class="andreani-detail__shipment-product-dims">' . esc_html( $dims_display ) . '</span>';
				$html .= '</li>';
			}
			$html .=     '</ul>';
			$html .=   '</details>';
		}
		$html .= '</section>';

		$html .= '<section class="andreani-detail__card">';
		$html .=   '<h4 class="andreani-detail__card-title">' . esc_html__( 'Información Andreani', 'andreani-shipping' ) . '</h4>';
		$html .=   '<dl class="andreani-detail__info">';
		$html .=     '<div><dt>' . esc_html__( 'Contrato', 'andreani-shipping' ) . '</dt>';
		$html .=     '<dd>' . esc_html( '' !== $contract_number ? $contract_number : '—' ) . '</dd></div>';
		$html .=     '<div><dt>' . esc_html__( 'Servicio', 'andreani-shipping' ) . '</dt>';
		$html .=     '<dd>' . esc_html( $servicio_label ) . '</dd></div>';
		$html .=     '<div><dt>' . esc_html__( 'Tipo cliente', 'andreani-shipping' ) . '</dt>';
		$html .=     '<dd>' . esc_html( $tipo_label ) . '</dd></div>';
		$html .=     '<div><dt>' . esc_html__( 'Costo envío', 'andreani-shipping' ) . '</dt>';
		$html .=     '<dd class="andreani-detail__info-price">$ ' . esc_html( $shipping_total_formatted ) . '</dd></div>';
		$html .=   '</dl>';
		$html .= '</section>';

		$html .= '</div>';

		$html .= '<section class="andreani-detail__card andreani-detail__card--full">';
		$html .=   '<h4 class="andreani-detail__card-title">' . esc_html__( 'Detalles', 'andreani-shipping' ) . '</h4>';
		$html .=   '<ul class="andreani-detail__ids">';

		if ( ! empty( $numero_interno_display ) ) {
			$id_orden_label = ( $type_info && $type_info->can( 'id_orden_display' ) )
				? __( 'ID Orden', 'andreani-shipping' )
				: __( 'Número Interno', 'andreani-shipping' );
			$html .= '<li class="andreani-detail__id-row">';
			$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_hash . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=   '<span class="andreani-detail__id-label">' . esc_html( $id_orden_label ) . '</span>';
			$html .=   '<span class="andreani-detail__id-value andreani-copy-click" data-copy-text="' . esc_attr( $numero_interno_display ) . '" title="' . esc_attr__( 'Click para copiar', 'andreani-shipping' ) . '" role="button" tabindex="0">'
				. esc_html( $numero_interno_display ) . '</span>';
			$html .= '</li>';
		}

		if ( ! empty( $display_tracking ) ) {
			$html .= '<li class="andreani-detail__id-row">';
			$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_barcode . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=   '<span class="andreani-detail__id-label">' . esc_html__( 'Nro Seguimiento', 'andreani-shipping' ) . '</span>';
			$html .=   '<span class="andreani-detail__id-value andreani-copy-click" data-copy-text="' . esc_attr( $display_tracking ) . '" title="' . esc_attr__( 'Click para copiar', 'andreani-shipping' ) . '" role="button" tabindex="0">'
				. esc_html( $display_tracking ) . '</span>';
			$html .= '</li>';
		}

		if ( $type_info && $type_info->can( 'pedido_id_row' ) && '' !== $pedido_id_display ) {
			$html .= '<li class="andreani-detail__id-row">';
			$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_hash . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=   '<span class="andreani-detail__id-label">' . esc_html__( 'Pedido ID', 'andreani-shipping' ) . '</span>';
			$html .=   '<span class="andreani-detail__id-value andreani-copy-click" data-copy-text="' . esc_attr( $pedido_id_display ) . '" title="' . esc_attr__( 'Click para copiar', 'andreani-shipping' ) . '" role="button" tabindex="0">'
				. esc_html( $pedido_id_display ) . '</span>';
			$html .= '</li>';
		}

		$icon_activity = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>';
		$html .= '<li class="andreani-detail__id-row">';
		$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_activity . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=   '<span class="andreani-detail__id-label">' . esc_html__( 'Estado', 'andreani-shipping' ) . '</span>';
		$html .=   '<span class="andreani-detail__id-value andreani-detail__id-value--text">' . esc_html( $status_label ) . '</span>';
		$html .= '</li>';

		if ( $type_info && $type_info->can( 'payment_step' ) && ! empty( $data['payment_status'] ) ) {
			$payment_config = Andreani_Shipments_List::get_payment_status_config( $data['payment_status'] );
			$html .= '<li class="andreani-detail__id-row">';
			$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_card . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=   '<span class="andreani-detail__id-label">' . esc_html__( 'Pago', 'andreani-shipping' ) . '</span>';
			$html .=   '<span class="andreani-detail__id-value andreani-detail__id-value--text andreani-detail__payment--' . esc_attr( $data['payment_status'] ) . '">' . esc_html( $payment_config['label'] ) . '</span>';
			$html .= '</li>';
		}

		if ( ! empty( $created_at ) ) {
			$html .= '<li class="andreani-detail__id-row">';
			$html .=   '<span class="andreani-detail__id-icon" aria-hidden="true">' . $icon_calendar . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			$html .=   '<span class="andreani-detail__id-label">' . esc_html__( 'Creado', 'andreani-shipping' ) . '</span>';
			$html .=   '<span class="andreani-detail__id-value andreani-detail__id-value--text">' . esc_html( $created_at ) . '</span>';
			$html .= '</li>';
		}

		$html .=   '</ul>';
		$html .= '</section>';
		$html .= '</div>';

		if ( $has_error ) {
			$icon_alert   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
			$icon_copy    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
			$has_body_tab = ! empty( $body_pretty );
			$tabs_id      = 'andreani-error-' . absint( $order_id );

			$support_data = array( 'message' => $last_error );
			if ( ! empty( $last_error_body ) ) {
				$decoded_body            = json_decode( $last_error_body, true );
				$support_data['request'] = ( null !== $decoded_body ) ? $decoded_body : $last_error_body;
			}
			$support_payload = wp_json_encode( $support_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

			$html .= '<aside class="andreani-detail__aside">';
			$html .= '<div class="andreani-detail__error-card" role="alert">';
			$html .=   '<div class="andreani-detail__error-card-header">';
			$html .=     '<span class="andreani-detail__error-card-icon" aria-hidden="true">' . $icon_alert . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$html .=     '<span class="andreani-detail__error-card-title">' . esc_html__( 'Error al crear envío', 'andreani-shipping' ) . '</span>';
			$html .=     '<button type="button" class="andreani-detail__error-card-copy andreani-copy-click" data-copy-text="' . esc_attr( $support_payload ) . '" title="' . esc_attr__( 'Copiar reporte para soporte (mensaje + request)', 'andreani-shipping' ) . '" aria-label="' . esc_attr__( 'Copiar reporte para soporte', 'andreani-shipping' ) . '">';
			$html .=       $icon_copy . '<span>' . esc_html__( 'Copiar', 'andreani-shipping' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$html .=     '</button>';
			$html .=   '</div>';

			$html .=   '<div class="andr-tabs andreani-detail__error-tabs" data-tabs="' . esc_attr( $tabs_id ) . '">';
			$html .=     '<nav class="andr-tabs__list" role="tablist">';
			$html .=       '<button type="button" class="andr-tabs__item andr-tabs__item--active" data-tab="mensaje" role="tab" aria-selected="true">' . esc_html__( 'Mensaje', 'andreani-shipping' ) . '</button>';
			if ( $has_body_tab ) {
				$html .=     '<button type="button" class="andr-tabs__item" data-tab="request" role="tab" aria-selected="false">' . esc_html__( 'Request', 'andreani-shipping' ) . '</button>';
			}
			$html .=     '</nav>';

			$html .=     '<div class="andr-tabs__panel andr-tabs__panel--active andreani-detail__error-tab-content" data-panel="mensaje" role="tabpanel">';
			$html .=       '<pre class="andreani-detail__error-tab-text andreani-detail__error-tab-text--message">' . esc_html( $last_error ) . '</pre>';
			$html .=     '</div>';

			if ( $has_body_tab ) {
				$html .=   '<div class="andr-tabs__panel andreani-detail__error-tab-content" data-panel="request" role="tabpanel">';
				$html .=     '<pre class="andreani-detail__error-tab-text andreani-detail__error-tab-text--code">' . esc_html( $body_pretty ) . '</pre>';
				$html .=   '</div>';
			}

			$html .=   '</div>'; // .andr-tabs
			$html .= '</div>'; // .andreani-detail__error-card
			$html .= '</aside>';
		}

		$html .= '</div>'; // .andreani-detail__layout

		$html .= '</div>'; // .andreani-detail

		return $html;
	}

	/**
	 * Formatea un número decimal sin ceros innecesarios a la derecha.
	 * Ej: 1.0 → "1", 1.50 → "1.5", 250.00 → "250".
	 * Util para dimensiones del producto donde "1×1×250" lee mejor que "1.00×1.00×250.00".
	 */
	private static function format_number( $value ) {
		$value = (float) $value;
		if ( floor( $value ) === $value ) {
			return (string) (int) $value;
		}
		return rtrim( rtrim( number_format( $value, 2, '.', '' ), '0' ), '.' );
	}

	private static function render_recipient_card( $order, $is_editable, $customer_name, $phone, $email, $dni, $icons ) {
		$icon_warning = '<svg class="andreani-recipient-form__warning" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
		$icon_pencil  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';

		$html  = '<section class="andreani-detail__card' . ( $is_editable ? ' andreani-detail__card--recipient-error' : '' ) . '">';
		$html .=   '<h4 class="andreani-detail__card-title">';
		$html .=     '<span class="andreani-detail__card-title-label">';
		$html .=       esc_html__( 'Destinatario', 'andreani-shipping' );
		if ( $is_editable ) {
			$html .= ' ' . $icon_warning; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		}
		$html .=     '</span>';
		if ( $is_editable ) {
			$html .= '<button type="button" class="andreani-recipient-form__edit-toggle" title="' . esc_attr__( 'Editar datos', 'andreani-shipping' ) . '" aria-label="' . esc_attr__( 'Editar datos', 'andreani-shipping' ) . '">' . $icon_pencil . '</button>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		}
		$html .=   '</h4>';

		if ( ! $is_editable ) {
			$html .= '<ul class="andreani-detail__contact">';
			if ( ! empty( $customer_name ) ) {
				$html .= '<li class="andreani-detail__contact-row">' . $icons['user'] . '<span>' . esc_html( $customer_name ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			}
			if ( ! empty( $phone ) ) {
				$tel_href = 'tel:' . preg_replace( '/[^+\d]/', '', $phone );
				$html .= '<li class="andreani-detail__contact-row">' . $icons['phone'] . '<a href="' . esc_url( $tel_href ) . '">' . esc_html( $phone ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			}
			if ( ! empty( $email ) ) {
				$html .= '<li class="andreani-detail__contact-row">' . $icons['mail'] . '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			}
			if ( ! empty( $dni ) ) {
				$html .= '<li class="andreani-detail__contact-row">' . $icons['idcard'] . '<span>' . esc_html( $dni ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
			}
			if ( empty( $customer_name ) && empty( $phone ) && empty( $email ) && empty( $dni ) ) {
				$html .= '<li class="andreani-detail__contact-row"><span>—</span></li>';
			}
			$html .= '</ul>';
			$html .= '</section>';
			return $html;
		}

		$nonce    = wp_create_nonce( 'andreani_update_recipient_and_retry' );
		$ajax_url = admin_url( 'admin-ajax.php' );

		$html .= '<div class="andreani-recipient-form"';
		$html .=   ' data-order-id="' . esc_attr( $order->get_id() ) . '"';
		$html .=   ' data-ajax-url="' . esc_url( $ajax_url ) . '"';
		$html .=   ' data-nonce="' . esc_attr( $nonce ) . '">';

		$html .= '<ul class="andreani-detail__contact">';

		if ( ! empty( $customer_name ) ) {
			$html .= '<li class="andreani-detail__contact-row">' . $icons['user'] . '<span>' . esc_html( $customer_name ) . '</span></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		}
		if ( ! empty( $email ) ) {
			$html .= '<li class="andreani-detail__contact-row">' . $icons['mail'] . '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a></li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		}

		$html .= '<li class="andreani-detail__contact-row andreani-recipient-form__row">';
		$html .=   $icons['phone']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=   '<input type="tel" name="phone" value="' . esc_attr( $phone ) . '" placeholder="' . esc_attr__( 'Teléfono', 'andreani-shipping' ) . '" required disabled>';
		$html .=   '<span class="andreani-recipient-form__hint" data-hint-for="phone">' . esc_html__( 'necesario', 'andreani-shipping' ) . '</span>';
		$html .= '</li>';

		$html .= '<li class="andreani-detail__contact-row andreani-recipient-form__row">';
		$html .=   $icons['idcard']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG estatico interno.
		$html .=   '<input type="text" name="dni" value="' . esc_attr( $dni ) . '" placeholder="' . esc_attr__( 'DNI / CUIT', 'andreani-shipping' ) . '" inputmode="numeric" required disabled>';
		$html .=   '<span class="andreani-recipient-form__hint" data-hint-for="dni">' . esc_html__( 'necesario', 'andreani-shipping' ) . '</span>';
		$html .= '</li>';

		$html .= '</ul>';

		$html .= '<div class="andreani-recipient-form__actions">';
		$html .=   '<span class="andreani-recipient-form__feedback" role="status" aria-live="polite"></span>';
		$html .=   '<button type="button" class="andreani-recipient-form__submit">';
		$html .=     esc_html__( 'Guardar y reintentar', 'andreani-shipping' );
		$html .=   '</button>';
		$html .= '</div>';

		$html .= '</div>';
		$html .= '</section>';

		return $html;
	}

}
