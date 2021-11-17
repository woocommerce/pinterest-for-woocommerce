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
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;

/**
 * Class Handling feed files generation.
 */
class FeedGenerator extends AbstractChainedJob {

	use BatchQueryOffset;

	/**
	 * FeedGenerator instance;
	 */
	static $instance = null;

	/**
	 * Runs before starting the job.
	 */
	protected function handle_start() {
		// Optionally do something when starting the job.
	}

	/**
	 * Runs after the finishing the job.
	 */
	protected function handle_end() {
		// Optionally do something when ending the job.
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
	 * Process a single item.
	 *
	 * @param string|int|array $item A single item from the get_items_for_batch() method.
	 * @param array            $args The args for the job.
	 *
	 * @throws Exception On error. The failure will be logged by Action Scheduler and the job chain will stop.
	 */
	protected function process_item( $item, array $args ) {
		// Process each item here.
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
		return 'facebook_for_woocommerce';
	}

	public function init(): FeedGenerator {
		if ( is_null( self::$instance ) ) {
			
			self::$instance = new FeedGenerator()
		}
	}

}