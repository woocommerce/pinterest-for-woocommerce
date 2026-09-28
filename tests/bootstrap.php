<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest;

use WC_Install;

define( 'PLUGIN_TESTS_DIR', __DIR__ );
define( 'PLUGIN_DATA_TESTS_DIR', PLUGIN_TESTS_DIR . '/data' );

global $plugin_dir;
global $wp_plugins_dir;
global $wc_dir;

$wp_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress-tests-lib' );
validate_file_exits( "{$wp_tests_dir}/includes/functions.php" );

$wp_core_dir    = getenv( 'WP_CORE_DIR' ) ?: path_join( sys_get_temp_dir(), '/wordpress' );
$wp_plugins_dir = path_join( $wp_core_dir, '/wp-content/plugins' );

$plugin_dir = dirname( __FILE__, 2 ); // ../../

$wc_dir = getenv( 'WC_DIR' );
if ( ! $wc_dir ) {
	// Check if WooCommerce exists in the core plugin folder. The `bin/install-wp-tests.sh` script clones a copy there.
	$wc_dir = path_join( $wp_plugins_dir, '/woocommerce' );
	if ( ! file_exists( "{$wc_dir}/woocommerce.php" ) ) {
		// Check if WooCommerce exists in parent directory of the plugin (in case the plugin is located in a WordPress installation's `wp-content/plugins` folder)
		$wc_dir = path_join( dirname( $plugin_dir ), '/woocommerce' );
	}
}
validate_file_exits( "{$wc_dir}/woocommerce.php" );

// Require the composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Give access to tests_add_filter() function.
require_once "{$wp_tests_dir}/includes/functions.php";

tests_add_filter( 'muplugins_loaded', function () {
	load_plugins();
} );

tests_add_filter( 'setup_theme', function () {
	install_woocommerce();
} );

// Start up the WP testing environment.
require "{$wp_tests_dir}/includes/bootstrap.php";

// Start up the WC testing environment.
require_once $wc_dir . '/tests/legacy/bootstrap.php';

// Override the storage WooCommerce's test bootstrap selected.
configure_order_storage();

// CLI-only diagnostics from the installed test dependencies.
// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
printf( "Testing PHP %s, WordPress %s, WooCommerce %s.\n", PHP_VERSION, $GLOBALS['wp_version'], WC_VERSION );

// Add helpers for shipping tests.
require_once PLUGIN_TESTS_DIR . '/Helpers/ShippingHelpers.php';

/**
 * Load WooCommerce for testing
 *
 * @global $wc_dir
 */
function install_woocommerce() {
	global $wc_dir;

	define( 'WP_UNINSTALL_PLUGIN', true );
	define( 'WC_REMOVE_ALL_DATA', true );

	include $wc_dir . '/uninstall.php';

	WC_Install::install();

	// Reload capabilities after install, see https://core.trac.wordpress.org/ticket/28374.
	$GLOBALS['wp_roles'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	wp_roles();

	echo "Installing WooCommerce..." . PHP_EOL;
}

/**
 * Select and verify the order storage used by this test run.
 *
 * @throws \RuntimeException When the requested storage is invalid or inactive.
 */
function configure_order_storage() {
	// Storage is chosen below. Without this, WooCommerce can switch an empty shop to
	// HPOS later in the run (e.g. on admin_init in AJAX tests).
	add_filter( 'woocommerce_enable_hpos_by_default_for_new_shops', '__return_false' );

	$storage = getenv( 'PINTEREST_FOR_WOOCOMMERCE_TEST_ORDER_STORAGE' );
	$storage = false === $storage || '' === $storage ? 'cpt' : $storage;

	if ( ! in_array( $storage, array( 'cpt', 'hpos' ), true ) ) {
		throw new \RuntimeException( 'PINTEREST_FOR_WOOCOMMERCE_TEST_ORDER_STORAGE must be cpt or hpos.' );
	}

	if ( 'hpos' === $storage ) {
		wc_get_container()->get( \Automattic\WooCommerce\Internal\DataStores\Orders\DataSynchronizer::class )->create_database_tables();
	}

	update_option( 'woocommerce_custom_orders_table_data_sync_enabled', 'no' );
	update_option( 'woocommerce_custom_orders_table_enabled', 'hpos' === $storage ? 'yes' : 'no' );

	if ( ( 'hpos' === $storage ) !== \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		throw new \RuntimeException( 'The requested order storage is not active.' );
	}

	echo 'Order storage: ' . esc_html( $storage ) . PHP_EOL;
}

/**
 * Manually load plugins
 *
 * @global $plugin_dir
 * @global $wc_dir
 */
function load_plugins() {
	global $plugin_dir;
	global $wc_dir;

	require_once( $wc_dir . '/woocommerce.php' );
	update_option( 'woocommerce_db_version', WC()->version );
	
	// Be sure the WooCommerce features are loaded on the test environment
	add_filter( 'woocommerce_admin_should_load_features', '__return_true' );

	require $plugin_dir . '/pinterest-for-woocommerce.php';
}

/**
 * Checks whether a file exists and throws an error if it doesn't.
 *
 * @param string $file_name
 */
function validate_file_exits( string $file_name ) {
	if ( ! file_exists( $file_name ) ) {
		echo "Could not find {$file_name}, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit( 1 );
	}
}

/**
 * @param string $base
 * @param string $path
 *
 * @return string
 */
function path_join( string $base, string $path ) {
	return rtrim( $base, '/\\' ) . '/' . ltrim( $path, '/\\' );
}
