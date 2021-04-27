<?php
/**
 * Handle frontend scripts
 *
 * @class       Pinterest_For_Woocommerce_Frontend_Scripts
 * @version     1.0.0
 * @package     Pinterest_For_Woocommerce/Classes/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Pinterest\SaveToPinterest;

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
		add_filter( 'script_loader_tag', array( $this, 'maybe_defer_scripts' ), 10, 3 );
	}

	/**
	 * Get styles for the frontend.
	 *
	 * @return array
	 */
	public function get_styles() {

		$styles = array();

		$enabled_in_loop    = ( ( is_front_page() || is_woocommerce() ) && SaveToPinterest::show_in_loop() );
		$enabled_in_product = ( is_product() && SaveToPinterest::show_in_product() );

		if ( $enabled_in_loop || $enabled_in_product ) {
			$styles['pinterest-for-woocommerce-pins'] = array(
				'src' => $this->localize_asset( 'css/frontend/pinterest-for-woocommerce-pins.css' ),
			);
		}

		return apply_filters( 'pinterest_for_woocommerce_enqueue_styles', $styles );
	}

	/**
	 * Get styles for the frontend.
	 *
	 * @return array
	 */
	public function get_scripts() {

		$scripts = array();

		$enabled_in_loop    = ( ( is_front_page() || is_woocommerce() ) && SaveToPinterest::show_in_loop() );
		$enabled_in_product = ( is_product() && SaveToPinterest::show_in_product() );

		if ( $enabled_in_loop || $enabled_in_product ) {
			$scripts['pinterest-for-woocommerce-pinit'] = array(
				'src' => 'https://assets.pinterest.com/js/pinit.js',
			);
		}

		return apply_filters( 'pinterest_for_woocommerce_enqueue_scripts', $scripts );
	}

	/**
	 * Filters the HTML script tag to defer specifics scripts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tag    The `<script>` tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 * @param string $src    The script's source URL.
	 */
	public function maybe_defer_scripts( $tag, $handle, $src ) {

		$defer = array(
			'pinterest-for-woocommerce-pinit'
		);

		if ( in_array( $handle, $defer ) ) {
			return '<script src="' . $src . '" defer="defer"></script>' . "\n";
		}

		return $tag;
	}
}

new Pinterest_For_Woocommerce_Frontend_Assets();
