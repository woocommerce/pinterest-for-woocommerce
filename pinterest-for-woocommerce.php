<?php
/**
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
 * Plugin URI:        https://woocommerce.com/products/pinterest-for-woocommerce/
 * Description:       Grow your business on Pinterest! Use this official plugin to allow shoppers to Pin products while browsing your store, track conversions, and advertise on Pinterest.
 * Version:           1.0.4
 * Author:            WooCommerce
 * Author URI:        https://woocommerce.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       pinterest-for-woocommerce
 * Domain Path:       /i18n/languages
 *
 * Requires at least: 5.6
 * Tested up to: 5.8
 * Requires PHP: 7.3
 *
 * WC requires at least: 5.3
 * WC tested up to: 5.5
 */

/**
 * Developer note: updating minimum PHP, WordPress and WooCommerce versions.
 *
 * When updating any version metadata above please ensure other files are updated
 * as needed, for example:
 * - `class-pinterest-for-woocommerce.php`
 * - `phpcs.xml`
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );
define( 'PINTEREST_FOR_WOOCOMMERCE_VERSION', '1.0.4' ); // WRCS: DEFINED_VERSION.

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

// Initiate the plugin.
Pinterest_For_Woocommerce();

// Register deactivation hook.
register_deactivation_hook(
	PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE,
	function () {
		Automattic\WooCommerce\Pinterest\ProductSync::cancel_jobs();
	}
);
