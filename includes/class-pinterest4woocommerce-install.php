<?php
/**
 * Installation related functions and actions.
 *
 * @author   WooCommece
 * @category Admin
 * @package  Pinterest_For_Woocommerce/Classes
 * @version  1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinterest4WooCommerce_Install Class.
 */
class Pinterest4WooCommerce_Install {

	/**
	 * Install Pinterest4WooCommerce.
	 */
	public static function install() {
		// PERFORM INSTALL ACTIONS HERE

		// Trigger action
		do_action( 'pinterest_for_woocommerce_installed' );
	}
}

register_activation_hook( PINTEREST4WOOCOMMERCE_PLUGIN_FILE, array( 'Pinterest4WooCommerce_Install', 'install' ) );
