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
use WP_Query;

/**
 * Class Handling feed files generation.
 */
class FeedGenerator extends AbstractChainedJob {

	use BatchQueryOffset;

	/**
	 * Local Feed Configurations class.
	 *
	 * @var LocalFeedConfigs of local feed configurations;
	 */
	private $local_feeds_configurations;

	/**
	 * FeedGenerator initialization.
	 *
	 * @since x.x.x
	 * @param ActionSchedulerInterface $action_scheduler           Action Scheduler proxy.
	 * @param LocalFeedConfigs         $local_feeds_configurations Locations configuration class.
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, $local_feeds_configurations ) {
		parent::__construct( $action_scheduler );
		$this->local_feeds_configurations = $local_feeds_configurations;
	}

	/**
	 * Runs before starting the job.
	 */
	protected function handle_start() {
		$this->prepare_temporary_files();
	}

	/**
	 * Runs after the finishing the job.
	 */
	protected function handle_end() {
		$this->add_footer_to_temporary_feed_files();
		$this->rename_temporary_feed_files_to_final();
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
		$product_args = [
			'fields'         => 'ids',
			'post_status'    => 'publish',
			'post_type'      => [ 'product', 'product_variation' ],
			'posts_per_page' => $this->get_batch_size(),
			'offset'         => $this->get_query_offset( $batch_number ),
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		$query = new WP_Query( $product_args );
		return $query->posts;
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
		foreach ( $this->local_feeds_configurations->get_configurations() as $config ) {
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
		foreach ( $this->local_feeds_configurations->get_configurations() as $config ) {
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
		foreach ( $this->local_feeds_configurations->get_configurations() as $config ) {
			rename( $config['tmp_file'], $config['feed_file'] );
			// Check success and add logging.
		}
	}

	private function write_to_each_temporary_files( $function, $flags = 0 ): void {
		foreach ( $this->local_feeds_configurations->get_configurations() as $config ) {
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
