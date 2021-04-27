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

/**
 * Pinterest_For_Woocommerce_Frontend_Scripts Class.
 */
class Pinterest_For_Woocommerce_Frontend_Assets {

	/**
	 * Hook in methods.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'load_assets' ) );
		add_filter( 'script_loader_tag', array( $this, 'maybe_defer_scripts' ), 10, 3 );
	}


	/**
	 * Enqueues frontend related scripts & styles
	 *
	 * @return void
	 */
	public function load_assets() {

		$enabled_in_loop    = ( ( is_front_page() || is_woocommerce() ) && SaveToPinterest::show_in_loop() );
		$enabled_in_product = ( is_product() && SaveToPinterest::show_in_product() );
		$assets_path_url    = str_replace( array( 'http:', 'https:' ), '', Pinterest_For_Woocommerce()->plugin_url() ) . '/assets/';
		$ext                = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		if ( ! $enabled_in_loop && ! $enabled_in_product ) {
			return;
		}

		wp_enqueue_script( 'pinterest-for-woocommerce-pinit', 'https://assets.pinterest.com/js/pinit.js', array(), PINTEREST_FOR_WOOCOMMERCE_VERSION, true );
		wp_enqueue_style( 'pinterest-for-woocommerce-pins', $assets_path_url . 'css/frontend/pinterest-for-woocommerce-pins' . $ext . '.css', array(), PINTEREST_FOR_WOOCOMMERCE_VERSION );

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
			'pinterest-for-woocommerce-pinit',
		);

		if ( in_array( $handle, $defer, true ) ) {
			return '<script src="' . $src . '" defer="defer"></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript --- Not enqueuing here.
		}

		return $tag;
	}
}

new Pinterest_For_Woocommerce_Frontend_Assets();
