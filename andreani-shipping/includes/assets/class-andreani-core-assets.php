<?php
/**
 * Registra los handles compartidos del plugin: capa core (variables/base/utilities)
 * y componentes reutilizables (badge/button/status/modal/card). Solo `wp_register_style`,
 * cada vista encola los handles que necesita y WP resuelve la cadena de dependencias.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Core_Assets {

	private static $instance = null;

	const HANDLE_VARIABLES = 'andreani-core-variables';
	const HANDLE_BASE      = 'andreani-core-base';
	const HANDLE_UTILITIES = 'andreani-core-utilities';

	const HANDLE_C_BADGE  = 'andreani-component-badge';
	const HANDLE_C_BUTTON = 'andreani-component-button';
	const HANDLE_C_STATUS = 'andreani-component-status';
	const HANDLE_C_MODAL  = 'andreani-component-modal';
	const HANDLE_C_CARD   = 'andreani-component-card';
	const HANDLE_C_TABS   = 'andreani-component-tabs';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'register' ), 1 );
	}

	public function register() {
		$this->register_core();
		$this->register_components();
	}

	private function register_core() {
		wp_register_style(
			self::HANDLE_VARIABLES,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/core/01-variables.css',
			array(),
			ANDREANI_PLUGIN_VERSION
		);

		wp_register_style(
			self::HANDLE_BASE,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/core/02-base.css',
			array( self::HANDLE_VARIABLES ),
			ANDREANI_PLUGIN_VERSION
		);

		wp_register_style(
			self::HANDLE_UTILITIES,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/core/03-utilities.css',
			array( self::HANDLE_VARIABLES ),
			ANDREANI_PLUGIN_VERSION
		);
	}

	private function register_components() {
		$components = array(
			self::HANDLE_C_BADGE  => 'badge.css',
			self::HANDLE_C_BUTTON => 'button.css',
			self::HANDLE_C_STATUS => 'status-pill.css',
			self::HANDLE_C_MODAL  => 'modal.css',
			self::HANDLE_C_CARD   => 'card.css',
			self::HANDLE_C_TABS   => 'tabs.css',
		);

		foreach ( $components as $handle => $file ) {
			wp_register_style(
				$handle,
				ANDREANI_PLUGIN_URL . 'includes/assets/css/components/' . $file,
				array( self::HANDLE_VARIABLES ),
				ANDREANI_PLUGIN_VERSION
			);
		}
	}
}
