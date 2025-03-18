<?php
/**
 * Implement WP Consent API for Pinterest for WooCommerce.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.4.17
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class handling WP Consent API integration.
 *
 * @since 1.4.17
 */
class WPConsentAPI {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! $this->is_wp_consent_api_active() ) {
			return;
		}

		add_filter( 'wp_consent_api_registered_' . PINTEREST_FOR_WOOCOMMERCE_PLUGIN_BASENAME, '__return_true' );
		add_filter( 'woocommerce_pinterest_disable_conversions_capi_tracking', array( $this, 'should_disable_tracking' ) );
	}

	/**
	 * Check if WP Consent API is active.
	 *
	 * @return bool
	 */
	protected function is_wp_consent_api_active(): bool {
		return function_exists( 'wp_has_consent' );
	}

	/**
	 * Check if tracking should be disabled based on marketing consent.
	 *
	 * @return bool
	 */
	public function should_disable_tracking(): bool {
		return ! wp_has_consent( 'marketing' );
	}
}
