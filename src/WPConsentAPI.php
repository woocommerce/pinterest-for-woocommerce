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
 * @class   WPConsentAPI
 * @since   1.4.17
 * @version x.x.x
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
		add_filter( 'woocommerce_pinterest_disable_conversions_api_user_consent', array( $this, 'should_disable_conversions_api_for_user_consent' ) );
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
		return ! $this->has_marketing_consent();
	}

	/**
	 * Check if Conversions API events should be skipped based on user marketing consent.
	 *
	 * Stores with a full-page cache may force the Tag on for every visitor and let
	 * the consent plugin block it in the browser. Server-side events still follow
	 * the marketing consent of the current request.
	 *
	 * @since x.x.x
	 *
	 * @param bool $disable_conversions_api Whether an earlier callback already skips Conversions API events.
	 * @return bool
	 */
	public function should_disable_conversions_api_for_user_consent( $disable_conversions_api = false ) {
		return (bool) $disable_conversions_api || ! $this->has_marketing_consent();
	}

	/**
	 * Follow the API's marketing consent decision, allowing tracking when the API is unavailable.
	 *
	 * @return bool
	 */
	private function has_marketing_consent(): bool {
		if ( $this->is_wp_consent_api_active() ) {
			return (bool) wp_has_consent( 'marketing' );
		}

		return true;
	}
}
