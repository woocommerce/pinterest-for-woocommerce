<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliable append to an existing file.
/**
 * Pinterest for WooCommerce Feed Files Generator
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     x.x.x
 */
namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset;
use Automattic\WooCommerce\ActionSchedulerJobFramework\AbstractChainedJob;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\Utilities\FeedLogger;
/**
 * Class Handling feed files generation.
 */
class FeedGenerator extends AbstractChainedJob {

	use BatchQueryOffset;
	use FeedLogger;

	const ACTION_START_FEED_GENERATOR = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-start-feed-generation';

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
		$this->prepare_feed_buffers( $local_feeds_configurations );

		add_action( self::ACTION_START_FEED_GENERATOR, array( __CLASS__, 'start_generation' ) );
		if ( false === as_next_scheduled_action( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			as_schedule_recurring_action( time(), DAY_IN_SECONDS, self::ACTION_START_FEED_GENERATOR );
		}
	}

	public function reschedule_next_generator_start( $time ) {
		as_unschedule_action( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		as_schedule_recurring_action( $time, DAY_IN_SECONDS, self::ACTION_START_FEED_GENERATOR );
	}

	/**
	 * Start the queue processing.
	 */
	public function start_generation() {
		if ( ! $this->is_running() ) {
			$this->queue_start();
		}
		ProductFeedStatus::set( array( 'status' => 'scheduled_for_generation' ) );
	}

	/**
	 * Runs as the first step of the generation process.
	 */
	protected function handle_start() {
		$this->prepare_temporary_files();
		ProductFeedStatus::set(
			array(
				'status'        => 'in_progress',
				'product_count' => 0,
			)
		);
	}

	/**
	 * Runs as the last step of the job.
	 */
	protected function handle_end() {
		$this->add_footer_to_temporary_feed_files();
		$this->rename_temporary_feed_files_to_final();
		ProductFeedStatus::set( array( 'status' => 'generated' ) );

		// Check if feed is dirty, reschedule if yes.
		if ( Pinterest_For_Woocommerce()::get_data( 'feed_dirty' ) ) {
			Pinterest_For_Woocommerce()::save_data( 'feed_dirty', false );

			$this->reschedule_next_generator_start( time() + 10 );
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
	 * @since 1.1.0
	 *
	 * @param array $items The items of the current batch.
	 * @param array $args  The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function process_items( array $items, array $args ) {
		$excluded_product_types = apply_filters(
			'pinterest_for_woocommerce_excluded_product_types',
			array(
				'grouped',
			)
		);

		$types = array_diff( array_merge( array_keys( wc_get_product_types() ) ), $excluded_product_types );

		$products = wc_get_products(
			array(
				'type'    => $types,
				'include' => $items,
				'orderby' => 'none',
				'limit'   => $this->get_batch_size(),
			)
		);

		array_walk(
			$products,
			function ( $product ) {
				foreach ( $this->get_locations() as $location ) {
					$this->buffers[ $location ] .= ProductsXmlFeed::get_xml_item( $product, $location );
				}
			}
		);

		$this->write_buffers_to_temp_files();
		$count = ProductFeedStatus::get()['product_count'] ?? 0;
		ProductFeedStatus::set(
			array(
				'product_count' => $count + count( $products ),
			)
		);
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
	 */
	public function prepare_temporary_files(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				ProductsXmlFeed::get_xml_header()
			);

			if ( false === $bytes_written ) {
				// Add debug loggign
			}
		}
	}

	public function add_footer_to_temporary_feed_files(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				ProductsXmlFeed::get_xml_footer(),
				FILE_APPEND
			);

			if ( false === $bytes_written ) {
				// Add debug loggign
			}
		}
	}

	public function rename_temporary_feed_files_to_final(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			rename( $config['tmp_file'], $config['feed_file'] );
			// Check success and add logging.
		}
	}

	public function remove_temporary_feed_files(): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			if ( isset( $config['feed_file'] ) && file_exists( $config['feed_file'] ) ) {
				unlink( $config['feed_file'] );
			}

			if ( isset( $config['tmp_file'] ) && file_exists( $config['tmp_file'] ) ) {
				unlink( $config['tmp_file'] );
			}
		}
	}

	private function write_to_each_temporary_files( $function, $flags = 0 ): void {
		foreach ( $this->configurations->get_configurations() as $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				$function(),
				$flags
			);

			if ( false === $bytes_written ) {
				// Add debug loggign
			}
		}
	}

	private function write_buffers_to_temp_files() {
		foreach ( $this->configurations->get_configurations() as $location => $config ) {
			$bytes_written = file_put_contents(
				$config['tmp_file'],
				$this->buffers[ $location ],
				FILE_APPEND
			);

			if ( false === $bytes_written ) {
				// Add debug loggign
			}
		}
	}

	private function check_if_feed_file_exists() {
		$config = reset( $this->configurations->get_configurations() );
		if ( false === $config ) {
			return false;
		}
		return isset( $config['feed_file'] ) && file_exists( $config['feed_file'] );
	}

	/**
	 * Create empty string buffers for
	 */
	private function prepare_feed_buffers( $local_feed_configurations ) {
		foreach ( $this->get_locations() as $location ) {
			$this->buffers[ $location ] = '';
		}
	}

	/**
	 * Fetch supported locations.
	 */
	private function get_locations() {
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
