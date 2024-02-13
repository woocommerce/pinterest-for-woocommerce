<?php
/**
 * WordPress Plugin Uninstall
 *
 * Uninstalling WordPress Plugin.
 *
 * @package     Pinterest_For_Woocommerce/Uninstaller
 * @version     1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/pinterest-for-woocommerce.php';

try {
	Pinterest_For_Woocommerce::disconnect();
} catch ( Throwable $th ) {
	// phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
	// Do nothing - this is a cleanup routine.
}

$plugin_settings = get_option( 'pinterest_for_woocommerce' );

if ( $plugin_settings['erase_plugin_data'] ) {
	delete_option( 'pinterest_for_woocommerce' );
	delete_option( 'pinterest_for_woocommerce_data' );
	delete_option( 'pinterest_for_woocommerce_marketing_notifications_init_timestamp' );
	delete_option( 'pinterest_for_woocommerce_account_connection_timestamp' );
}

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'pinterest-for-woocommerce-handle-sync', array(), 'pinterest-for-woocommerce' );
	as_unschedule_all_actions( 'pinterest-for-woocommerce-feed-generation', array(), 'pinterest-for-woocommerce' );
	as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', array(), 'pinterest-for-woocommerce' );
}
