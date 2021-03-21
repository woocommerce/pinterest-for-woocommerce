<?php
/**
 * Handle frontend scripts
 *
 * @class       Pinterest_For_Woocommerce_Frontend_Scripts
 * @version     1.0.0
 * @package     Pinterest_For_Woocommerce/Classes/
 * @category    Class
 * @author      WooCommece
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once Pinterest_For_Woocommerce()->plugin_path() . '/includes/class-pinterest-for-woocommerce-assets.php';

/**
 * Pinterest_For_Woocommerce_Frontend_Scripts Class.
 */
class Pinterest_For_Woocommerce_Frontend_Assets extends Pinterest_For_Woocommerce_Assets {

	/**
	 * Hook in methods.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'wp_print_scripts', array( $this, 'localize_printed_scripts' ), 5 );
		add_action( 'wp_print_footer_scripts', array( $this, 'localize_printed_scripts' ), 5 );
	}

	/**
	 * Get styles for the frontend.
	 * @access private
	 * @return array
	 */
	public function get_styles() {
		return apply_filters(
			'pinterest_for_woocommerce_enqueue_styles',
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
			'pinterest_for_woocommerce_enqueue_scripts',
			array()
		);
	}

}

new Pinterest_For_Woocommerce_Frontend_Assets();
