<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Blocks {

	const INTEGRATION_NAME = 'andreani';
	const BLOCK_NAME       = 'andreani/sucursal-selector';
	const DNI_FIELD_ID     = 'andreani/dni';
	const HANDLE_FRONTEND  = 'andreani-blocks-sucursal';
	const HANDLE_EDITOR    = 'andreani-blocks-sucursal-editor';
	const HANDLE_STYLE     = 'andreani-blocks-sucursal';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->on_blocks_loaded();
		} else {
			add_action( 'woocommerce_blocks_loaded', array( $this, 'on_blocks_loaded' ) );
		}

		add_action( 'init', array( $this, 'register_block_type' ) );
		add_action( 'woocommerce_init', array( $this, 'register_dni_field' ) );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'update_order_from_request' ), 10, 2 );
	}

	public function on_blocks_loaded() {
		if ( interface_exists( '\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface' ) ) {
			require_once ANDREANI_PLUGIN_DIR . 'includes/blocks/class-andreani-blocks-integration.php';
			add_action( 'woocommerce_blocks_checkout_block_registration', array( $this, 'register_integration' ) );
		}

		$this->register_store_api_schema();
	}

	public function register_integration( $registry ) {
		if ( class_exists( 'Andreani_Blocks_Integration' ) && is_object( $registry ) && method_exists( $registry, 'register' ) ) {
			$registry->register( new Andreani_Blocks_Integration() );
		}
	}

	private function register_store_api_schema() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) || ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::IDENTIFIER,
				'namespace'       => self::INTEGRATION_NAME,
				'schema_callback' => array( $this, 'get_store_api_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);
	}

	public function get_store_api_schema() {
		$string_schema = array(
			'type'        => 'string',
			'context'     => array( 'view', 'edit' ),
			'arg_options' => array(
				'validate_callback' => function ( $value ) {
					return is_string( $value );
				},
			),
		);

		return array(
			'branch_code'    => array_merge( array( 'description' => 'Codigo de sucursal Andreani' ), $string_schema ),
			'branch_name'    => array_merge( array( 'description' => 'Nombre de sucursal Andreani' ), $string_schema ),
			'branch_address' => array_merge( array( 'description' => 'Direccion de sucursal Andreani' ), $string_schema ),
		);
	}

	public static function register_scripts() {
		if ( ! wp_script_is( self::HANDLE_FRONTEND, 'registered' ) ) {
			wp_register_script(
				self::HANDLE_FRONTEND,
				ANDREANI_PLUGIN_URL . 'includes/assets/js/blocks/sucursal-selector.js',
				array( 'wc-blocks-checkout', 'wc-blocks-data-store', 'wc-settings', 'wp-element', 'wp-data', 'wp-i18n' ),
				ANDREANI_PLUGIN_VERSION,
				true
			);
		}

		if ( ! wp_script_is( self::HANDLE_EDITOR, 'registered' ) ) {
			wp_register_script(
				self::HANDLE_EDITOR,
				ANDREANI_PLUGIN_URL . 'includes/assets/js/blocks/sucursal-selector-editor.js',
				array( 'wp-blocks', 'wp-element', 'wp-i18n', 'wc-blocks-checkout' ),
				ANDREANI_PLUGIN_VERSION,
				true
			);
		}
	}

	public static function register_style() {
		if ( wp_style_is( self::HANDLE_STYLE, 'registered' ) ) {
			return;
		}

		$deps = class_exists( 'Andreani_Core_Assets' ) ? array( Andreani_Core_Assets::HANDLE_BASE ) : array();

		wp_register_style(
			self::HANDLE_STYLE,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/frontend-checkout-blocks.css',
			$deps,
			ANDREANI_PLUGIN_VERSION
		);
	}

	public static function enqueue_style() {
		self::register_style();
		wp_enqueue_style( self::HANDLE_STYLE );
	}

	public function register_block_type() {
		if ( ! function_exists( 'register_block_type' ) || ! class_exists( 'WP_Block_Type_Registry' ) ) {
			return;
		}

		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			return;
		}

		self::register_scripts();
		register_block_type( ANDREANI_PLUGIN_DIR . 'includes/blocks/sucursal-selector' );
	}

	public function register_dni_field() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::DNI_FIELD_ID,
				'label'             => __( 'DNI', 'andreani-shipping' ),
				'location'          => 'address',
				'type'              => 'text',
				'required'          => true,
				'attributes'        => array(
					'autocomplete' => 'off',
					'maxLength'    => 11,
				),
				'sanitize_callback' => array( __CLASS__, 'sanitize_dni' ),
				'validate_callback' => array( __CLASS__, 'validate_dni' ),
			)
		);
	}

	public static function sanitize_dni( $value ) {
		return preg_replace( '/\D+/', '', (string) $value );
	}

	public static function validate_dni( $value ) {
		if ( '' === trim( (string) $value ) || Andreani_Checkout::is_valid_dni( $value ) ) {
			return true;
		}

		return new WP_Error( 'andreani_dni_invalido', __( 'Ingresá un DNI válido (solo números).', 'andreani-shipping' ) );
	}

	public function update_order_from_request( $order, $request ) {
		if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request || 'POST' !== $request->get_method() ) {
			return;
		}

		$shipping = $this->resolve_order_shipping( $order );
		$order_id = $order->get_id();

		if ( '' !== $shipping['rate_id'] ) {
			$order->update_meta_data( '_chosen_shipping', $shipping['rate_id'] );
		}

		if ( ! $shipping['is_andreani'] ) {
			return;
		}

		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Checkout por bloques completado - Método de envío: {$shipping['rate_id']}", 'info' );

		if ( $shipping['is_sucursal'] ) {
			$data        = $this->get_extension_data( $request );
			$branch_code = isset( $data['branch_code'] ) ? sanitize_text_field( (string) $data['branch_code'] ) : '';

			if ( '' === $branch_code || '0' === $branch_code ) {
				$this->throw_route_exception( 'andreani_branch_required', __( 'Elegí una sucursal Andreani para continuar.', 'andreani-shipping' ) );
			}

			$order->update_meta_data( '_shipping_branch_code', $branch_code );
			$order->update_meta_data( 'sucursal_andreani', $branch_code );

			$branch_name = isset( $data['branch_name'] ) ? sanitize_text_field( (string) $data['branch_name'] ) : '';
			if ( '' !== $branch_name ) {
				$order->update_meta_data( '_andreani_sucursal_nombre', $branch_name );
			}

			$branch_address = isset( $data['branch_address'] ) ? sanitize_text_field( (string) $data['branch_address'] ) : '';
			if ( '' !== $branch_address ) {
				$order->update_meta_data( '_andreani_sucursal_direccion', $branch_address );
			}

			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Sucursal seleccionada: {$branch_code}", 'info' );
		}

		$billing_phone  = trim( (string) $order->get_billing_phone() );
		$shipping_phone = method_exists( $order, 'get_shipping_phone' ) ? trim( (string) $order->get_shipping_phone() ) : '';
		if ( '' === $billing_phone && '' === $shipping_phone ) {
			$this->throw_route_exception( 'andreani_phone_required', __( 'Ingresá un teléfono de contacto para el envío con Andreani.', 'andreani-shipping' ) );
		}

		$order->save();
	}

	public static function is_block_checkout() {
		$utils = '\Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils';
		if ( ! class_exists( $utils ) || ! method_exists( $utils, 'is_checkout_block_default' ) ) {
			return false;
		}

		try {
			return (bool) call_user_func( array( $utils, 'is_checkout_block_default' ) );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	private function resolve_order_shipping( WC_Order $order ) {
		$rate_id     = '';
		$is_andreani = false;
		$candidates  = array();

		foreach ( $order->get_shipping_methods() as $item ) {
			$item_rate_id = (string) $item->get_meta( '_andreani_rate_id', true );
			if ( '' === $rate_id && '' !== $item_rate_id ) {
				$rate_id = $item_rate_id;
			}
			if ( ANDREANI_SHIPPING_METHOD_ID === $item->get_method_id() ) {
				$is_andreani = true;
			}
			$candidates[] = $item_rate_id;
			$candidates[] = $item->get_method_id() . ':' . $item->get_instance_id();
			$candidates[] = (string) $item->get_name();
		}

		if ( '' === $rate_id && function_exists( 'WC' ) && WC()->session ) {
			$chosen = WC()->session->get( 'chosen_shipping_methods' );
			if ( is_array( $chosen ) && ! empty( $chosen ) ) {
				$rate_id = sanitize_text_field( (string) reset( $chosen ) );
				foreach ( $chosen as $chosen_rate ) {
					$candidates[] = (string) $chosen_rate;
				}
			}
		}

		$is_sucursal = false;
		foreach ( $candidates as $candidate ) {
			$candidate = strtolower( $candidate );
			if ( false === strpos( $candidate, 'andreani' ) ) {
				continue;
			}
			$is_andreani = true;
			if ( false !== strpos( $candidate, 'sucursal' ) ) {
				$is_sucursal = true;
			}
		}

		return array(
			'rate_id'     => $rate_id,
			'is_andreani' => $is_andreani,
			'is_sucursal' => $is_sucursal,
		);
	}

	private function get_extension_data( WP_REST_Request $request ) {
		$extensions = $request['extensions'];
		if ( ! is_array( $extensions ) || empty( $extensions[ self::INTEGRATION_NAME ] ) || ! is_array( $extensions[ self::INTEGRATION_NAME ] ) ) {
			return array();
		}

		return $extensions[ self::INTEGRATION_NAME ];
	}

	private function throw_route_exception( $code, $message ) {
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException( $code, $message, 400 );
		}
	}
}
