<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Blocks_Integration implements \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	public function get_name() {
		return Andreani_Blocks::INTEGRATION_NAME;
	}

	public function initialize() {
		Andreani_Blocks::register_scripts();
		Andreani_Blocks::register_style();
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_before', array( 'Andreani_Blocks', 'enqueue_style' ) );
		add_action( 'enqueue_block_editor_assets', array( 'Andreani_Blocks', 'enqueue_style' ) );
	}

	public function get_script_handles() {
		return array( Andreani_Blocks::HANDLE_FRONTEND );
	}

	public function get_editor_script_handles() {
		return array( Andreani_Blocks::HANDLE_EDITOR );
	}

	public function get_script_data() {
		return array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'ajaxAction'    => 'andreani_get_sucursales',
			'nonce'         => wp_create_nonce( 'andreani_checkout_nonce' ),
			'sucursalMatch' => array( 'andreani', 'sucursal' ),
			'namespace'     => Andreani_Blocks::INTEGRATION_NAME,
			'i18n'          => array(
				'titulo'        => __( 'Sucursal de retiro Andreani', 'andreani-shipping' ),
				'placeholder'   => __( 'Elegí la sucursal donde retirás', 'andreani-shipping' ),
				'cargando'      => __( 'Buscando sucursales…', 'andreani-shipping' ),
				'sinCp'         => __( 'Ingresá tu código postal para ver las sucursales.', 'andreani-shipping' ),
				'sinSucursales' => __( 'No encontramos sucursales para el código postal %s.', 'andreani-shipping' ),
				'error'         => __( 'No pudimos traer las sucursales. Probá de nuevo en unos segundos.', 'andreani-shipping' ),
				'requerido'     => __( 'Elegí una sucursal Andreani para continuar.', 'andreani-shipping' ),
				'direccion'     => __( 'Dirección', 'andreani-shipping' ),
			),
		);
	}
}
