<?php
/**
 * Handle frontend scripts
 *
 * @class       Pinterest4WooCommerce_Frontend_Scripts
 * @version     1.0.0
 * @package     Pinterest_For_Woocommerce/Classes/
 * @category    Class
 * @author      WooCommece
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once Pinterest4WooCommerce()->plugin_path() . '/includes/class-pinterest4woocommerce-assets.php';

/**
 * Pinterest4WooCommerce_Frontend_Scripts Class.
 */
class Pinterest4WooCommerce_Frontend_Assets extends Pinterest4WooCommerce_Assets {

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
			array(
				'pinterest-for-woocommerce-general' => array(
					'src' => $this->localize_asset( 'css/frontend/pinterest-for-woocommerce.css' ),
				),
			)
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
			array(
				'pinterest-for-woocommerce-general' => array(
					'src'  => $this->localize_asset( 'js/frontend/pinterest-for-woocommerce.js' ),
					'data' => array(
						'ajax_url' => Pinterest4WooCommerce()->ajax_url(),
					),
				),
			)
		);
	}

}

new Pinterest4WooCommerce_Frontend_Assets();
