<?php
/**
 * Pinterest for WooCommerce Feed Registration.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

use Exception;
use Throwable;
use Automattic\WooCommerce\Pinterest\Utilities\ProductFeedLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling feed files registration.
 */
class FeedRegistration {

	use ProductFeedLogger;

	const ACTION_HANDLE_FEED_REGISTRATION = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-feed-registration';

	/**
	 * Local Feed Configurations class.
	 *
	 * @var LocalFeedConfigs of local feed configurations;
	 */
	private $configurations;

	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_generator FeedGenerator
	 */
	private $feed_generator = null;

	/**
	 * Feed Registration.
	 *
	 * @since 1.0.10
	 * @param LocalFeedConfigs $local_feeds_configurations Locations configuration class.
	 * @param FeedGenerator    $feed_generator Feed generator class.
	 */
	public function __construct( $local_feeds_configurations, $feed_generator ) {
		$this->configurations = $local_feeds_configurations;
		$this->feed_generator = $feed_generator;
	}

	/**
	 * Initialize FeedRegistration actions and Action Scheduler hooks.
	 *
	 * @since 1.0.10
	 */
	public function init() {
		add_action( self::ACTION_HANDLE_FEED_REGISTRATION, array( $this, 'handle_feed_registration' ) );
		if ( false === as_has_scheduled_action( self::ACTION_HANDLE_FEED_REGISTRATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time() + 10, 10 * MINUTE_IN_SECONDS, self::ACTION_HANDLE_FEED_REGISTRATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}
	}

	/**
	 * Check if the feed is registered based on the plugin's settings.
	 * If not, try to register it,
	 * Log issues.
	 * Potentially report issues.
	 *
	 * Should be run on demand when settings change,
	 * and on a scheduled basis.
	 *
	 * @return mixed
	 *
	 * @throws Exception PHP Exception.
	 */
	public function handle_feed_registration() {

		// Clean merchants error code.
		Pinterest_For_Woocommerce()::save_data( 'merchant_connected_diff_platform', false );

		if ( ! self::feed_file_exists() ) {
			self::log( 'Feed didn\'t fully generate yet. Retrying later.', 'debug' );
			// Feed is not generated yet, lets wait a bit longer.
			return true;
		}

		try {
			if ( self::register_feed() ) {
				return true;
			}

			throw new Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );

		} catch ( Throwable $th ) {
			if ( method_exists( $th, 'get_pinterest_code' ) && 4163 === $th->get_pinterest_code() ) {
				// Save the error to read it during the Health Check.
				Pinterest_For_Woocommerce()::save_data( 'merchant_connected_diff_platform', true );
			}

			self::log( $th->getMessage(), 'error' );
			return false;
		}

	}

	/**
	 * Handles feed registration using the given arguments.
	 * Will try to create a merchant if none exists.
	 * Also if a different feed is registered, it will update using the URL in the
	 * $feed_args.
	 *
	 * @return boolean
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function register_feed() {

		$merchant = Merchants::get_merchant();

		if ( ! empty( $merchant['data']->id ) && 'declined' === $merchant['data']->product_pin_approval_status ) {

			self::log( 'Pinterest returned a Declined status for product_pin_approval_status' );
			return false;
		}

		$merchant_id   = $merchant['data']->id;
		$local_feed_id = self::get_locally_stored_registered_feed_id();

		if ( ! $local_feed_id || ! Feeds::match_local_feed_configuration_to_registered_feeds( $merchant_id ) ) {

			$response = Merchants::update_or_create_merchant();

			// If he response contains an array with the feed id this means that it is registered.
			if ( $response['feed_id'] ) {
				$local_feed_id = $response['feed_id'];
			}
		}

		if ( $local_feed_id && ( ! Feeds::is_local_feed_enabled( $merchant_id, $local_feed_id ) ) ) {
			Feeds::enabled_feed( $merchant_id, $local_feed_id );
		}

		// Cleanup feeds that are registered but not in the local feed configurations.
		self::maybe_disable_stale_feeds_for_merchant( $merchant_id );

		return true;
	}

	/**
	 * Check if there are stale feeds that are registered but not in the local feed configurations.
	 * Deregister them if they are registered as WooCommerce integration.
	 *
	 * @since x.x.x
	 *
	 * @param string $merchant_id Merchant ID.
	 *
	 * @return void
	 */
	public static function maybe_disable_stale_feeds_for_merchant( $merchant_id ) {

		$feed_profiles = Feeds::get_merchant_feeds( $merchant_id );

		if ( empty( $feed_profiles ) ) {
			return;
		}

		$local_feed_id = self::get_locally_stored_registered_feed_id();

		foreach ( $feed_profiles as $feed ) {
			// Local feed should not be disabled.
			if ( $local_feed_id === $feed->id ) {
				continue;
			}

			// Only disable feeds that are registered as WooCommerce integration.
			if ( 'WOOCOMMERCE' !== $feed->integration_platform_type ) {
				continue;
			}

			if ( 'ACTIVE' === $feed->feed_status ) {
				Feeds::disable_feed( $merchant_id, $feed->id );
			}
		}

		// TODO: Invalidate cache.
	}

	/**
	 * Checks if the feed file for the configured (In $state var) feed exists.
	 * This could be true as the feed is being generated, if its not the 1st time
	 * its been generated.
	 *
	 * @return bool
	 */
	public function feed_file_exists() {
		return $this->feed_generator->check_if_feed_file_exists();
	}

	/**
	 * Returns the feed profile ID stored locally if it's registered.
	 * Returns `false` otherwise.
	 * If everything is configured correctly, this feed profile id will match
	 * the setup that the merchant has in Pinterest.
	 *
	 * @return string|boolean
	 */
	public static function get_locally_stored_registered_feed_id() {
		return Pinterest_For_Woocommerce()::get_data( 'feed_registered' ) ?? false;
	}

	/**
	 * Stop feed generator jobs.
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::ACTION_HANDLE_FEED_REGISTRATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}

	/**
	 * Cleanup registration data.
	 */
	public static function deregister() {
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', false );
		self::cancel_jobs();
	}
}
