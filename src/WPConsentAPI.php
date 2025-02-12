<?php
/**
 * Implement WP Consent API for Pinterest for WooCommerce.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WPConsentAPI
 *
 * @since x.x.x
 */
class WPConsentAPI {

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	public function __construct() {
		if ( ! $this->is_wp_consent_api_active() ) {
			return;
		}

		add_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME, '__return_true' );

		add_filter(
			'woocommerce_pinterest_disable_tracking',
			function () {
				return ! wp_has_consent( 'marketing' );
			}
		);
	}

	/**
	 * Check if WP Cookie Consent API is active
	 *
	 * @return bool
	 */
	protected function is_wp_consent_api_active() {
		return function_exists( 'wp_has_consent' );
	}
}
