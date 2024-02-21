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
use Automattic\WooCommerce\Pinterest\Exception\PinterestApiLocaleException;

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
	 * Feed File Operations Instance
	 *
	 * @var $feed_file_operations FeedFileOperations
	 */
	private $feed_file_operations;

	/**
	 * Feed Registration.
	 *
	 * @since 1.0.10
	 * @param LocalFeedConfigs   $local_feeds_configurations Locations configuration class.
	 * @param FeedFileOperations $feed_file_operations Feed file operations class.
	 */
	public function __construct( $local_feeds_configurations, $feed_file_operations ) {
		$this->configurations       = $local_feeds_configurations;
		$this->feed_file_operations = $feed_file_operations;
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
	 * @return bool
	 *
	 * @throws Exception PHP Exception.
	 */
	public function handle_feed_registration(): bool {

		// Clean merchants error code.
		$this->clear_merchant_error_code();

		if ( ! self::feed_file_exists() ) {
			self::log( 'Feed did not fully generate yet. Retrying later.' );
			// Feed is not generated yet, lets wait a bit longer.
			return true;
		}

		try {
			if ( self::register_feed() ) {
				return true;
			}
			throw new Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );
		} catch ( PinterestApiLocaleException $e ) {
			Pinterest_For_Woocommerce()::save_data( 'merchant_locale_not_valid', true );

			// translators: %s: Error message.
			$error_message = "Could not register feed. Error: {$e->getMessage()}";
			self::log( $error_message, 'error' );
			return false;
		} catch ( Throwable $th ) {
			if ( method_exists( $th, 'get_pinterest_code' ) && 4163 === $th->get_pinterest_code() ) {
				Pinterest_For_Woocommerce()::save_data( 'merchant_connected_diff_platform', true );
			}

			self::log( $th->getMessage(), 'error' );
			return false;
		}
	}

	/**
	 * Clear merchant error code.
	 *
	 * @since 1.2.13
	 * @return void
	 */
	private function clear_merchant_error_code() {
		Pinterest_For_Woocommerce()::save_data( 'merchant_connected_diff_platform', false );
		Pinterest_For_Woocommerce()::save_data( 'merchant_locale_not_valid', false );
	}

	/**
	 * Handles feed registration using the given arguments.
	 * Will try to create a merchant if none exists.
	 * Also, if a different feed is registered, it will update using the URL in the
	 * $feed_args.
	 *
	 * @return boolean
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function register_feed(): bool {
		$feed_id = Feeds::match_local_feed_configuration_to_registered_feeds();

		// If no matching registered feed found try to create it.
		if ( ! $feed_id ) {
			$feed_id = Feeds::create_feed();
		} else {
			// If a matching feed is found, update feed file location for it.
			$configs = LocalFeedConfigs::get_instance()->get_configurations();
			$config  = reset( $configs );
			$data    = array(
				'location' => $config['feed_url'],
			);
			$feed_id = Feeds::update_feed( $feed_id, $data )['id'] ?? '';
		}

		Pinterest_For_Woocommerce()::save_data( 'feed_registered', $feed_id );

		if ( ! $feed_id ) {
			return false;
		}

		self::feed_enable_status_maintenance( $feed_id );
		return true;
	}

	/**
	 * Maintenance function for feed enable status.
	 * Enable the registered feed if it is not enabled.
	 * Disable all other feed configurations for the merchant.
	 *
	 * @param string $feed_id Feed ID.
	 * @return void
	 *
	 * @throws PinterestApiException Feed could not be fetched.
	 * @since 1.2.13
	 */
	private static function feed_enable_status_maintenance( string $feed_id ) {
		// Check if the feed is enabled. If not, enable it.
		if ( ! Feeds::is_local_feed_enabled( $feed_id ) ) {
			Feeds::enabled_feed( $feed_id );
		}

		// Cleanup feeds that are registered but not in the local feed configurations.
		self::maybe_disable_stale_feeds_for_merchant( $feed_id );
	}

	/**
	 * Check if there are stale feeds that are registered but not in the local feed configurations.
	 * Deregister them if they are registered as WooCommerce integration.
	 *
	 * @param string $feed_id Feed ID.
	 * @return void
	 *
	 * @since 1.2.13
	 */
	public static function maybe_disable_stale_feeds_for_merchant( string $feed_id ) {
		$feeds = Feeds::get_feeds();

		if ( empty( $feeds ) ) {
			return;
		}

		$invalidate_cache = false;

		foreach ( $feeds as $feed ) {
			// Local feed should not be disabled.
			if ( $feed_id === $feed['id'] ) {
				continue;
			}

			// Disable the feed if it is active and originated from the current website.
			$is_active_feed      = Feeds::FEED_STATUS_ACTIVE === $feed['status'];
			$is_woocommerce_feed = 0 === strpos( $feed['location'], get_site_url() );
			if ( $is_active_feed && $is_woocommerce_feed ) {
				Feeds::disable_feed( $feed['id'] );
				$invalidate_cache = true;
			}
		}

		if ( $invalidate_cache ) {
			Feeds::invalidate_feeds_cache();
		}
	}

	/**
	 * Checks if the feed file for the configured (In $state var) feed exists.
	 * This could be true as the feed is being generated, if it's not the 1st time
	 * it's been generated.
	 *
	 * @return bool
	 */
	public function feed_file_exists(): bool {
		return $this->feed_file_operations->check_if_feed_file_exists();
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
