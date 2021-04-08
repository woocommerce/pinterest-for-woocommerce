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
 * Pinterest_For_Woocommerce_Install Class.
 */
class Pinterest_For_Woocommerce_Install {

	/**
	 * Install Pinterest_For_Woocommerce.
	 */
	public static function install() {
		// PERFORM INSTALL ACTIONS HERE

		// Trigger action
		do_action( 'pinterest_for_woocommerce_installed' );
	}
}

register_activation_hook( PINTEREST_FOR_WOOCOMMERCE_PLUGIN_FILE, array( 'Pinterest_For_Woocommerce_Install', 'install' ) );
