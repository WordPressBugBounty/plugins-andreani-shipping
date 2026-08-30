<?php
/**
 * Plugin Name: Andreani WooCommerce
 * Plugin URI: https://wordpress.org/plugins/andreani-shipping
 * Description: Plugin oficial de Andreani. Simplifica la gestión de tus envíos con Andreani.
 * Version: 1.6.5
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 10.7
 * Author: Andreani
 * Author URI: https://www.andreani.com
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: andreani-shipping
 * Domain Path: /languages
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ANDREANI_PLUGIN_FILE' ) ) {
	define( 'ANDREANI_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ANDREANI_PLUGIN_DIR' ) ) {
	define( 'ANDREANI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ANDREANI_PLUGIN_URL' ) ) {
	define( 'ANDREANI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ANDREANI_PLUGIN_VERSION' ) ) {
	define( 'ANDREANI_PLUGIN_VERSION', '1.6.5' );
}

if ( ! defined( 'ANDREANI_SHIPPING_METHOD_ID' ) ) {
	define( 'ANDREANI_SHIPPING_METHOD_ID', 'andreani_flexipaas' );
}

// Fecha de corte para persistencia en la API. Orders anteriores se leen de la BD local de WP.
if ( ! defined( 'ANDREANI_API_CUTOFF' ) ) {
	define( 'ANDREANI_API_CUTOFF', '2026-04-27T21:06:21.966+00:00' );
}

add_action( 'before_woocommerce_init', function() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'orders_cache', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
} );

require_once ANDREANI_PLUGIN_DIR . 'includes/andreani-plugin.php';

add_action( 'plugins_loaded', array( 'Andreani_Plugin', 'get_instance' ), 10 );

register_activation_hook( __FILE__, array( 'Andreani_Plugin', 'activation_check' ) );
