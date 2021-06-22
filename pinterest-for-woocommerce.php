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
 * @package           woocommerce/pinterest-for-woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Pinterest for WooCommerce
 * Plugin URI:        https://woocommerce.com
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           0.5.0
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

define( 'PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );

/**
 * Autoload packages.
 *
 * The package autoloader includes version information which prevents classes in this feature plugin
 * conflicting with WooCommerce core.
 *
 * We want to fail gracefully if `composer install` has not been executed yet, so we are checking for the autoloader.
 * If the autoloader is not present, let's log the failure and display a nice admin notice.
 */
$autoloader = __DIR__ . '/vendor/autoload_packages.php';

if ( is_readable( $autoloader ) ) {
	require $autoloader;
} else {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log(  // phpcs:ignore
			sprintf(
				/* translators: 1: composer command. 2: plugin directory */
				esc_html__( 'Your installation of the Pinterest for WooCommerce plugin is incomplete. Please run %1$s within the %2$s directory.', 'pinterest-for-woocommerce' ),
				'`composer install`',
				'`' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '`'
			)
		);
	}
	/**
	 * Outputs an admin notice if composer install has not been ran.
	 */
	add_action(
		'admin_notices',
		function() {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: 1: composer command. 2: plugin directory */
						esc_html__( 'Your installation of the Pinterest for WooCommerce plugin is incomplete. Please run %1$s within the %2$s directory.', 'pinterest-for-woocommerce' ),
						'<code>composer install</code>',
						'<code>' . esc_html( str_replace( ABSPATH, '', __DIR__ ) ) . '</code>'
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

require_once 'class-pinterest-for-woocommerce.php';

/**
 * Main instance of Pinterest_For_Woocommerce.
 *
 * Returns the main instance of Pinterest_For_Woocommerce to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return Pinterest_For_Woocommerce
 */
function Pinterest_For_Woocommerce() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName
	return Pinterest_For_Woocommerce::instance();
}

// Global for backwards compatibility.
$GLOBALS['pinterest_for_woocommerce'] = Pinterest_For_Woocommerce();
