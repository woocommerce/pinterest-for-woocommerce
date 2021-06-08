<?php
/**
 * Pinterest For WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling registration & generation of the XML product feed.
 */
class ProductSync {

	const ACTION_HANDLE_SYNC     = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync';
	const ACTION_FEED_GENERATION = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation';

	/**
	 * The number of products to process on each Iteration of the scheduled task.
	 * A high number increases the time it takes to complete the iteration, risking a PHP timeout.
	 *
	 * @var integer
	 */
	private static $products_per_step = 500;

	/**
	 * The number of products to hold in a buffer variable before writing their XML output to a file.
	 * A high number increases memory usage while a low number increases file writes.
	 *
	 * @var integer
	 */
	private static $products_per_write = 100;

	/**
	 * The current index of the loop iterating through feed creation steps.
	 *
	 * @var integer
	 */
	private static $current_index = 0;

	/**
	 * The buffer used to hold XML markup to be printed to the XML file.
	 *
	 * @var string
	 */
	private static $iteration_buffer = '';

	/**
	 * The number of products the iteration_buffer currently has
	 *
	 * @var integer
	 */
	private static $iteration_buffer_size = 0;

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		// Schedule required actions.
		add_action( self::ACTION_HANDLE_SYNC, array( __CLASS__, 'handle_feed_registration' ) );
		add_action( self::ACTION_FEED_GENERATION, array( __CLASS__, 'handle_feed_generation' ) );

		// ACTION_HANDLE_SYNC task handles both registration and de-registration.
		if ( self::is_product_sync_enabled() || self::is_feed_registered() ) {

			if ( false === as_next_scheduled_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				$interval = 10 * MINUTE_IN_SECONDS;

				as_schedule_recurring_action( time() + $interval, $interval, self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
			}
		}

		if ( self::is_product_sync_enabled() ) {
			$state = self::feed_job_status();

			if ( $state && 'scheduled_for_generation' === $state['status'] ) {
				self::trigger_async_feed_generation();
			}
		}
	}


	/**
	 * Deletes the XML file that is configured in the settings and deletes the feed_job option.
	 *
	 * @return void
	 */
	public static function feed_reset() {

		$state = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );

		if ( file_exists( $state['feed_file'] ) ) {
			unlink( $state['feed_file'] );
		}

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', false );
		Pinterest_For_Woocommerce()::save_setting( 'feed_data_cache', false );

		self::log( 'Product Feed reset and file deleted.' );
	}


	/**
	 * Schedules the regeneration process of the XML feed.
	 *
	 * @return void
	 */
	public static function feed_reschedule() {

		$feed_job           = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );
		$feed_job           = $feed_job ? $feed_job : array();
		$feed_job['status'] = 'scheduled_for_generation';

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', $feed_job );
		self::trigger_async_feed_generation();

		self::log( 'Feed generation (re)scheduled.' );
	}


	/**
	 * Logs Sync related messages separately.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level   The level of the message.
	 *
	 * @return void
	 */
	private static function log( $message, $level = 'debug' ) {
		Logger::log( $message, $level, 'product-sync' );
	}


	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	private static function is_product_sync_enabled() {

		$domain_verified  = Pinterest_For_Woocommerce()::is_domain_verified();
		$tracking_enabled = Pinterest_For_Woocommerce()::is_tracking_enabled();

		return (bool) Pinterest_For_Woocommerce()::get_setting( 'product_sync_enabled' ) && $domain_verified && $tracking_enabled;
	}


	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	private static function is_feed_registered() {
		return Pinterest_For_Woocommerce()::get_setting( 'feed_registered' );
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
	public static function handle_feed_registration() {

		$state         = self::feed_job_status( 'check_registration' );
		$force_new_reg = false;

		$feed_args = array(
			'feed_location'             => $state['feed_url'],
			'feed_format'               => 'XML',
			'feed_default_currency'     => get_woocommerce_currency(),
			'default_availability_type' => 'IN_STOCK',
		);

		$registered = self::is_feed_registered();

		if ( ! self::is_product_sync_enabled() ) {
			// Handle feed deregistration.
			if ( $registered ) {
				self::handle_feed_deregistration();
			}

			return false;
		}

		try {

			if ( empty( $registered ) || $force_new_reg ) {
				$registered = self::register_feed( $feed_args );
			}

			if ( $registered ) {

				$expired = ( 'generated' === $state['status'] && $state['finished'] < ( time() - DAY_IN_SECONDS ) );

				// If local is not generated, or is older than X , schedule regeneration.
				if ( 'starting' === $state['status'] || 'in_progress' === $state['status'] ) {
					self::trigger_async_feed_generation();
				} elseif ( $expired || 'pending_config' === $state['status'] ) {
					self::feed_reschedule();
				}

				return true;
			}

			throw new \Exception( esc_html__( 'Could not register feed.', 'pinterest-for-woocommerce' ) );

		} catch ( \Throwable $th ) {
			self::log( $th->getMessage(), 'error' );
			return false;
		}

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
		Pinterest_For_Woocommerce()::save_setting( 'feed_registered', false );

		self::feed_reset();
	}


	/**
	 * Schedules an async action - if not already scheduled - to generate the feed.
	 *
	 * @param boolean $force When true, overrides the check for already scheduled task.
	 *
	 * @return void
	 */
	private static function trigger_async_feed_generation( $force = false ) {

		if ( $force || false === as_next_scheduled_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_enqueue_async_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
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
	private static function register_feed( $feed_args ) {

		// Get merchant object.
		$merchant   = self::get_merchant( $feed_args );
		$registered = false;

		if ( ! empty( $merchant['data']->id ) && 'declined' === $merchant['data']->product_pin_approval_status ) {
			$registered = false;
			self::log( 'Pinterest returned a Declined status for product_pin_approval_status' );
		} elseif ( ! empty( $merchant['data']->id ) && ! isset( $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) ) {
			// No feed registered, but we got a merchant.
			$merchant = API\Base::add_merchant_feed( $merchant['data']->id, $feed_args );

			if ( $merchant && 'success' === $merchant['status'] && isset( $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) ) {
				$registered = $merchant['data']->product_pin_feed_profile->id;
				self::log( 'Added merchant feed: ' . $feed_args['feed_location'] );
			}
		} elseif ( $feed_args['feed_location'] === $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) {
			// Feed registered.
			$registered = $merchant['data']->product_pin_feed_profile->id;
			self::log( 'Feed registered for merchant: ' . $feed_args['feed_location'] );
		} else {
			// A diff feed was registered. Update to the current one.
			$feed = API\Base::update_merchant_feed( $merchant['data']->product_pin_feed_profile->merchant_id, $merchant['data']->product_pin_feed_profile->id, $feed_args );

			if ( $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location ) ) {
				$registered = $feed['data']->id;
				self::log( 'Merchant\'s feed updated to current location: ' . $feed_args['feed_location'] );
			}
		}

		Pinterest_For_Woocommerce()::save_setting( 'feed_registered', $registered );
		Pinterest_For_Woocommerce()::save_setting( 'merchant_id', $merchant['data']->id );

		return $registered;
	}


	/**
	 * Returns the merchant object for the current user.
	 * If a merchant alreyad
	 *
	 * @param array $feed_args The arguments used to create the feed.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	private static function get_merchant( $feed_args ) {

		$merchant    = false;
		$merchant_id = Pinterest_For_Woocommerce()::get_setting( 'merchant_id' );

		if ( empty( $merchant_id ) ) {
			// Get merchant from advertiser object.
			$advertisers = API\Base::get_advertisers();

			if ( 'success' !== $advertisers['status'] ) {
				throw new \Exception( esc_html__( 'Response error when trying to get advertisers.', 'pinterest-for-woocommerce' ), 400 );
			}

			$advertiser = reset( $advertisers['data'] ); // All advertisers assigned to a user share the same merchant_id.

			if ( ! empty( $advertiser->merchant_id ) ) {
				$merchant_id = $advertiser->merchant_id;
				self::log( 'Got merchant Id from user\'s advertisers: ' . $merchant_id );
			}
		}

		if ( ! empty( $merchant_id ) ) {

			try {
				$merchant = API\Base::get_merchant( $merchant_id );
			} catch ( \Throwable $th ) {
				$merchant = false;
			}
		}

		if ( ! $merchant || ( 'success' !== $merchant['status'] && 650 === $merchant['code'] ) ) {  // https://developers.pinterest.com/docs/redoc/#tag/API-Response-Codes Merchant not found 650.
			// Try creating one.
			$merchant = API\Base::maybe_create_merchant( $feed_args );
		}

		if ( 'success' !== $merchant['status'] ) {
			throw new \Exception( esc_html__( 'Response error when trying create a merchant or get the existing one.', 'pinterest-for-woocommerce' ), 400 );
		}

		return $merchant;
	}


	/**
	 * Gets the Array of IDs for the products that are to be printed to the
	 * XML feed, based on the current settings.
	 *
	 * @return array
	 */
	private static function get_product_ids_for_feed() {

		$product_ids = wc_get_products(
			array(
				'limit'  => -1,
				'return' => 'ids',
			)
		);

		if ( empty( $product_ids ) ) {
			return array();
		}

		$products_count = count( $product_ids );

		// Get Variations of the current product and inject them into our array.
		for ( $i = 0; $i < $products_count; $i++ ) {

			$type = \WC_Product_Factory::get_product_type( $product_ids[ $i ] );

			if ( in_array( $type, array( 'variable' ), true ) ) {

				$product  = wc_get_product( $product_ids[ $i ] );
				$children = $product->get_children();

				array_splice( $product_ids, $i + 1, 0, $children );

				$i = $i + count( $children );

				$products_count = $products_count + count( $children );
			}
		}

		return $product_ids;
	}


	/**
	 * Handles the feed's generation,
	 * Using AS scheduled tasks prints $products_per_step number of products
	 * on each iteration and writes to a file every $products_per_write.
	 *
	 * @return void
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function handle_feed_generation() {

		$state = self::feed_job_status();
		$start = microtime( true );

		if ( $state && 'generated' === $state['status'] ) {
			return; // No need to perform any action.
		}

		if ( ! $state || ( $state && 'scheduled_for_generation' === $state['status'] ) ) {
			// We need to start a generation from scratch.
			$product_ids = self::get_product_ids_for_feed();

			if ( empty( $product_ids ) ) {
				self::log( 'No products found for feed generation.' );
				return; // No need to perform any action.
			}

			$state = self::feed_job_status(
				'starting',
				array(
					'dataset'       => $product_ids,
					'current_index' => 0,
				)
			);

			self::$current_index = 0;
		}

		try {

			if ( 'in_progress' === $state['status'] ) {
				$product_ids         = get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state['job_id'] );
				self::$current_index = get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state['job_id'] );

				if ( false === self::$current_index || empty( $product_ids ) ) {
					throw new \Exception( esc_html__( 'Something went wrong while attempting to generated the feed.', 'pinterest-for-woocommerce' ), 400 );
				}

				self::$current_index = ( (int) self::$current_index ) + 1; // Start on the next item.
			}

			$xml_file = fopen( $state['feed_file'], ( 'in_progress' === $state['status'] ? 'a' : 'w' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.

			if ( ! $xml_file ) {
				/* Translators: the path of the file */
				throw new \Exception( sprintf( esc_html__( 'Could not open file: %s.', 'pinterest-for-woocommerce' ), $state['feed_file'] ), 400 );
			}

			self::log( 'Generating feed for ' . count( $product_ids ) . ' products' );

			if ( 0 === self::$current_index ) {
				// Write header.
				fwrite( $xml_file, ProductsXmlFeed::get_xml_header() ); // phpcs:ignore WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
			}

			self::$iteration_buffer      = '';
			self::$iteration_buffer_size = 0;
			$step_index                  = 0;
			$products_count              = count( $product_ids );

			for ( self::$current_index; ( self::$current_index < $products_count ); self::$current_index++ ) {

				$product_id = $product_ids[ self::$current_index ];

				self::$iteration_buffer .= ProductsXmlFeed::get_xml_item( wc_get_product( $product_id ) );
				self::$iteration_buffer_size++;

				if ( self::$iteration_buffer_size >= self::$products_per_write || self::$current_index >= $products_count ) {
					self::write_iteration_buffer( $xml_file, $state );
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
				fwrite( $xml_file, ProductsXmlFeed::get_xml_footer() ); // phpcs:ignore WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.

				self::feed_job_status( 'generated' );
			} else {
				// We got more products left. Schedule next iteration.
				self::trigger_async_feed_generation( true );
			}

			fclose( $xml_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.

			$end = microtime( true );
			self::log( 'Feed step generation completed in ' . round( ( $end - $start ) * 1000 ) . 'ms. Current Index: ' . self::$current_index . ' / ' . $products_count );
			self::log( 'Wrote ' . $step_index . ' products to file: ' . $state['feed_file'] );

		} catch ( \Throwable $th ) {
			self::log( $th->getMessage(), 'error' );
		}

	}


	/**
	 * Writes the iteration_buffer to the given file.
	 *
	 * @param resource $xml_file The file handle.
	 * @param array    $state    The array holding the feed_state values.
	 *
	 * @return void
	 *
	 * @throws \Exception PHP Exception.
	 */
	private static function write_iteration_buffer( $xml_file, $state ) {

		if ( false !== fwrite( $xml_file, self::$iteration_buffer ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
			self::$iteration_buffer      = '';
			self::$iteration_buffer_size = 0;

			self::feed_job_status(
				'in_progress',
				array(
					'current_index' => self::$current_index,
				)
			);

		} else {
			/* Translators: the path of the file */
			throw new \Exception( sprintf( esc_html__( 'Could not write to file: %s.', 'pinterest-for-woocommerce' ), $state['feed_file'] ), 400 );
		}
	}


	/**
	 * Saves or returns the Current state of the Feed generation job.
	 * Status can be one of the following:
	 *
	 * - starting                 The feed job is being initialized. A new JobID will be assigned if none exists.
	 * - check_registration       If a JobID already exists, it is returned, otherwise a new one will be assigned.
	 * - in_progress              Signifies that we are between iterations and generating the feed.
	 * - generated                The feed is generated, no further action will be taken.
	 * - scheduled_for_generation The feed needs to be (re)generated. If this status is set, the next run of __CLASS__::handle_feed_generation() will start the generation process.
	 * - pending_config           The feed was reset or was never configured.
	 *
	 * @param string $status The status of the feed's generation process. See above.
	 * @param array  $args   The arguments that go along with the given status.
	 *
	 * @return array
	 */
	public static function feed_job_status( $status = null, $args = null ) {

		$state_data = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );

		if ( is_null( $status ) || ( ! is_null( $status ) && 'check_registration' === $status && ! empty( $state_data['job_id'] ) ) ) {
			return $state_data;
		}

		if ( ! isset( $state_data['status'] ) ) {
			$state_data['status'] = 'pending_config';
		}

		$state_data     = empty( $state_data ) ? array() : $state_data;
		$initial_status = $state_data['status'];

		if ( 'starting' === $status || 'check_registration' === $status ) {

			if ( 'check_registration' !== $status ) {
				$state_data['status']  = $status;
				$state_data['started'] = time();
			}

			if ( empty( $state_data['job_id'] ) ) {
				$state_data['job_id'] = wp_generate_password( 6, false, false );
			}

			$upload_dir = wp_get_upload_dir();

			$state_data['feed_file'] = trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $state_data['job_id'] . '.xml';
			$state_data['feed_url']  = trailingslashit( $upload_dir['baseurl'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $state_data['job_id'] . '.xml';

			if ( isset( $args, $args['dataset'] ) ) {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state_data['job_id'], $args['dataset'], DAY_IN_SECONDS );
			}
		} elseif ( 'in_progress' === $status ) {
			$state_data['status'] = 'in_progress';

			if ( isset( $args, $args['current_index'] ) ) {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state_data['job_id'], $args['current_index'], DAY_IN_SECONDS );
			}
		} elseif ( 'generated' === $status ) {
			$state_data['status']   = 'generated';
			$state_data['finished'] = time();

			// Cleanup.
			delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state_data['job_id'] );
			delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state_data['job_id'] );
		}

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', $state_data );

		if ( $initial_status !== $state_data['status'] ) {
			self::log( 'Feed status set to: ' . $state_data['status'] );
		}

		return $state_data;

	}
}
