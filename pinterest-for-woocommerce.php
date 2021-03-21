<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://woocommerce.com
 * @since             1.0.0
 * @package           Pinterest_For_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Pinterest for WooCommerce
 * Plugin URI:        https://woocommerce.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            WooCommece
 * Author URI:        https://woocommerce.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pinterest-for-woocommerce
 * Domain Path:       /i18n/languages
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PINTEREST4WOOCOMMERCE_PLUGIN_FILE', __FILE__ );
require_once 'class-pinterest-for-woocommerce.php';

/**
 * Main instance of Pinterest_For_Woocommerce.
 *
 * Returns the main instance of Pinterest_For_Woocommerce to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return Pinterest_For_Woocommerce
 */

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName
function Pinterest_For_Woocommerce() {
	return Pinterest_For_Woocommerce::instance();
}

// Global for backwards compatibility.
$GLOBALS['pinterest_for_woocommerce'] = Pinterest_For_Woocommerce();
