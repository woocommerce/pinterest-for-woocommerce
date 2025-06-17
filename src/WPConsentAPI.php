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
		add_filter( 'woocommerce_pinterest_disable_tracking_user_consent', array( $this, 'should_disable_tracking_for_user_consent' ) );
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
	 * Check if tracking should be disabled based on user marketing consent.
	 *
	 * @since 1.4.17
	 * @since 1.4.21 Renamed from should_disable_tracking() for clarity.
	 *
	 * @return bool
	 */
	public function should_disable_tracking_for_user_consent(): bool {
		return ! wp_has_consent( 'marketing' );
	}
}
