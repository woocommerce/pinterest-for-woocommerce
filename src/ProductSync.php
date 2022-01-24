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
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\API\FeedIssues;
use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;

use \Exception;
use \Throwable;

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

		add_action( 'update_option_' . PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME, array( __class__, 'maybe_deregister' ), 10, 2 );
		if ( ! self::is_product_sync_enabled() ) {
			return;
		}

		self::initialize_feed_components();
		/**
		 * Mark feed as needing re-generation whenever a product is edited or changed.
		 */
		add_action( 'edit_post', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );

		if ( 'yes' === get_option( 'woocommerce_manage_stock' ) ) {
			add_action( 'woocommerce_variation_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
			add_action( 'woocommerce_product_set_stock_status', array( __CLASS__, 'mark_feed_dirty' ), 10, 1 );
		}

		/**
		 * Mark feed as needing re-generation on changes to the woocommerce_hide_out_of_stock_items setting
		 */
		add_action(
			'update_option_woocommerce_hide_out_of_stock_items',
			function () {
				Pinterest_For_Woocommerce()::save_data( 'feed_dirty', true );
				self::log( 'Feed is dirty.' );
			}
		);
	}

	/**
	 * Observe pinterest option change and decide if we need to deregister.
	 *
	 * @since x.x.x
	 *
	 * @param array $old_value Option old value.
	 * @param array $value     Option new value.
	 */
	public static function maybe_deregister( $old_value, $value ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		$product_sync_enabled = $value['product_sync_enabled'] ?? false;

		if ( ! $product_sync_enabled ) {
			self::deregister();
		}
	}

	/**
	 * Initialize components of the synchronization process.
	 *
	 * @since x.x.x
	 */
	private static function initialize_feed_components() {
		$locations               = array( Pinterest_For_Woocommerce()::get_base_country() ?? 'US' ); // Replace with multiple countries array for multiple feed config.
		$action_scheduler        = new ActionSchedulerProxy();
		self::$configurations    = new LocalFeedConfigs( $locations );
		self::$feed_generator    = new FeedGenerator( $action_scheduler, self::$configurations );
		self::$feed_registration = new FeedRegistration( self::$configurations, self::$feed_generator );

		self::$feed_registration->init();
		self::$feed_generator->init();

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
	 *
	 * @return void
	 */
	private static function deregister() {

		self::$feed_generator->deregister();
		self::$configurations->deregister();
		self::$feed_registration->deregister();
		ProductFeedStatus::deregister();
		FeedIssues::deregister();

		self::log( 'Product feed reset and files deleted.' );
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
	public static function handle_feed_registration() {

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
	 * Handles de-registration of the feed.
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
	 * Handles the feed's generation,
	 * Using AS scheduled tasks prints $products_per_step number of products
	 * on each iteration and writes to a file every $products_per_write.
	 *
	 * @return void
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function handle_feed_generation() {

		$state = ProductFeedStatus::get();
		$start = microtime( true );

		if ( $state && 'generated' === $state['status'] || ! self::is_product_sync_enabled() ) {
			return; // No need to perform any action.
		}

		if ( ! $state || ( $state && 'scheduled_for_generation' === $state['status'] ) ) {
			// We need to start a generation from scratch.
			$product_ids = self::get_product_ids_for_feed();

			if ( empty( $product_ids ) ) {
				self::log( 'No products found for feed generation.' );
				return; // No need to perform any action.
			}

			ProductFeedStatus::set(
				array(
					'status'        => 'starting',
					'current_index' => 0,
					'product_count' => count( $product_ids ),
				)
			);

			ProductFeedStatus::store_dataset( $product_ids );
			self::$current_index = 0;
		}

		try {

			if ( 'in_progress' === $state['status'] ) {

				$product_ids         = ProductFeedStatus::retrieve_dataset();
				self::$current_index = $state['current_index'];
				self::$current_index = false === self::$current_index ? self::$current_index : ( (int) self::$current_index ) + 1; // Start on the next item.
			}

			if ( false === self::$current_index || empty( $product_ids ) ) {
				throw new Exception( esc_html__( 'Something went wrong while attempting to generate the feed.', 'pinterest-for-woocommerce' ), 400 );
			}

			$local_feed  = ProductFeedStatus::get_local_feed();
			$target_file = $local_feed['tmp_file'];
			$xml_file    = fopen( $target_file, ( 'in_progress' === $state['status'] ? 'a' : 'w' ) );

			if ( ! $xml_file ) {
				/* Translators: the path of the file */
				throw new Exception( sprintf( esc_html__( 'Could not open file: %s.', 'pinterest-for-woocommerce' ), $target_file ), 400 );
			}

			self::log( 'Generating feed for ' . count( $product_ids ) . ' products' );

			if ( 0 === self::$current_index ) {
				// Write header.
				fwrite( $xml_file, ProductsXmlFeed::get_xml_header() );
			}

			self::$iteration_buffer      = '';
			self::$iteration_buffer_size = 0;
			$step_index                  = 0;
			$products_count              = count( $product_ids );
			$state['products_count']     = $products_count;

			for ( self::$current_index; ( self::$current_index < $products_count ); self::$current_index++ ) {

				$product_id = $product_ids[ self::$current_index ];

				self::$iteration_buffer .= ProductsXmlFeed::get_xml_item( wc_get_product( $product_id ) );
				self::$iteration_buffer_size++;

				if ( self::$iteration_buffer_size >= self::$products_per_write || self::$current_index >= $products_count ) {
					self::write_iteration_buffer( $xml_file, $local_feed );
				}

				$step_index++;

				if ( $step_index >= self::$products_per_step ) {
					break;
				}
			}

			if ( ! empty( self::$iteration_buffer ) ) {
				self::write_iteration_buffer( $xml_file, $state );
			}

			if ( self::$current_index >= $products_count ) {
				// Write footer.
				fwrite( $xml_file, ProductsXmlFeed::get_xml_footer() );
				fclose( $xml_file );

				if ( ! rename( $local_feed['tmp_file'], $local_feed['feed_file'] ) ) {
					/* Translators: the path of the file */
					throw new Exception( sprintf( esc_html__( 'Could not write feed to file: %s.', 'pinterest-for-woocommerce' ), $local_feed['feed_file'] ), 400 );
				}

				$target_file = $local_feed['feed_file'];

				ProductFeedStatus::set( array( 'status' => 'generated' ) );

			} else {
				// We got more products left. Schedule next iteration.
				fclose( $xml_file );
				self::trigger_async_feed_generation( true );
			}

			$end = microtime( true );
			self::log( 'Feed step generation completed in ' . round( ( $end - $start ) * 1000 ) . 'ms. Current Index: ' . self::$current_index . ' / ' . $products_count );
			self::log( 'Wrote ' . $step_index . ' products to file: ' . $target_file );

		} catch ( Throwable $th ) {

			if ( 'error' === $state['status'] ) {
				// Already errored at once. Restart job.
				self::feed_reschedule( true );
				self::log( $th->getMessage(), 'error' );
				self::log( 'Restarting Feed generation.', 'error' );
				return;
			}

			ProductFeedStatus::set(
				array(
					'status'        => 'error',
					'error_message' => $th->getMessage(),
				)
			);

			self::log( $th->getMessage(), 'error' );
		}
	}


	/**
	 * Writes the iteration_buffer to the given file.
	 *
	 * @param resource $xml_file   The file handle.
	 * @param array    $local_feed The array holding the feed attributes.
	 *
	 * @return void
	 *
	 * @throws Exception PHP Exception.
	 */
	private static function write_iteration_buffer( $xml_file, $local_feed ) {

		if ( false !== fwrite( $xml_file, self::$iteration_buffer ) ) {
			self::$iteration_buffer      = '';
			self::$iteration_buffer_size = 0;

			ProductFeedStatus::set(
				array(
					'status'        => 'in_progress',
					'current_index' => self::$current_index,
				)
			);

		} else {
			/* Translators: the path of the file */
			throw new Exception( sprintf( esc_html__( 'Could not write to file: %s.', 'pinterest-for-woocommerce' ), $local_feed['tmp_file'] ), 400 );
		}
	}


	/**
	 * Schedules an async action - if not already scheduled - to generate the feed.
	 *
	 * @param boolean $force When true, overrides the check for already scheduled task.
	 *
	 * @return boolean true if rescheduled, false otherwise.
	 */
	private static function trigger_async_feed_generation( $force = false ) {

		if ( $force || false === as_next_scheduled_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			return 0 !== as_enqueue_async_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
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
		as_enqueue_async_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
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
	 * Stop jobs on deactivation.
	 */
	public static function cancel_jobs() {
		FeedGenerator::cancel_jobs();
		FeedRegistration::cancel_jobs();
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
}
