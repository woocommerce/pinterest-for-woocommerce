<?php
/**
 * Pinterest for WooCommerce Feed Registration.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */
namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FeedRegistration {

	use FeedLogger;

	const ACTION_HANDLE_SYNC = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync';

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

		add_action( self::ACTION_HANDLE_SYNC, array( $this, 'handle_feed_registration' ) );
		if ( false === as_has_scheduled_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time(), 10 * MINUTE_IN_SECONDS, self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
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
	 * @throws \Exception PHP Exception.
	 */
	public function handle_feed_registration() {

		if ( ! $this->feed_file_exists() ) {
			self::log( 'Feed didn\'t fully generate yet. Retrying later.', 'debug' );
			// Feed is not generated yet, lets wait a bit longer.
			return true;
		}

		// So far only one configuration exists. This loop is OK but the self::register_feed will need updating.
		foreach ( $this->configurations->get_configurations() as $location => $config ) {

			$feed_args = array(
				'feed_location'             => $config['feed_url'],
				'feed_format'               => 'XML',
				'feed_default_currency'     => get_woocommerce_currency(),
				'default_availability_type' => 'IN_STOCK',
				'country'                   => $location,
				'locale'                    => str_replace( '_', '-', determine_locale() ),
			);

			try {
				$registered = self::register_feed( $feed_args, $location );

				if ( $registered ) {
					return true;
				}

				throw new \Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );

			} catch ( \Throwable $th ) {
				self::log( $th->getMessage(), 'error' );
				return false;
			}
		}

	}

	/**
	 * Handles feed registration using the given arguments.
	 * Will try to create a merchant if none exists.
	 * Also if a different feed is registered, it will update using the URL in the
	 * $feed_args.
	 *
	 * @param array $feed_args The arguments used to create the feed.
	 *
	 * @return boolean|string
	 *
	 * @throws \Exception PHP Exception.
	 */
	private static function register_feed( $feed_args, $location ) {

		// Get merchant object.
		$merchant   = Merchants::get_merchant( $feed_args );
		$registered = false;

		if ( ! empty( $merchant['data']->id ) && 'declined' === $merchant['data']->product_pin_approval_status ) {
			$registered = false;
			self::log( 'Pinterest returned a Declined status for product_pin_approval_status' );
		} elseif ( ! empty( $merchant['data']->id ) && ! isset( $merchant['data']->product_pin_feed_profile ) ) {
			// No feed registered, but we got a merchant.
			$registered = self::do_add_merchant_feed( $merchant['data']->id, $feed_args );
		} elseif ( $feed_args['feed_location'] === $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) {
			// Feed registered.
			$registered = $merchant['data']->product_pin_feed_profile->id;
			self::log( 'Feed registered for merchant: ' . $feed_args['feed_location'] );
		} else {
			$product_pin_feed_profile    = $merchant['data']->product_pin_feed_profile;
			$product_pin_feed_profile_id = false;
			$prev_registered             = self::get_registered_feed_id();
			if ( false !== $prev_registered ) {
				try {
					$feed                        = API\Base::get_merchant_feed( $merchant['data']->id, $prev_registered );
					$product_pin_feed_profile_id = $feed['data']->feed_profile_id;
				} catch ( \Throwable $e ) {
					$product_pin_feed_profile_id = false;
				}
			}

			if ( false === $product_pin_feed_profile_id ) {
				$configured_path = dirname( $product_pin_feed_profile->location_config->full_feed_fetch_location );
				$local_path      = dirname( $feed_args['feed_location'] );

				if ( $configured_path === $local_path && $feed_args['country'] === $product_pin_feed_profile->country && $feed_args['locale'] === $product_pin_feed_profile->locale ) {
					// We can assume we're on the same site.

					$product_pin_feed_profile_id = $product_pin_feed_profile->id;
				}
			}

			if ( false !== $product_pin_feed_profile_id ) { // We update a feed, if we have one matching our site.
				// We cannot change the country or locale, so we remove that from the parameters to send.
				$update_feed_args = $feed_args;
				unset( $update_feed_args['country'] );
				unset( $update_feed_args['locale'] );

				// Actually do the update.
				$feed = API\Base::update_merchant_feed( $product_pin_feed_profile->merchant_id, $product_pin_feed_profile_id, $update_feed_args );

				if ( $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location ) ) {
					$registered = $feed['data']->id;
					self::log( 'Merchant\'s feed updated to current location: ' . $feed_args['feed_location'] );
				}
			} else {
				// We cannot infer that a feed exists, therefore we create a new one.
				$registered = self::do_add_merchant_feed( $merchant['data']->id, $feed_args );
			}
		}
		$feeds              = Pinterest_For_Woocommerce()::get_data( 'feed_registered' ) ?? array();
		$feeds[ $location ] = $registered;
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', $feeds );

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
	 * Make API request to add_merchant_feed.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param array  $feed_args   The arguments used to create the feed.
	 *
	 * @return string|bool
	 */
	private static function do_add_merchant_feed( $merchant_id, $feed_args ) {
		$feed = API\Base::add_merchant_feed( $merchant_id, $feed_args );

		if ( $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location ) ) {
			self::log( 'Added merchant feed: ' . $feed_args['feed_location'] );
			return $feed['data']->id;
		}

		return false;
	}

	/**
	 * If the feed is not already registered, schedules an async action to registrer it asap.
	 *
	 * @return void
	 */
	public static function trigger_async_feed_registration_asap() {

		if ( self::get_registered_feed_id() ) {
			return;
		}

		self::log( 'running trigger_async_feed_registration_asap' );

		as_unschedule_all_actions( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_schedule_recurring_action( time(), 10 * MINUTE_IN_SECONDS, self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
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
	 * Cleanup registration data.
	 *
	 */
	public static function deregister() {
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', false );
	}

}
