<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Feed Files Generator
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset;
use Automattic\WooCommerce\ActionSchedulerJobFramework\AbstractChainedJob;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;
use Exception;

/**
 * Class Handling feed files generation.
 */
class FeedGenerator extends AbstractChainedJob {

	use BatchQueryOffset;
	use FeedLogger;

	const ACTION_START_FEED_GENERATOR = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-start-feed-generation';

	/**
	 * The time in seconds to wait after a failed feed generation attempt,
	 * before attempting a retry.
	 */
	const WAIT_ON_ERROR_BEFORE_RETRY = HOUR_IN_SECONDS;

	/**
	 * Local Feed Configurations class.
	 *
	 * @var LocalFeedConfigs of local feed configurations;
	 */
	private $configurations;


	/**
	 * Location buffers. On buffer for each local feed configuration.
	 * We write to a buffer to limit the number disk writes.
	 *
	 * @var array $buffers Array of feed buffers.
	 */
	private $buffers = array();

	/**
	 * FeedGenerator initialization.
	 *
	 * @since x.x.x
	 * @param ActionSchedulerInterface $action_scheduler           Action Scheduler proxy.
	 * @param LocalFeedConfigs         $local_feeds_configurations Locations configuration class.
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, $local_feeds_configurations ) {
		parent::__construct( $action_scheduler );
		$this->configurations = $local_feeds_configurations;
	}

	/**
	 * Initialize FeedGenerator actions and Action Scheduler hooks.
	 *
	 * @since x.x.x
	 */
	public function init() {
		add_action(
			self::ACTION_START_FEED_GENERATOR,
			function () {
				$this->start_generation();
			}
		);

		if ( false === as_has_scheduled_action( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			$this->schedule_next_generator_start( time() );
		}
	}

	/**
	 * Reschedule the next feed generator start.
	 *
	 * @since x.x.x
	 * @param integer $timestamp Next feed generator timestamp.
	 */
	public function schedule_next_generator_start( $timestamp ) {
		as_unschedule_all_actions( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_schedule_recurring_action( $timestamp, DAY_IN_SECONDS, self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		/* translators: time in the format hours:minutes:seconds */
		self::log( sprintf( __( 'Feed scheduled to run at %s.', 'pinterest-for-woocommerce' ), gmdate( 'H:i:s', $timestamp ) ) );
	}

	/**
	 * Stop feed generator jobs.
	 */
	public static function cancel_jobs() {
		as_unschedule_all_actions( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}

	/**
	 * Start the queue processing.
	 *
	 * @since x.x.x
	 */
	private function start_generation() {
		if ( $this->is_running() ) {
			return;
		}

		$this->queue_start();
		ProductFeedStatus::set( array( 'status' => 'scheduled_for_generation' ) );
		self::log( __( 'Feed generation queued.', 'pinterest-for-woocommerce' ) );
	}

	/**
	 * Runs as the first step of the generation process.
	 *
	 * @since x.x.x
	 *
	 * @throws \Throwable Related to issues possible when creating an empty feed temp file and populating the header.
	 */
	protected function handle_start() {
		self::log( __( 'Feed generation start. Preparing temporary files.', 'pinterest-for-woocommerce' ) );
		try {
			$this->prepare_temporary_files();
			ProductFeedStatus::set(
				array(
					'status'        => 'in_progress',
					'product_count' => 0,
				)
			);
		} catch ( \Throwable $th ) {
			$this->handle_error( $th );
			throw $th;
		}
	}

	/**
	 * Runs as the last step of the job.
	 * Add XML footer to the feed files and copy the move the files from tmp to the final destination.
	 *
	 * @since x.x.x
	 *
	 * @throws \Throwable Related to issues possible when adding the footer or renaming the files.
	 */
	protected function handle_end() {
		self::log( __( 'Feed generation end. Moving files to the final destination.', 'pinterest-for-woocommerce' ) );
		try {
			$this->add_footer_to_temporary_feed_files();
			$this->rename_temporary_feed_files_to_final();
		} catch ( \Throwable $th ) {
			$this->handle_error( $th );
			throw $th;
		}
		ProductFeedStatus::set( array( 'status' => 'generated' ) );
		self::log( __( 'Feed generated successfully.', 'pinterest-for-woocommerce' ) );
		// Check if feed is dirty and reschedule in necessary.
		if ( Pinterest_For_Woocommerce()::get_data( 'feed_dirty' ) ) {
			Pinterest_For_Woocommerce()::save_data( 'feed_dirty', false );
			$this->schedule_next_generator_start( time() );
		}
	}

	/**
	 * Get a set of items for the batch.
	 *
	 * NOTE: when using an OFFSET based query to retrieve items it's recommended to order by the item ID while
	 * ASCENDING. This is so that any newly added items will not disrupt the query offset.
	 *
	 * @param int   $batch_number The batch number increments for each new batch in the job cycle.
	 * @param array $args         The args for the job.
	 *
	 * @return array Items ids.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		global $wpdb;

		$product_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post.ID
				FROM {$wpdb->posts} as post
				LEFT JOIN {$wpdb->posts} as parent ON post.post_parent = parent.ID
				WHERE
					( post.post_type = 'product_variation' AND parent.post_status = 'publish' )
				OR
					( post.post_type = 'product' AND post.post_status = 'publish' )
				ORDER BY post.ID ASC
				LIMIT %d OFFSET %d",
				$this->get_batch_size(),
				$this->get_query_offset( $batch_number )
			)
		);

		return array_map( 'intval', $product_ids );
	}

	/**
	 * Processes a batch of items. The middle part of the generation process.
	 * Can run multiple times depending on the catalog size.
	 *
	 * @since x.x.x
	 *
	 * @param array $items The items of the current batch.
	 * @param array $args  The args for the job.
	 *
	 * @throws \Throwable On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function process_items( array $items, array $args ) {
		try {
			/**
			 * Prepare allowed product types.
			 * For variations:
			 * We just want variations ( variable children ) but not parents ( variable ).
			 */
			$included_product_types = array_merge( array_keys( wc_get_product_types() ), array( 'variation' ) );
			$excluded_product_types = apply_filters(
				'pinterest_for_woocommerce_excluded_product_types',
				array(
					'grouped',
					'variable',
				)
			);

			$types = array_diff( $included_product_types, $excluded_product_types );

			$products_query_args = array(
				'type'       => $types,
				'include'    => $items,
				'visibility' => 'catalog',
				'orderby'    => 'none',
				'limit'      => $this->get_batch_size(),
			);

			// Do not sync out of stock products if woocommerce_hide_out_of_stock_items is set.
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$products_query_args['stock_status'] = 'instock';
			}

			$products = wc_get_products( $products_query_args );

			$this->prepare_feed_buffers();

			array_walk(
				$products,
				function ( $product ) {
					foreach ( $this->get_locations() as $location ) {
						$this->buffers[ $location ] .= ProductsXmlFeed::get_xml_item( $product, $location );
					}
				}
			);

			$this->write_buffers_to_temp_files();

		} catch ( \Throwable $th ) {
			$this->handle_error( $th );
			throw $th;
		}

		$count = ProductFeedStatus::get()['product_count'] ?? 0;
		ProductFeedStatus::set(
			array(
				'product_count' => $count + count( $products ),
			)
		);
		/* translators: number of products */
		self::log( sprintf( __( 'Feed batch generated. Wrote %s products to the feed file.', 'pinterest-for-woocommerce' ), count( $products ) ) );
	}

	/**
	 * Get the name/slug of the job.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'generate_feed';
	}

	/**
	 * Get the name/slug of the plugin that owns the job.
	 *
	 * @return string
	 */
	public function get_plugin_name(): string {
		return 'pinterest';
	}

	/**
	 * Prepare a fresh temporary file for each local configuration.
	 * Files is populated with the XML headers.
	 *
	 * @since x.x.x
	 */
	private function prepare_temporary_files(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				ProductsXmlFeed::get_xml_header()
			);

			$this->check_write_for_io_errors( $bytes_written, $config['tmp_file'] );
		}
	}

	/**
	 * Add XML footer to all of the temporary feed files.
	 *
	 * @since x.x.x
	 */
	private function add_footer_to_temporary_feed_files(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				ProductsXmlFeed::get_xml_footer(),
				FILE_APPEND
			);

			$this->check_write_for_io_errors( $bytes_written, $config['tmp_file'] );
		}
	}

	/**
	 * Checks the status of the file write operation and throws if issues are found.
	 * Utility function for functions using file_put_contents.
	 *
	 * @since x.x.x
	 * @param integer $bytes_written How much data was written to the file.
	 * @param string  $file          File location.
	 *
	 * @throws Exception Can't open or write to the file.
	 */
	private function check_write_for_io_errors( $bytes_written, $file ): void {

		if ( false === $bytes_written ) {
			throw new Exception(
				sprintf(
					/* translators: error message with file path */
					__( 'Could not open temporary file %s for writing', 'pinterest-for-woocommerce' ),
					$file
				)
			);
		}

		if ( 0 === $bytes_written ) {
			throw new Exception(
				sprintf(
					/* translators: error message with file path */
					__( 'Temporary file: %s is not writeable.', 'pinterest-for-woocommerce' ),
					$file
				)
			);
		}
	}

	/**
	 * React to errors during feed files generation process.
	 *
	 * @since x.x.x
	 * @param \Throwable $th Exception handled.
	 */
	private function handle_error( $th ) {
		ProductFeedStatus::set(
			array(
				'status'        => 'error',
				'error_message' => $th->getMessage(),
			)
		);

		self::log( $th->getMessage(), 'error' );
		$this->schedule_next_generator_start( time() + self::WAIT_ON_ERROR_BEFORE_RETRY );
	}

	/**
	 * Rename temporary feed files to final name.
	 * This is the last step of the feed file generation process.
	 *
	 * @since x.x.x
	 * @throws \Exception Renaming not possible.
	 */
	private function rename_temporary_feed_files_to_final(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$status = rename( $config['tmp_file'], $config['feed_file'] );
			if ( false === $status ) {
				throw new Exception(
					sprintf(
						/* translators: 1: temporary file name 2: final file name */
						__( 'Could not rename %1$s to %2$s', 'pinterest-for-woocommerce' ),
						$config['tmp_file'],
						$config['feed_file']
					)
				);
			}
		}
	}

	/**
	 * Remove feed files and cancel pending actions.
	 * Part of the cleanup procedure.
	 *
	 * @since x.x.x
	 */
	public function deregister(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			if ( isset( $config['feed_file'] ) && file_exists( $config['feed_file'] ) ) {
				unlink( $config['feed_file'] );
			}

			if ( isset( $config['tmp_file'] ) && file_exists( $config['tmp_file'] ) ) {
				unlink( $config['tmp_file'] );
			}
		}
		as_unschedule_all_actions( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}

	/**
	 * Write pre-populated buffers to feed files.
	 *
	 * @since x.x.x
	 */
	private function write_buffers_to_temp_files(): void {
		foreach ( $this->configurations->get_configurations() as $location => $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				$this->buffers[ $location ],
				FILE_APPEND
			);

			$this->check_write_for_io_errors( $bytes_written, $config['tmp_file'] );
		}
	}

	/**
	 * Check if we have a feed file on the disk.
	 *
	 * @since x.x.x
	 */
	public function check_if_feed_file_exists() {
		$configs = $this->configurations->get_configurations();
		$config  = reset( $configs );
		if ( false === $config ) {
			return false;
		}
		return isset( $config['feed_file'] ) && file_exists( $config['feed_file'] );
	}

	/**
	 * Create empty string buffers for
	 *
	 * @since x.x.x
	 */
	private function prepare_feed_buffers(): void {
		foreach ( $this->get_locations() as $location ) {
			$this->buffers[ $location ] = '';
		}
	}

	/**
	 * Fetch supported locations.
	 *
	 * @since x.x.x
	 */
	private function get_locations(): array {
		return array_keys( $this->configurations->get_configurations() );
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int
	 */
	protected function get_batch_size(): int {
		return 100;
	}

	/**
	 * Not used.
	 * Process a single item. Added to satisfy abstract interface definition in the framework.
	 * We use process_items instead.
	 *
	 * @param string|int|array $item A single item from the get_items_for_batch() method.
	 * @param array            $args The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function process_item( $item, array $args ) {
		// Process each item here.
	}
}
