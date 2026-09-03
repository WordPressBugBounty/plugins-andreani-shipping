<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exporter CSV de envíos.
 *
 * Patrón: una sola fuente de verdad en `get_columns()`. Cada columna define
 * un `label` y un `extract` (closure que recibe $order + $data resuelto y
 * devuelve el valor). Headers y filas se generan del mismo array — imposible
 * desalinearlos. Agregar/quitar/reordenar columnas = un solo lugar.
 */
class Andreani_Shipment_Exporter {
	use Andreani_Ajax_Helpers_Trait;

	const QUERY_LIMIT = 5000;

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_andreani_export_shipments', array( $this, 'handle_export_shipments' ) );
	}

	public function handle_export_shipments() {
		check_ajax_referer( 'andreani_export_shipments', 'nonce' );

		if ( ! $this->user_can_manage_orders() ) {
			wp_send_json_error( array(
				'message' => __( 'No tenés permisos para exportar.', 'andreani-shipping' ),
			), 403 );
		}

		$orders = $this->query_orders( $this->parse_filters() );

		if ( empty( $orders ) ) {
			wp_send_json_error( array(
				'message' => __( 'No hay envíos para exportar.', 'andreani-shipping' ),
			), 400 );
		}

		$csv = $this->build_csv( $orders );

		wp_send_json_success( array(
			'csv'      => $csv,
			'filename' => 'andreani-envios-' . gmdate( 'Y-m-d' ) . '.csv',
			'count'    => count( $orders ),
		) );
	}

	private function parse_filters() {
		return array(
			'client_type'     => isset( $_POST['client_type'] ) ? sanitize_text_field( wp_unslash( $_POST['client_type'] ) ) : '',
			'andreani_status' => isset( $_POST['andreani_status'] ) ? sanitize_text_field( wp_unslash( $_POST['andreani_status'] ) ) : '',
			'search'          => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
			'date_from'       => isset( $_POST['andreani_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['andreani_date_from'] ) ) : '',
			'date_to'         => isset( $_POST['andreani_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['andreani_date_to'] ) ) : '',
		);
	}

	private function query_orders( $filters ) {
		$args = array(
			'type'          => 'shop_order',
			'limit'         => self::QUERY_LIMIT,
			'orderby'       => 'date',
			'order'         => 'DESC',
			'andreani_only' => true,
		);

		if ( '' !== $filters['date_from'] ) {
			$args['date_after'] = $filters['date_from'];
		}
		if ( '' !== $filters['date_to'] ) {
			$args['date_before'] = $filters['date_to'];
		}

		$meta_conditions = array();

		if ( '' !== $filters['search'] ) {
			if ( is_numeric( $filters['search'] ) ) {
				$args['post__in'] = array( absint( $filters['search'] ) );
			} else {
				$meta_conditions[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_order_andreani_tracking_number',
						'value'   => $filters['search'],
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_billing_last_name',
						'value'   => $filters['search'],
						'compare' => 'LIKE',
					),
					array(
						'key'     => '_order_andreani_pedido_id',
						'value'   => $filters['search'],
						'compare' => 'LIKE',
					),
				);
			}
		}

		$type_status_conditions = Andreani_Shipments_List::build_filter_meta_query(
			$filters['client_type'],
			$filters['andreani_status']
		);
		foreach ( $type_status_conditions as $condition ) {
			$meta_conditions[] = $condition;
		}

		if ( 1 === count( $meta_conditions ) ) {
			$args['meta_query'] = $meta_conditions[0]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		} elseif ( count( $meta_conditions ) > 1 ) {
			$args['meta_query'] = array_merge( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array( 'relation' => 'AND' ),
				$meta_conditions
			);
		}

		return wc_get_orders( $args );
	}

	/**
	 * Definición de columnas — única fuente de verdad. Para agregar una columna,
	 * sumá una entrada acá: label + closure que extrae el valor. Headers y filas
	 * se generan del mismo array, no pueden desincronizarse.
	 *
	 * El closure recibe `$order` (WC_Order) y `$data` (array de
	 * `Andreani_Shipments_List::get_order_shipment_data()` ya resuelto).
	 */
	private function get_columns() {
		return array(
			'order_number' => array(
				'label'   => __( 'Pedido', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return $data['order_number'];
				},
			),
			'customer_name' => array(
				'label'   => __( 'Cliente', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return $order->get_formatted_billing_full_name();
				},
			),
			'dni' => array(
				'label'   => __( 'DNI', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return (string) $order->get_meta( '_billing_dni', true );
				},
			),
			'phone' => array(
				'label'   => __( 'Teléfono', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$phone = method_exists( $order, 'get_shipping_phone' ) ? (string) $order->get_shipping_phone() : '';
					return '' !== $phone ? $phone : (string) $order->get_billing_phone();
				},
			),
			'email' => array(
				'label'   => __( 'Email', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return (string) $order->get_billing_email();
				},
			),
			'shipping_address' => array(
				'label'   => __( 'Dirección', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$addr = (string) $order->get_shipping_address_1();
					return '' !== $addr ? $addr : (string) $order->get_billing_address_1();
				},
			),
			'shipping_city' => array(
				'label'   => __( 'Ciudad', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$city = (string) $order->get_shipping_city();
					return '' !== $city ? $city : (string) $order->get_billing_city();
				},
			),
			'shipping_state' => array(
				'label'   => __( 'Provincia', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$state = (string) $order->get_shipping_state();
					return '' !== $state ? $state : (string) $order->get_billing_state();
				},
			),
			'shipping_postcode' => array(
				'label'   => __( 'CP', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$cp = (string) $order->get_shipping_postcode();
					return '' !== $cp ? $cp : (string) $order->get_billing_postcode();
				},
			),
			'sucursal_destino' => array(
				'label'   => __( 'Sucursal destino', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$nombre = (string) $order->get_meta( '_andreani_sucursal_nombre', true );
					if ( '' === $nombre ) {
						return '';
					}
					$dir = (string) $order->get_meta( '_andreani_sucursal_direccion', true );
					return '' !== $dir ? $nombre . ' · ' . $dir : $nombre;
				},
			),
			'sucursal_origen' => array(
				'label'   => __( 'Sucursal origen', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$nombre = (string) $order->get_meta( '_order_andreani_sucursal_origen_nombre', true );
					if ( '' === $nombre ) {
						return '';
					}
					$dir = (string) $order->get_meta( '_order_andreani_sucursal_origen_direccion', true );
					return '' !== $dir ? $nombre . ' · ' . $dir : $nombre;
				},
			),
			'products' => array(
				'label'   => __( 'Productos', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$names = array();
					foreach ( $order->get_items() as $item ) {
						$names[] = $item->get_quantity() . '× ' . $item->get_name();
					}
					return implode( ' | ', $names );
				},
			),
			'weight_grams' => array(
				'label'   => __( 'Peso (g)', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$unit  = strtolower( (string) get_option( 'woocommerce_weight_unit', 'kg' ) );
					$total = 0.0;
					foreach ( $order->get_items() as $item ) {
						$product = method_exists( $item, 'get_product' ) ? $item->get_product() : null;
						if ( ! $product ) {
							continue;
						}
						$w = (float) $product->get_weight();
						if ( $w > 0 ) {
							$total += $w * (int) $item->get_quantity();
						}
					}
					if ( $total <= 0 ) {
						return '';
					}
					return (string) (int) round( 'kg' === $unit ? $total * 1000 : $total );
				},
			),
			'declared_value' => array(
				'label'   => __( 'Valor declarado', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return $this->format_money( (float) $order->get_subtotal() );
				},
			),
			'client_type_label' => array(
				'label'   => __( 'Tipo', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$type_info = Andreani_Client_Type::from_id( $data['client_type'] );
					return $type_info ? $type_info->short_label() : 'Pyme';
				},
			),
			'delivery_mode' => array(
				'label'   => __( 'Servicio', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return ucfirst( (string) $data['delivery_mode'] );
				},
			),
			'contract_number' => array(
				'label'   => __( 'Contrato', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return (string) $order->get_meta( '_order_andreani_contract_number', true );
				},
			),
			'status' => array(
				'label'   => __( 'Estado', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$type_info        = Andreani_Client_Type::from_id( $data['client_type'] );
					$has_payment_step = $type_info ? $type_info->has_payment_step() : false;
					$config           = Andreani_Shipments_List::get_shipping_status_config(
						$data['shipping_status'],
						$has_payment_step
					);
					return (string) $config['label'];
				},
			),
			'payment_status' => array(
				'label'   => __( 'Pago', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					if ( empty( $data['is_pyme'] ) || empty( $data['payment_status'] ) ) {
						return '';
					}
					$config = Andreani_Shipments_List::get_payment_status_config( $data['payment_status'] );
					return (string) $config['label'];
				},
			),
			'internal_number' => array(
				'label'   => __( 'ID Orden', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$internal = (string) $order->get_meta( '_order_andreani_numero_interno', true );
					if ( '' === $internal && ! empty( $data['is_pyme'] ) && ! empty( $data['pedido_id'] ) ) {
						$internal = '#' . $data['order_number'] . '-' . $data['pedido_id'];
					}
					return $internal;
				},
			),
			'pedido_id' => array(
				'label'   => __( 'Pedido ID', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return ! empty( $data['is_pyme'] ) ? (string) $data['pedido_id'] : '';
				},
			),
			'tracking_number' => array(
				'label'   => __( 'Nro Seguimiento', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return (string) $data['display_tracking'];
				},
			),
			'shipping_cost' => array(
				'label'   => __( 'Costo envío', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return $this->format_money( (float) $order->get_shipping_total() );
				},
			),
			'order_total' => array(
				'label'   => __( 'Total', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return $this->format_money( (float) $order->get_total() );
				},
			),
			'date_created' => array(
				'label'   => __( 'Fecha', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					$date = $order->get_date_created();
					return $date ? $date->date( 'd/m/Y H:i' ) : '';
				},
			),
			'last_error' => array(
				'label'   => __( 'Error', 'andreani-shipping' ),
				'extract' => function ( $order, $data ) {
					return (string) $order->get_meta( '_andreani_last_error', true );
				},
			),
		);
	}

	private function build_csv( $orders ) {
		$columns = $this->get_columns();
		$output  = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// BOM para que Excel reconozca UTF-8 sin pedirle al user importar manualmente.
		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite

		fputcsv( $output, array_column( $columns, 'label' ), ';' );

		foreach ( $orders as $order ) {
			$data = Andreani_Shipments_List::get_order_shipment_data( $order );
			$row  = array();
			foreach ( $columns as $col ) {
				$row[] = $col['extract']( $order, $data );
			}
			fputcsv( $output, $row, ';' );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		return $csv;
	}

	private function format_money( $amount ) {
		return number_format( (float) $amount, 2, ',', '.' );
	}
}
