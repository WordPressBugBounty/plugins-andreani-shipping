<?php
defined( 'ABSPATH' ) || exit;

class Andreani_Shipments_Hydrator {
	const CACHE_TTL_SECONDS = 60;
	const CACHE_KEY_PREFIX  = 'andreani_hydrate_';

	private static $instance = null;

	private $cutoff_ts;

	/**
	 * Flag de la última corrida: true si la llamada bulk al back falló (WP_Error,
	 * timeout, 5xx, token vencido). Lo lee Andreani_Shipments_List para decidir
	 * si mostrar el banner "datos en línea no disponibles" arriba de la grilla.
	 */
	public $last_run_had_api_failure = false;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$cutoff_iso = defined( 'ANDREANI_API_CUTOFF' ) ? ANDREANI_API_CUTOFF : '';
		$this->cutoff_ts = $cutoff_iso ? strtotime( $cutoff_iso ) : 0;
	}

	/**
	 * Enriquece items WC con datos de la API para los envíos post-cutoff.
	 * Cada item sale con la key `source` ('api' | 'wc'). Si la API no responde,
	 * todos quedan como 'wc' y se loguea el fallback — el usuario sigue operando.
	 *
	 * @param array $items Items con shape de Andreani_Shipments_List::build_item_from_order().
	 * @return array Items enriquecidos (mismo orden, mismo length).
	 */
	public function hydrate( array $items ) {
		$this->last_run_had_api_failure = false;

		if ( empty( $items ) ) {
			return $items;
		}

		$candidates = array();
		foreach ( $items as $i => $item ) {
			if ( $this->needs_api_lookup( $item ) ) {
				$candidates[ (string) $item['order_id'] ] = $i;
			} else {
				$items[ $i ]['source'] = 'wc';
			}
		}

		if ( empty( $candidates ) ) {
			return $items;
		}

		$api_response = $this->lookup_with_cache( array_keys( $candidates ) );

		if ( is_wp_error( $api_response ) ) {
			$this->last_run_had_api_failure = true;
			Andreani_Utils::andreani_log(
				sprintf( '[HYDRATE] Fallback total: %s | candidates=%d', $api_response->get_error_message(), count( $candidates ) ),
				'warning'
			);
			foreach ( $candidates as $i ) {
				$items[ $i ]['source']            = 'wc';
				$items[ $i ]['hydrate_fallback']  = true;
			}
			return $items;
		}

		$by_so = $this->index_by_sales_order_number( $api_response );

		$hit = 0;
		$miss = 0;
		foreach ( $candidates as $so_number => $i ) {
			if ( isset( $by_so[ $so_number ] ) ) {
				$items[ $i ]           = $this->merge_with_api( $items[ $i ], $by_so[ $so_number ] );
				$items[ $i ]['source'] = 'api';
				$this->persist_meta_if_changed( $so_number, $by_so[ $so_number ] );
				$hit++;
			} else {
				$items[ $i ]['source']           = 'wc';
				$items[ $i ]['hydrate_missing']  = true;
				$miss++;
			}
		}

		Andreani_Utils::andreani_log(
			sprintf( '[HYDRATE] candidates=%d hit=%d miss=%d', count( $candidates ), $hit, $miss ),
			'debug'
		);

		return $items;
	}

	private function needs_api_lookup( array $item ) {
		if ( empty( $item['order_id'] ) || empty( $item['created'] ) ) {
			return false;
		}
		if ( ! $this->cutoff_ts ) {
			return false;
		}
		$created_iso = isset( $item['date_created'] ) ? (string) $item['date_created'] : '';
		if ( '' === $created_iso ) {
			return false;
		}
		$created_ts = strtotime( $created_iso );
		return $created_ts && $created_ts >= $this->cutoff_ts;
	}

	private function lookup_with_cache( array $so_numbers ) {
		sort( $so_numbers, SORT_STRING );
		$cache_key = self::CACHE_KEY_PREFIX . md5( implode( '|', $so_numbers ) );

		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$api      = Andreani_Shipments_Api::get_instance();
		$response = $api->lookup_shipments( $so_numbers );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		set_transient( $cache_key, $response, self::CACHE_TTL_SECONDS );
		return $response;
	}

	private function index_by_sales_order_number( $api_response ) {
		$index = array();
		if ( ! is_array( $api_response ) ) {
			return $index;
		}
		foreach ( $api_response as $shipment ) {
			if ( ! is_array( $shipment ) || empty( $shipment['salesOrderNumber'] ) ) {
				continue;
			}
			$index[ (string) $shipment['salesOrderNumber'] ] = $shipment;
		}
		return $index;
	}

	/**
	 * Overrides parciales del item WC con datos frescos del back: la API es
	 * source-of-truth para todo lo que es de Andreani (client_type, delivery_mode,
	 * contract_number, status, tracking, pedidoId, fechas, error).
	 * El resto (customer, destination, products) sigue siendo WC.
	 */
	private function merge_with_api( array $item, array $api ) {
		if ( isset( $api['clientType'] ) && '' !== $api['clientType'] ) {
			$item['client_type'] = $api['clientType'];
		}
		if ( isset( $api['deliveryMode'] ) && '' !== $api['deliveryMode'] ) {
			$item['delivery_mode'] = $api['deliveryMode'];
		}
		if ( isset( $api['contractNumber'] ) && '' !== $api['contractNumber'] ) {
			$item['contract_number'] = $api['contractNumber'];
		}
		if ( isset( $api['status'] ) && '' !== $api['status'] ) {
			$item['api_status'] = $api['status'];
		}
		if ( isset( $api['pedidoId'] ) && '' !== $api['pedidoId'] ) {
			$item['pedido_id']          = $api['pedidoId'];
			$item['andreani_pedido_id'] = $api['pedidoId'];
		}
		// Pyme/MM no persisten el tracking ni el estado en el meta local (vive en la API):
		// recalculamos los estados derivados con el dato fresco para que la grilla no muestre
		// "Pendiente"/"Empaquetado" cuando el envío ya tiene seguimiento.
		if ( isset( $api['trackingStatus'] ) && '' !== $api['trackingStatus'] ) {
			$item['tracking_status'] = $api['trackingStatus'];
			$item['shipping_status'] = Andreani_Shipments_List::map_tracking_status( $api['trackingStatus'] );
		}
		if ( isset( $api['trackingUpdatedAt'] ) && '' !== $api['trackingUpdatedAt'] ) {
			$item['tracking_updated_at'] = $api['trackingUpdatedAt'];
		}
		if ( isset( $api['trackingNumber'] ) && '' !== $api['trackingNumber'] ) {
			$item['tracking_number']  = $api['trackingNumber'];
			$item['display_tracking'] = $api['trackingNumber'];
			$item['payment_status']   = Andreani_Shipments_List::compute_payment_status(
				isset( $item['client_type'] ) ? $item['client_type'] : '',
				$api['trackingNumber']
			);
		}
		if ( isset( $api['internalNumber'] ) && '' !== $api['internalNumber'] ) {
			$item['internal_number'] = $api['internalNumber'];
		}
		if ( isset( $api['priceShipment'] ) ) {
			$item['price_shipment'] = (float) $api['priceShipment'];
		}
		if ( isset( $api['errorMessage'] ) && '' !== $api['errorMessage'] ) {
			$item['last_error'] = $api['errorMessage'];
		}
		if ( isset( $api['updatedAt'] ) ) {
			$item['api_updated_at'] = $api['updatedAt'];
		}
		if ( isset( $api['createdAt'] ) ) {
			$item['api_created_at'] = $api['createdAt'];
		}
		return $item;
	}

	/**
	 * Persiste en el meta local el tracking/estado fresco de la API cuando difiere del guardado.
	 * Aprovecha que el admin ya consultó la API para mantener el meta al día sin esperar al sync
	 * de fondo — así el front del cliente y la próxima carga ven el estado nuevo de inmediato.
	 *
	 * @param string|int $order_id
	 * @param array      $api ShipmentDto de la API.
	 */
	private function persist_meta_if_changed( $order_id, array $api ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$changed = false;
		if ( ! empty( $api['trackingNumber'] ) && (string) $order->get_meta( '_order_andreani_tracking_number', true ) !== (string) $api['trackingNumber'] ) {
			$order->update_meta_data( '_order_andreani_tracking_number', (string) $api['trackingNumber'] );
			$changed = true;
		}
		if ( ! empty( $api['trackingStatus'] ) && (string) $order->get_meta( '_order_andreani_tracking_status', true ) !== (string) $api['trackingStatus'] ) {
			$order->update_meta_data( '_order_andreani_tracking_status', (string) $api['trackingStatus'] );
			$changed = true;
		}

		if ( $changed ) {
			$order->save();
		}
	}
}
