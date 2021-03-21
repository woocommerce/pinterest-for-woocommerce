<?php
/**
 * Load assets
 *
 * @author      WooCommece
 * @category    Admin
 * @package     Pinterest_For_Woocommerce/Admin
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once Pinterest_For_Woocommerce()->plugin_path() . '/includes/class-pinterest-for-woocommerce-assets.php';

/**
 * Pinterest_For_Woocommerce_Admin_Assets Class.
 */
class Pinterest_For_Woocommerce_Admin_Assets extends Pinterest_For_Woocommerce_Assets {

	/**
	 * Hook in methods.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'localize_printed_scripts' ), 5 );
		add_action( 'admin_print_footer_scripts', array( $this, 'localize_printed_scripts' ), 5 );
	}

	/**
	 * Get styles for the frontend.
	 * @access private
	 * @return array
	 */
	public function get_styles() {
		return apply_filters(
			'pinterest_for_woocommerce_enqueue_admin_styles',
			array()
		);
	}

	/**
	 * Get styles for the frontend.
	 * @access private
	 * @return array
	 */
	public function get_scripts() {
		return apply_filters(
			'pinterest_for_woocommerce_enqueue_admin_scripts',
			array()
		);
	}

}

return new Pinterest_For_Woocommerce_Admin_Assets();
