<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ANDREANI_PYME_HISTORIAL_URL' ) ) {
	define( 'ANDREANI_PYME_HISTORIAL_URL', 'https://pymes.andreani.com/ver-envios?tab=integraciones' );
}
if ( ! defined( 'ANDREANI_PYME_HACER_ENVIO_URL' ) ) {
	define( 'ANDREANI_PYME_HACER_ENVIO_URL', 'https://pymes.andreani.com/hacer-envio/' );
}
if ( ! defined( 'ANDREANI_CORPO_HISTORIAL_URL' ) ) {
	define( 'ANDREANI_CORPO_HISTORIAL_URL', 'https://corporativo.andreani.com/historial' );
}

class Andreani_Client_Type {

	const PYME           = 'pyme';
	const CORPORATIVO    = 'corporativo';
	const MIDDLE_MARKET  = 'middle_market';

	private static $registry = null;

	private $id;
	private $label;
	private $short_label;
	private $prefix;
	private $info_option;
	private $tracking_meta_key;
	private $tracking_response_key;
	private $tracking_log_label;
	private $shipped_meta_key;
	private $shipped_date_meta_key;
	private $manual_tracking_meta_key;
	private $contract_filter_types;
	private $contract_display_order;
	private $capabilities    = array();
	private $display_config  = array();

	private function __construct( array $config ) {
		$this->id                       = $config['id'];
		$this->label                    = $config['label'];
		$this->short_label              = $config['short_label'];
		$this->prefix                   = $config['prefix'];
		$this->info_option              = $config['info_option'];
		$this->tracking_meta_key        = $config['tracking_meta_key'];
		$this->tracking_response_key    = $config['tracking_response_key'];
		$this->tracking_log_label       = $config['tracking_log_label'];
		$this->shipped_meta_key         = $config['shipped_meta_key'];
		$this->shipped_date_meta_key    = $config['shipped_date_meta_key'];
		$this->manual_tracking_meta_key = $config['manual_tracking_meta_key'];
		$this->contract_filter_types    = $config['contract_filter_types'];
		$this->contract_display_order   = $config['contract_display_order'];
		$this->capabilities             = $config['capabilities'];
		$this->display_config           = $config['display'];
	}

	private static function registry() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		self::$registry = array(
			self::PYME => new self( array(
				'id'                       => self::PYME,
				'label'                    => 'Pyme',
				'short_label'              => 'Pyme',
				'prefix'                   => 'andreani_pyme_',
				'info_option'              => 'andreani_pyme_info',
				'tracking_meta_key'        => '_order_andreani_pedido_id',
				'tracking_response_key'    => 'pedidoId',
				'tracking_log_label'       => 'Pedido ID',
				'shipped_meta_key'         => '_andreani_pyme_shipped',
				'shipped_date_meta_key'    => '_andreani_pyme_shipped_date',
				'manual_tracking_meta_key' => '_andreani_pyme_manual_tracking',
				'contract_filter_types'    => array(),
				'contract_display_order'   => array( 'llega hoy', 'estándar', 'sucursal', 'bigger' ),
				'capabilities' => array(
					'label_pdf'         => true,
					'manual_tracking'   => true,
					'payment_step'      => true,
					'contract_refresh'  => false,
					'contract_details'  => false,
					'pedido_id_row'     => true,
					'id_orden_display'  => true,
				),
				'display' => array(
					'historial_url'                  => ANDREANI_PYME_HISTORIAL_URL,
					'badge_css_class'                => 'andr-badge--pyme',
					'badge_css_suffix'               => 'pyme',
					'tracking_id_label'              => 'ID Orden',
					'customer_action_url_text'       => 'Gestionar mis envíos',
					'external_creation_portal_url'   => ANDREANI_PYME_HACER_ENVIO_URL,
					'external_creation_portal_label' => 'Ir a Pymes Andreani',
				),
			) ),
			self::CORPORATIVO => new self( array(
				'id'                       => self::CORPORATIVO,
				'label'                    => 'Corporativo',
				'short_label'              => 'Corpo',
				'prefix'                   => 'andreani_corporativo_',
				'info_option'              => 'andreani_corporativo_info',
				'tracking_meta_key'        => '_order_andreani_tracking_number',
				'tracking_response_key'    => 'trackingNumber',
				'tracking_log_label'       => 'Tracking',
				'shipped_meta_key'         => '_andreani_corpo_shipped',
				'shipped_date_meta_key'    => '_andreani_corpo_shipped_date',
				'manual_tracking_meta_key' => null,
				'contract_filter_types'    => array( 'paquetes', 'encomienda', 'bigger' ),
				'contract_display_order'   => array(),
				'capabilities' => array(
					'label_pdf'         => true,
					'manual_tracking'   => false,
					'payment_step'      => false,
					'contract_refresh'  => true,
					'contract_details'  => true,
					'pedido_id_row'     => false,
					'id_orden_display'  => false,
				),
				'display' => array(
					'historial_url'                  => ANDREANI_CORPO_HISTORIAL_URL,
					'badge_css_class'                => 'andr-badge--corpo',
					'badge_css_suffix'               => 'corpo',
					'tracking_id_label'              => 'Nro. de seguimiento',
					'customer_action_url_text'       => 'Descargar etiqueta',
					'external_creation_portal_url'   => '',
					'external_creation_portal_label' => '',
				),
			) ),
			self::MIDDLE_MARKET => new self( array(
				'id'                       => self::MIDDLE_MARKET,
				'label'                    => 'Middle Market',
				'short_label'              => 'MM',
				'prefix'                   => 'andreani_middlemarket_',
				'info_option'              => 'andreani_middlemarket_info',
				'tracking_meta_key'        => '_order_andreani_pedido_id',
				'tracking_response_key'    => 'pedidoId',
				'tracking_log_label'       => 'Pedido ID',
				'shipped_meta_key'         => '_andreani_middlemarket_shipped',
				'shipped_date_meta_key'    => '_andreani_middlemarket_shipped_date',
				'manual_tracking_meta_key' => '_andreani_middlemarket_manual_tracking',
				'contract_filter_types'    => array(),
				'contract_display_order'   => array( 'llega hoy', 'estándar', 'sucursal', 'bigger' ),
				'capabilities' => array(
					'label_pdf'         => true,
					'manual_tracking'   => true,
					'payment_step'      => true,
					'contract_refresh'  => false,
					'contract_details'  => false,
					'pedido_id_row'     => true,
					'id_orden_display'  => true,
				),
				'display' => array(
					'historial_url'                  => ANDREANI_PYME_HISTORIAL_URL,
					'badge_css_class'                => 'andr-badge--mm',
					'badge_css_suffix'               => 'mm',
					'tracking_id_label'              => 'ID Orden',
					'customer_action_url_text'       => 'Gestionar mis envíos',
					'external_creation_portal_url'   => ANDREANI_PYME_HACER_ENVIO_URL,
					'external_creation_portal_label' => 'Ir a Pymes Andreani',
				),
			) ),
		);

		return self::$registry;
	}

	public static function pyme() {
		$registry = self::registry();
		return $registry[ self::PYME ];
	}

	public static function corporativo() {
		$registry = self::registry();
		return $registry[ self::CORPORATIVO ];
	}

	public static function middle_market() {
		$registry = self::registry();
		return $registry[ self::MIDDLE_MARKET ];
	}

	public static function from_id( $id ) {
		$registry = self::registry();
		return isset( $registry[ $id ] ) ? $registry[ $id ] : null;
	}

	public static function from_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		$id = (string) $order->get_meta( '_order_andreani_client_type', true );
		return self::from_id( $id );
	}

	public static function from_shipping_method( $shipping_method ) {
		if ( ! is_string( $shipping_method ) || '' === $shipping_method ) {
			return null;
		}
		foreach ( self::registry() as $type ) {
			if ( 0 === strpos( $shipping_method, $type->prefix() ) ) {
				return $type;
			}
		}
		return null;
	}

	public static function all() {
		return array_values( self::registry() );
	}

	public static function all_prefixes() {
		$prefixes = array();
		foreach ( self::registry() as $type ) {
			$prefixes[] = $type->prefix();
		}
		return $prefixes;
	}

	public static function all_shipped_meta_keys() {
		$keys = array();
		foreach ( self::registry() as $type ) {
			$keys[] = $type->shipped_meta_key();
		}
		return $keys;
	}

	public static function all_info_option_names() {
		$names = array();
		foreach ( self::registry() as $type ) {
			$names[] = $type->info_option_name();
		}
		return $names;
	}

	public function id() {
		return $this->id;
	}

	public function label() {
		return $this->label;
	}

	public function short_label() {
		return $this->short_label;
	}

	public function prefix() {
		return $this->prefix;
	}

	public function info_option_name() {
		return $this->info_option;
	}

	public function tracking_meta_key() {
		return $this->tracking_meta_key;
	}

	public function tracking_response_key() {
		return $this->tracking_response_key;
	}

	public function tracking_log_label() {
		return $this->tracking_log_label;
	}

	public function shipped_meta_key() {
		return $this->shipped_meta_key;
	}

	public function shipped_date_meta_key() {
		return $this->shipped_date_meta_key;
	}

	public function manual_tracking_meta_key() {
		return $this->manual_tracking_meta_key;
	}

	public function contract_filter_types() {
		return $this->contract_filter_types;
	}

	public function contract_display_order() {
		return $this->contract_display_order;
	}

	public function is( $id ) {
		return $this->id === $id;
	}

	public function equals( Andreani_Client_Type $other ) {
		return $this->id === $other->id;
	}

	public function can( $capability ) {
		return ! empty( $this->capabilities[ $capability ] );
	}

	public function display( $key, $default = '' ) {
		return isset( $this->display_config[ $key ] ) ? $this->display_config[ $key ] : $default;
	}

	public function is_visible_for_customer( $order_created, $tracking_number ) {
		if ( $this->can( 'manual_tracking' ) ) {
			return (bool) $order_created;
		}
		return ! empty( $tracking_number );
	}

	public function supports_label_pdf() {
		return $this->can( 'label_pdf' );
	}

	public function supports_manual_tracking() {
		return $this->can( 'manual_tracking' );
	}

	public function has_payment_step() {
		return $this->can( 'payment_step' );
	}

	public function supports_contract_refresh() {
		return $this->can( 'contract_refresh' );
	}

	public function shows_contract_details() {
		return $this->can( 'contract_details' );
	}

	public function historial_url() {
		return $this->display( 'historial_url' );
	}

	public function badge_css_class() {
		return $this->display( 'badge_css_class' );
	}
}
