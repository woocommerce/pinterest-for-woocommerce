<?php
/**
 * Pinterest for WooCommerce Ratings Notice REST controller.
 *
 * @package Pinterest_For_Woocommerce/API
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\RatingsNotice\Eligibility;
use Automattic\WooCommerce\Pinterest\RatingsNotice\State;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * REST endpoint that powers the in-plugin ratings notice.
 *
 * GET  /pinterest/v1/rating_notice  - returns the current decision.
 * POST /pinterest/v1/rating_notice  - transitions state (snooze|clicked|dismiss_forever).
 */
class RatingsNotice extends VendorAPI {

	const ENDPOINT = 'rating_notice';

	const CACHE_KEY = 'pinterest_for_woocommerce_rating_notice_cache';
	const CACHE_TTL = 25 * HOUR_IN_SECONDS;

	const ACTION_SNOOZE          = 'snooze';
	const ACTION_CLICKED         = 'clicked';
	const ACTION_DISMISS_FOREVER = 'dismiss_forever';

	/**
	 * Register routes for the ratings notice.
	 */
	public function __construct() {
		$this->base                        = self::ENDPOINT;
		$this->supports_multiple_endpoints = true;
		$this->endpoint_callbacks_map      = array(
			'get_rating_notice' => WP_REST_Server::READABLE,
			'set_rating_notice' => WP_REST_Server::CREATABLE,
		);

		$this->register_routes();
	}

	/**
	 * Handle GET requests. Returns the current should_show/channel/ask_count.
	 *
	 * @return array
	 */
	public function get_rating_notice() {
		$eligibility = self::get_cached_eligibility();
		$decision    = State::decide( (bool) $eligibility['eligible'] );

		// Record that the notice has been served to the client once, so subsequent
		// calls know we've passed through a render. Done here to keep the client
		// side simple and avoid a second round-trip just to bump ask_count.
		if ( $decision['should_show'] ) {
			State::record_shown();
		}

		return $decision;
	}

	/**
	 * Handle POST requests. Dispatches state transitions.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return array|WP_Error
	 */
	public function set_rating_notice( WP_REST_Request $request ) {
		$action = (string) $request->get_param( 'action' );

		switch ( $action ) {
			case self::ACTION_SNOOZE:
				State::snooze();
				break;
			case self::ACTION_CLICKED:
				State::mark_clicked();
				break;
			case self::ACTION_DISMISS_FOREVER:
				State::dismiss_forever();
				break;
			default:
				return new WP_Error(
					\PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_' . self::ENDPOINT,
					esc_html__( 'Unrecognized ratings notice action.', 'pinterest-for-woocommerce' ),
					array( 'status' => 400 )
				);
		}

		$eligibility = self::get_cached_eligibility();
		return State::decide( (bool) $eligibility['eligible'] );
	}

	/**
	 * Read eligibility from the cache, refreshing on miss.
	 *
	 * @return array{eligible: bool, reason: string}
	 */
	public static function get_cached_eligibility(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) && isset( $cached['eligible'] ) ) {
			return array(
				'eligible' => (bool) $cached['eligible'],
				'reason'   => (string) ( $cached['reason'] ?? '' ),
			);
		}
		return self::refresh_cache();
	}

	/**
	 * Recompute eligibility, update the cache and the State hold timer.
	 *
	 * @return array{eligible: bool, reason: string}
	 */
	public static function refresh_cache(): array {
		$result = Eligibility::compute();

		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );

		if ( ! empty( $result['eligible'] ) ) {
			State::mark_eligible();
		}

		return $result;
	}

	/**
	 * Clear the transient cache.
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}
