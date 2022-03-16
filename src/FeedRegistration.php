<?php
/**
 * Pinterest for WooCommerce Feed Registration.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Exception;
use Throwable;
use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling feed files registration.
 */
class FeedRegistration {

	use FeedLogger;

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
	 * @since x.x.x
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
	 * @since x.x.x
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

		if ( ! self::feed_file_exists() ) {
			self::log( 'Feed didn\'t fully generate yet. Retrying later.', 'debug' );
			// Feed is not generated yet, lets wait a bit longer.
			return true;
		}

		try {
			$registered = self::register_feed();

			if ( $registered ) {
				return true;
			}

			throw new Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );

		} catch ( Throwable $th ) {
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
	 * @return boolean|string
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function register_feed() {

		// Get merchant object.
		$merchant   = Merchants::get_merchant();
		$registered = false;

		if ( ! empty( $merchant['data']->id ) && 'declined' === $merchant['data']->product_pin_approval_status ) {

			$registered = false;
			self::log( 'Pinterest returned a Declined status for product_pin_approval_status' );

		} else {

			// Update feed if we don't have a feed_id saved or if local feed is not properly registered.
			// for cases where the already existed in the API.
			$registered = self::get_registered_feed_id();

			if ( ! $registered || ! Feeds::is_local_feed_registered( $merchant['data']->id ) ) {

				// The response only contains the merchant id.
				$response = Merchants::update_or_create_merchant();

				// The response contains an array with the ID of merchant and feed.
				$registered = $response['feed_id'];
			}
		}

		return $registered;
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
	 * Returns the feed profile ID if it's registered. Returns `false` otherwise.
	 *
	 * @return string|boolean
	 */
	public static function get_registered_feed_id() {
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
