<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler as ActionSchedulerProxy;
use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;

/**
 * Class Handling registration & generation of the XML product feed.
 */
class ProductSync {

	use FeedLogger;

	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_generator FeedGenerator
	 */
	private static $feed_generator = null;


	/**
	 * Feed File Generator Instance
	 *
	 * @var $feed_registration FeedRegistration
	 */
	private static $feed_registration = null;


	/**
	 * Local Feed Configurations class.
	 *
	 * @var $configurations LocalFeedConfigs
	 */
	private static $configurations = null;

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		if ( ! self::is_product_sync_enabled() && ! FeedRegistration::get_registered_feed_id() ) {
			return;
		}

		$locations = array( Pinterest_For_Woocommerce()::get_base_country() ?? 'US' ); // Replace with multiple countries array for multiple feed config.
		// Start Feed File Generator.
		self::initialize_feed_components( $locations );

		if ( self::is_product_sync_enabled() ) {

			self::reschedule_if_errored();

			/**
			 * Mark feed as needing re-generation whenever a product is edited or changed.
			 */
			add_action( 'edit_post', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );

			if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
				add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
				add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
			}
		} else {
			self::handle_feed_deregistration();
		}
	}

	/**
	 * Initialize the FeedGenerator instance.
	 *
	 * @since x.x.x
	 * @param array $locations Array of location to generate the feed files for.
	 */
	private static function initialize_feed_components( $locations ) {
		$action_scheduler        = new ActionSchedulerProxy();
		self::$configurations    = new LocalFeedConfigs( $locations );
		self::$feed_generator    = new FeedGenerator( $action_scheduler, self::$configurations );
		self::$feed_registration = new FeedRegistration( self::$configurations, self::$feed_generator );
		self::$feed_generator->init();
	}

	/**
	 * Deletes the XML file that is configured in the settings and deletes the feed_job option.
	 *
	 * @return void
	 */
	public static function feed_reset() {

		self::$feed_generator->remove_temporary_feed_files();
		self::$configurations->cleanup_local_feed_configs();
		ProductFeedStatus::feed_transients_cleanup();

		Pinterest_For_Woocommerce()::save_data( 'feed_data_cache', false );

		self::log( 'Product feed reset and files deleted.' );
	}

	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	public static function is_product_sync_enabled() {

		$domain_verified  = Pinterest_For_Woocommerce()::is_domain_verified();
		$tracking_enabled = $domain_verified && Pinterest_For_Woocommerce()::is_tracking_configured();

		return (bool) $domain_verified && $tracking_enabled && Pinterest_For_Woocommerce()::get_setting( 'product_sync_enabled' );
	}

	/**
	 * Handles de-registration of the feed.
	 * $feed_args are needed so that they are passed to update_merchant_feed() in order to perform the update.
	 * Running this, sets the feed to 'DISABLED' in Pinterest, deletes the local XML file and the option holding the feed
	 * status of the feed generation job.
	 *
	 * @return void
	 */
	private static function handle_feed_deregistration() {
		self::feed_reset();
		self::cancel_jobs();
		Pinterest_For_Woocommerce()::save_data( 'feed_registered', false );
	}

	/**
	 * Check if Given ID is of a product and if yes, mark feed as dirty.
	 *
	 * @param integer $product_id The product ID.
	 *
	 * @return void
	 */
	public static function mark_feed_dirty( $product_id ) {
		if ( ! wc_get_product( $product_id ) ) {
			return;
		}

		Pinterest_For_Woocommerce()::save_data( 'feed_dirty', true );
		self::log( 'Feed is dirty.' );
	}

	/**
	 * Check if feed is expired, and reschedule feed generation.
	 *
	 * @return void
	 */
	public static function reschedule_if_errored() {

		$state = ProductFeedStatus::get();

		if ( ( 'error' === $state['status'] && $state['last_activity'] < ( time() - self::WAIT_ON_ERROR_BEFORE_RETRY ) ) ) {
			self::log( 'Retrying feed generation after error.' );
			self::start_feed_generator();
		}
	}

	/**
	 * Cancels the scheduled product sync jobs.
	 *
	 * @return void
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_unschedule_all_actions( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation', array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}
}
