<?php //phpcs:disable WordPress.WP.AlternativeFunctions --- Uses FS read/write in order to reliably append to an existing file.
/**
 * Pinterest for WooCommerce Feed Files Generator
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\ActionSchedulerJobFramework\Utilities\BatchQueryOffset;
use Automattic\WooCommerce\ActionSchedulerJobFramework\AbstractChainedJob;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\Exception\FeedCircuitBreakerException;
use Automattic\WooCommerce\Pinterest\Exception\FeedFileOperationsException;
use Automattic\WooCommerce\Pinterest\Notes\FeedCircuitBreakerNote;
use Automattic\WooCommerce\Pinterest\Utilities\ProductFeedLogger;
use ActionScheduler;
use Exception;
use Pinterest_For_Woocommerce;
use Throwable;

/**
 * Class Handling feed files generation.
 */
class FeedGenerator extends AbstractChainedJob {

	use BatchQueryOffset;
	use ProductFeedLogger;

	const ACTION_START_FEED_GENERATOR = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-start-feed-generation';

	/**
	 * The time in seconds to wait after a failed feed generation attempt,
	 * before attempting a retry.
	 */
	const WAIT_ON_ERROR_BEFORE_RETRY = HOUR_IN_SECONDS;

	/**
	 * The max number of retries per batch before aborting the generation process.
	 */
	const MAX_RETRIES_PER_BATCH = 2;

	/**
	 * The max number of batches to process in a single generation cycle.
	 * Circuit breaker to prevent runaway scheduling and database bloat.
	 */
	const MAX_BATCHES_PER_CYCLE = 1000;

	public const DEFAULT_PRODUCT_BATCH_SIZE = 100;

	/**
	 * Feed file operations class.
	 *
	 * @var FeedFileOperations
	 */
	private $feed_file_operations;

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
	 * Pending last batch ID to be committed after successful processing.
	 * Prevents cursor advancement before batch processing completes.
	 *
	 * @var int|null $pending_last_batch_id
	 */
	private $pending_last_batch_id = null;

	/**
	 * FeedGenerator initialization.
	 *
	 * @since 1.0.10
	 * @param ActionSchedulerInterface $action_scheduler           Action Scheduler proxy.
	 * @param FeedFileOperations       $feed_file_operations       Feed file operations.
	 * @param LocalFeedConfigs         $local_feeds_configurations Locations configuration class.
	 */
	public function __construct( ActionSchedulerInterface $action_scheduler, FeedFileOperations $feed_file_operations, $local_feeds_configurations ) {
		parent::__construct( $action_scheduler );
		$this->feed_file_operations = $feed_file_operations;
		$this->configurations       = $local_feeds_configurations;
	}

	/**
	 * Initialize FeedGenerator actions and Action Scheduler hooks.
	 *
	 * @since 1.0.10
	 */
	public function init() {
		// Initialize the action handlers.
		parent::init();

		add_action(
			self::ACTION_START_FEED_GENERATOR,
			function () {
				$this->start_generation();
			}
		);

		if ( false === as_has_scheduled_action( self::ACTION_START_FEED_GENERATOR, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
			$this->schedule_next_generator_start( time() );
		}

		// Set the store address as taxable location.
		add_filter( 'woocommerce_customer_taxable_address', array( $this, 'set_store_address_as_taxable_location' ) );

		// PHP shuts down execution for some reason.
		add_action( 'action_scheduler_unexpected_shutdown', array( $this, 'handle_unexpected_shutdown' ), 10, 2 );
		// Action got an exception thrown.
		add_action( 'action_scheduler_failed_execution', array( $this, 'handle_failed_execution' ), 10, 2 );
	}

	/**
	 * Returns feed generator actions group name.
	 *
	 * @since 1.3.1
	 *
	 * @return string
	 */
	public function get_group_name(): string {
		return PINTEREST_FOR_WOOCOMMERCE_PREFIX;
	}

	/**
	 * Unexpected shutdown handler.
	 *
	 * @param int        $action_id - Action Scheduler action ID.
	 * @param array|null $error - Error details.
	 *
	 * @since 1.3.1
	 *
	 * @return void
	 */
	public function handle_unexpected_shutdown( int $action_id, ?array $error ) {
		if ( ! $error || ! $this->is_timeout_error( $error ) ) {
			return;
		}
		$action = ActionScheduler::store()->fetch_action( $action_id );
		$hook   = $action->get_hook();
		$args   = $action->get_args();

		// If not Pinterest Feed Generator action - ignore.
		if ( $hook !== $this->get_action_full_name( self::CHAIN_BATCH ) ) {
			return;
		}

		// Check if the action had failed before.
		if ( $this->is_failure_rate_above_threshold( $hook, $args ) ) {
			self::log(
				sprintf(
					// Translators: 1. Action Scheduler hook name.
					__(
						'Feed Generator `%s` Action reschedule threshold has been reached. Quit.',
						'pinterest-for-woocommerce'
					),
					$hook
				)
			);
			return;
		}

		// Check if a PENDING retry already exists to prevent duplicate retries.
		// We query STATUS_PENDING only — the timing out action itself is STATUS_RUNNING
		// at the point this handler fires (AS marks it in-progress before invoking the
		// callback), so as_has_scheduled_action() would incorrectly match it and block
		// the very first reschedule.  A genuine duplicate is a *pending* retry scheduled
		// by an earlier invocation of this handler.
		$pending_retries = $this->action_scheduler->search(
			array(
				'hook'     => $hook,
				'args'     => $args,
				'per_page' => 1,
				'status'   => ActionSchedulerInterface::STATUS_PENDING,
			),
			'ids',
			PINTEREST_FOR_WOOCOMMERCE_PREFIX
		);
		if ( ! empty( $pending_retries ) ) {
			self::log(
				sprintf(
					// Translators: Action Scheduler hook name.
					__(
						'Feed Generator `%s` Action retry already scheduled. Skipping duplicate.',
						'pinterest-for-woocommerce'
					),
					$hook
				)
			);
			return;
		}

		self::log(
			sprintf(
				// Translators: Action Scheduler hook name.
				__(
					'Feed Generator `%s` Action timed out due to an unexpected shutdown. Rescheduling it.',
					'pinterest-for-woocommerce'
				),
				$hook
			)
		);

		// Decrease the number of products to retry.
		$attempt = ( Pinterest_For_Woocommerce::get_data( 'feed_product_batch_attempt' ) ?? 1 ) + 1;
		$limit   = (int) ceil( $this->get_batch_size() / $attempt );
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_size', $limit );
		Pinterest_For_Woocommerce::save_data( 'feed_product_batch_attempt', $attempt );

		self::log(
			sprintf(
				// Translators: 1: Action Scheduler hook name, 2: New products number to process next action run.
				__(
					'Feed Generator `%1$s` Action product batch size decreased to %2$d.',
					'pinterest-for-woocommerce'
				),
				$hook,
				$limit
			)
		);

		// Register retry attempt.
		$this->action_scheduler->schedule_immediate( $hook, $args, PINTEREST_FOR_WOOCOMMERCE_PREFIX );
	}

	/**
	 * Action exception handler.
	 *
	 * @param int       $action_id - Action Scheduler action id.
	 * @param Throwable $throwable - Exception object.
	 *
	 * @since 1.3.1
	 *
	 * @return void
	 */
	public function handle_failed_execution( int $action_id, Throwable $throwable ) {
		$action = ActionScheduler::store()->fetch_action( $action_id );
		$hook   = $action->get_hook();

		// If not Pinterest Feed Generator action - ignore.
		if ( $hook !== $this->get_action_full_name( self::CHAIN_BATCH ) ) {
			return;
		}

		$this->handle_error( $throwable, $hook );
	}

	/**
	 * Reschedule the next feed generator start.
	 *
	 * @since 1.0.10
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
	 * @since 1.0.10
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
	 * @since 1.0.10
	 *
	 * @throws Throwable Related to creating an empty feed temp file and populating the header possible issues.
	 */
	protected function handle_start() {
		self::log( __( 'Feed generation start. Preparing temporary files.', 'pinterest-for-woocommerce' ) );
		try {
			ProductFeedStatus::reset_feed_file_generation_time();
			ProductFeedStatus::set(
				array(
					'status'        => 'in_progress',
					'product_count' => 0,
				)
			);
			$this->feed_file_operations->prepare_temporary_files();
		} catch ( Throwable $th ) {
			$this->handle_error( $th, $this->get_action_full_name( self::CHAIN_START ) );
			throw $th;
		}
	}

	/**
	 * Handle processing a chain batch.
	 *
	 * @since 1.2.14
	 *
	 * @param int   $batch_number The batch number for the new batch.
	 * @param array $args         The args for the job.
	 *
	 * @throws Throwable Related to issue possible when creating an empty feed temp file and populating the header.
	 */
	public function handle_batch_action( int $batch_number, array $args ) {
		// Reset pending cursor to prevent stale values from previous failed batches.
		$this->pending_last_batch_id = null;

		parent::handle_batch_action( $batch_number, $args );

		// Reset number of products per batch and action retries counter on success.
		Pinterest_For_Woocommerce::remove_data( 'feed_product_batch_size' );
		Pinterest_For_Woocommerce::remove_data( 'feed_product_batch_attempt' );
	}

	/**
	 * Runs as the last step of the job.
	 * Add XML footer to the feed files and copy the move the files from tmp to the final destination.
	 *
	 * @since 1.0.10
	 *
	 * @throws Throwable Related to adding the footer or renaming the files possible issues.
	 */
	protected function handle_end() {
		self::log( __( 'Feed generation end. Moving files to the final destination.', 'pinterest-for-woocommerce' ) );
		try {
			$this->feed_file_operations->add_footer_to_temporary_feed_files();
			$this->feed_file_operations->rename_temporary_feed_files_to_final();
			ProductFeedStatus::set(
				array(
					'status' => 'generated',
					ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => ProductFeedStatus::get()['product_count'],
				)
			);
			ProductFeedStatus::set_feed_file_generation_time( time() );
		} catch ( Throwable $th ) {
			$this->handle_error( $th, $this->get_action_full_name( self::CHAIN_END ) );
			throw $th;
		}
		self::log( __( 'Feed generated successfully.', 'pinterest-for-woocommerce' ) );

		// Check if feed is dirty and reschedule in necessary.
		if ( $this->feed_is_dirty() ) {
			$this->mark_feed_clean();
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
	 * @throws FeedCircuitBreakerException When the batch limit is exceeded, stopping the job chain.
	 */
	protected function get_items_for_batch( int $batch_number, array $args ): array {
		/**
		 * Maximum number of batches allowed per generation cycle.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		$max_batches = (int) apply_filters( 'pinterest_for_woocommerce_max_feed_batches_per_cycle', self::MAX_BATCHES_PER_CYCLE );

		// Circuit breaker: abort with an error so the truncated feed is never silently published.
		if ( $batch_number > $max_batches ) {
			$message = sprintf(
				// Translators: 1: batch limit, 2: filter name.
				__(
					'Feed generation truncated: maximum batch limit of %1$d reached. Use the `%2$s` filter to increase the limit.',
					'pinterest-for-woocommerce'
				),
				$max_batches,
				'pinterest_for_woocommerce_max_feed_batches_per_cycle'
			);
			throw new FeedCircuitBreakerException( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		global $wpdb;

		$variable_type_like = $wpdb->esc_like( 'variable' ) . '%';
		$product_ids        = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT post.ID
				FROM {$wpdb->posts} as post
				LEFT JOIN {$wpdb->posts} as parent ON post.post_parent = parent.ID
				WHERE
					(
						( post.post_type = 'product_variation' AND parent.post_status = 'publish'
							AND EXISTS (
								SELECT 1
								FROM {$wpdb->term_relationships} tr
								INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
								INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
								WHERE tr.object_id = parent.ID AND tt.taxonomy = 'product_type' AND t.slug LIKE %s
							)
						)
					OR
						( post.post_type = 'product' AND post.post_status = 'publish' )
					)
				AND
					post.ID > %d
				ORDER BY post.ID ASC
				LIMIT %d",
				$variable_type_like,
				$this->get_last_batch_id( $batch_number ),
				$this->get_batch_size()
			)
		);

		$product_ids                 = array_map( 'intval', $product_ids );
		$this->pending_last_batch_id = $product_ids[ count( $product_ids ) - 1 ] ?? 0;
		return $product_ids;
	}

	/**
	 * Processes a batch of items. The middle part of the generation process.
	 * Can run multiple times depending on the catalog size.
	 *
	 * @since 1.0.10
	 *
	 * @param array $items The items of the current batch.
	 * @param array $args  The args for the job.
	 *
	 * @throws FeedFileOperationsException In case there was an exception thrown when writing to a feed file.
	 */
	protected function process_items( array $items, array $args ) {
		$products = $this->get_feed_products( $items );

		$this->prepare_feed_buffers();

		$processed_products = 0;
		foreach ( $products as $product ) {
			foreach ( $this->get_locations() as $location ) {
				$product_xml = ProductsXmlFeed::get_xml_item( $product, $location );
				if ( '' === $product_xml ) {
					continue;
				}
				$this->buffers[ $location ] .= $product_xml;
			}
			++$processed_products;
		}

		// May throw write to file exception.
		$this->feed_file_operations->write_buffers_to_temp_files( $this->buffers );

		// Commit cursor immediately after the successful write to minimise the duplicate-append
		// window: if a timeout lands after write_buffers_to_temp_files() but before the commit the
		// retry would re-fetch the same IDs and append them a second time.
		if ( null !== $this->pending_last_batch_id ) {
			$this->set_last_batch_id( $this->pending_last_batch_id );
			$this->pending_last_batch_id = null;
		}

		$count = ProductFeedStatus::get()['product_count'] ?? 0;
		ProductFeedStatus::set(
			array(
				'product_count' => $count + $processed_products,
			)
		);
		/* translators: number of products */
		self::log( sprintf( __( 'Feed batch generated. Wrote %s products to the feed file.', 'pinterest-for-woocommerce' ), $processed_products ) );
	}

	/**
	 * Returns WC products by product ids. Products returned are of either `in stock` or `on backorder` statuses.
	 *
	 * @since 1.2.19
	 *
	 * @param int[] $ids - array of product ids.
	 *
	 * @return array|\stdClass
	 */
	public function get_feed_products( array $ids ) {
		// Get included product types.
		$included_product_types = array_diff(
			self::get_included_product_types(),
			self::get_excluded_product_types(),
		);

		$products_query_args = array(
			'type'       => $included_product_types,
			'include'    => $ids,
			'visibility' => 'catalog',
			'orderby'    => 'none',
			'limit'      => $this->get_batch_size(),
		);

		// Exclude variation subscriptions.
		$products_query_args['parent_exclude'] = $this->get_excluded_products_by_parent();

		// Do not sync out of stock products which do not support backorders if woocommerce_hide_out_of_stock_items is set.
		if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
			$products_query_args['stock_status'] = array( 'instock', 'onbackorder' );
		}

		return wc_get_products( $products_query_args );
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
	 * Marks feed as dirty.
	 *
	 * @since 1.0.10
	 */
	public function mark_feed_dirty(): void {
		Pinterest_For_Woocommerce()::save_data( 'feed_dirty', true );
		self::log( 'Feed is dirty.' );

		if ( $this->is_running() ) {
			// New generation will be started at the end of current one.
			return;
		}

		// Start new feed generation cycle now.
		$this->schedule_next_generator_start( time() );
	}

	/**
	 * Marks feed as clean.
	 *
	 * @since 1.0.10
	 */
	public function mark_feed_clean(): void {
		Pinterest_For_Woocommerce()::save_data( 'feed_dirty', false );
	}

	/**
	 * Check if feed is dirty.
	 *
	 * @since 1.0.10
	 * @return bool Indicates if feed is dirty or not.
	 */
	public function feed_is_dirty(): bool {
		return (bool) Pinterest_For_Woocommerce()::get_data( 'feed_dirty' );
	}

	/**
	 * Whether the given throwable is, or wraps, a FeedCircuitBreakerException.
	 *
	 * Action Scheduler's queue runner catches the original Throwable and re-throws a
	 * generic Exception, keeping the original only as the previous exception. The
	 * exception delivered to the failed-execution handler is therefore not the
	 * FeedCircuitBreakerException itself, so we walk the previous chain to detect it.
	 *
	 * @param Throwable $th The thrown exception.
	 * @return bool
	 */
	private function is_circuit_breaker_exception( Throwable $th ): bool {
		for ( $current = $th; null !== $current; $current = $current->getPrevious() ) {
			if ( $current instanceof FeedCircuitBreakerException ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Handle feed generation error by updating status and scheduling retry.
	 *
	 * @param Throwable $th - An exception that was thrown.
	 * @param string    $hook_name - The name of the hook that was being executed when the exception was thrown.
	 *
	 * @since 1.0.10
	 *
	 * @return void
	 */
	private function handle_error( Throwable $th, string $hook_name = '' ) {
		ProductFeedStatus::set(
			array(
				'status'        => 'error',
				'error_message' => $th->getMessage(),
			)
		);
		ProductFeedStatus::mark_feed_file_generation_as_failed();

		if ( $this->is_circuit_breaker_exception( $th ) ) {
			// Use a live product count rather than the batch-run status cache — the batch
			// may have been interrupted mid-update, so the cached count could be stale.
			$total_products = $this->count_published_products();
			/**
			 * Maximum number of batches allowed per generation cycle.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			$max_batches = (int) apply_filters( 'pinterest_for_woocommerce_max_feed_batches_per_cycle', self::MAX_BATCHES_PER_CYCLE );
			$recommended = null === $total_products ? 0 : $this->calculate_recommended_batch_limit( $total_products );

			// Never advise a value at or below the limit that just tripped. That would
			// happen if the count query failed ( null ) or returned a stale/low value,
			// leaving the merchant with a recommendation that cannot resolve the problem.
			if ( $recommended <= $max_batches ) {
				$recommended = (int) ( ceil( ( $max_batches * 2 ) / 500 ) * 500 );
			}

			FeedCircuitBreakerNote::add_note( $recommended );

			// Do not reschedule a full regeneration here. An over-limit catalog would
			// re-process every cycle and trip the breaker again — the exact runaway the
			// breaker exists to prevent. The admin note prompts the merchant to raise the
			// limit; the daily generator recurrence resumes the sync once they do.
			self::log(
				sprintf(
					// Translators: 1: Action Scheduler hook name, 2: Error message about why action has failed to execute.
					__(
						'Feed Generator `%1$s` Action stopped: `%2$s`. No automatic retry scheduled; raise the batch limit filter to resume.',
						'pinterest-for-woocommerce'
					),
					$hook_name,
					$th->getMessage()
				),
				\WC_Log_Levels::ERROR
			);

			return;
		}

		self::log(
			sprintf(
				// Translators: 1: Action Scheduler hook name, 2: Error message about why action has failed to execute.
				__(
					'Feed Generator `%1$s` Action failed to execute due to an error thrown `%2$s.`. A complete feed generation retry has been scheduled.',
					'pinterest-for-woocommerce'
				),
				$hook_name,
				$th->getMessage()
			),
			\WC_Log_Levels::ERROR
		);

		$this->schedule_next_generator_start( time() + self::WAIT_ON_ERROR_BEFORE_RETRY );
	}

	/**
	 * Remove feed files and cancel pending actions.
	 * Part of the cleanup procedure.
	 *
	 * @since 1.0.10
	 */
	public static function deregister(): void {
		foreach ( LocalFeedConfigs::get_instance()->get_configurations() as $config ) {
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
	 * Create empty string buffers for
	 *
	 * @since 1.0.10
	 */
	private function prepare_feed_buffers(): void {
		foreach ( $this->get_locations() as $location ) {
			$this->buffers[ $location ] = '';
		}
	}

	/**
	 * Fetch supported locations.
	 *
	 * @since 1.0.10
	 */
	private function get_locations(): array {
		return array_keys( $this->configurations->get_configurations() );
	}

	/**
	 * Get the job's batch size.
	 *
	 * @return int - The number of products to process per batch.
	 */
	protected function get_batch_size(): int {
		/**
		 * Returns products to process per batch.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		return Pinterest_For_Woocommerce::get_data( 'feed_product_batch_size' ) ?? apply_filters(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_feed_product_batch_size',
			self::DEFAULT_PRODUCT_BATCH_SIZE
		);
	}

	/**
	 * Returns last product id from the last batch of products fetched at the previous step.
	 *
	 * @param int $batch_number - Action Scheduler chain action batch number.
	 * @return int
	 */
	protected function get_last_batch_id( int $batch_number ): int {
		if ( 1 === $batch_number ) {
			// Reset last fetched ID if batch number equals to 1.
			Pinterest_For_Woocommerce::save_data( 'feed_last_queued_item_id', 0 );
		}
		// Get last fetched ID to start from the next item after it.
		return (int) Pinterest_For_Woocommerce::get_data( 'feed_last_queued_item_id' );
	}

	/**
	 * Saves last product id.
	 *
	 * @param int $id - product id.
	 * @return void
	 */
	protected function set_last_batch_id( int $id ): void {
		Pinterest_For_Woocommerce::save_data( 'feed_last_queued_item_id', $id );
	}

	/**
	 * Count all published products and published product variations.
	 *
	 * Mirrors the same WHERE clause as get_items_for_batch() — including the EXISTS
	 * subquery that restricts variations to variable-type parents — so the result
	 * accurately reflects what the feed would include.
	 *
	 * @return int|null Published product count, or null if the count query failed.
	 */
	private function count_published_products(): ?int {
		global $wpdb;

		$variable_type_like = $wpdb->esc_like( 'variable' ) . '%';

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT COUNT( post.ID )
				FROM {$wpdb->posts} AS post
				LEFT JOIN {$wpdb->posts} AS parent ON post.post_parent = parent.ID
				WHERE
					(
						( post.post_type = 'product_variation' AND parent.post_status = 'publish'
							AND EXISTS (
								SELECT 1
								FROM {$wpdb->term_relationships} tr
								INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
								INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
								WHERE tr.object_id = parent.ID AND tt.taxonomy = 'product_type' AND t.slug LIKE %s
							)
						)
					OR
						( post.post_type = 'product' AND post.post_status = 'publish' )
					)",
				$variable_type_like
			)
		);

		// A failed query (lock timeout, killed subquery, etc.) returns null. Surface it
		// rather than letting an (int) cast coerce it to 0, which would feed a misleading
		// recommendation into the admin note.
		if ( null === $count ) {
			self::log(
				__( 'Failed to count published products for the feed circuit breaker recommendation.', 'pinterest-for-woocommerce' ),
				\WC_Log_Levels::WARNING
			);
			return null;
		}

		return (int) $count;
	}

	/**
	 * Calculate the recommended max-batches-per-cycle filter value for the given product count.
	 *
	 * Formula: ceil(total / DEFAULT_PRODUCT_BATCH_SIZE) * 1.25 headroom, rounded up
	 * to the nearest 500.
	 *
	 * Uses DEFAULT_PRODUCT_BATCH_SIZE (not the current runtime batch size) so the
	 * recommendation remains stable across retry cycles where the batch size is
	 * temporarily halved due to timeouts.
	 *
	 * @param int $total_products Total published product count.
	 * @return int
	 */
	protected function calculate_recommended_batch_limit( int $total_products ): int {
		$needed   = (int) ceil( $total_products / self::DEFAULT_PRODUCT_BATCH_SIZE );
		$buffered = (int) ceil( $needed * 1.25 );
		return max( 500, (int) ( ceil( $buffered / 500 ) * 500 ) );
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

	/**
	 * Return the list of supported product types.
	 *
	 * @since 1.2.9
	 *
	 * @return array
	 */
	private function get_included_product_types(): array {
		/**
		 * Returns array of included product types.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		return (array) apply_filters(
			'pinterest_for_woocommerce_included_product_types',
			array(
				'simple',
				'variation',
			)
		);
	}

	/**
	 * Return the list of excluded product types.
	 *
	 * @since 1.2.9
	 *
	 * @return array
	 */
	private function get_excluded_product_types(): array {
		/**
		 * Returns array of excluded product types.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		return (array) apply_filters(
			'pinterest_for_woocommerce_excluded_product_types',
			array(
				'grouped',
				'variable',
				'subscription',
				'variable-subscription',
			)
		);
	}

	/**
	 * Exclude products by parent (e.g. 'variation-subscriptions').
	 *
	 * @since 1.2.9
	 *
	 * @return array
	 */
	private function get_excluded_products_by_parent(): array {
		/**
		 * Returns array of excluded products by parent.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		return (array) apply_filters(
			'pinterest_for_woocommerce_excluded_products_by_parent',
			wc_get_products(
				array(
					'type'   => 'variable-subscription',
					'limit'  => -1,
					'return' => 'ids',
				)
			)
		);
	}

	/**
	 * Set the store address as taxable location.
	 *
	 * @since 1.2.13
	 *
	 * @param array $taxable_location The taxable location.
	 */
	public function set_store_address_as_taxable_location( array $taxable_location ) {

		if ( ! doing_action( $this->get_action_full_name( self::CHAIN_BATCH ) ) ) {
			return $taxable_location;
		}

		if ( isset( $taxable_location[0] ) ) {
			$taxable_location[0] = Pinterest_For_Woocommerce()::get_base_country();
		}

		return $taxable_location;
	}

	/**
	 * Determines whether the given error is an execution "timeout" error.
	 *
	 * @param array $error An associative array describing the error with keys "type", "message", "file" and "line".
	 *
	 * @return bool
	 *
	 * @since 1.2.14
	 */
	protected function is_timeout_error( array $error ): bool {
		$is_error              = E_ERROR === $error['type'] ?? 0;
		$is_max_execution_time = strpos( $error['message'] ?? '', 'Maximum execution time' ) !== false;
		return $is_error && $is_max_execution_time;
	}

	/**
	 * Handle error on generate feed timeout.
	 *
	 * @since 1.2.14
	 * @deprecated x.x.x
	 *
	 * @param int $action_id The ID of the action marked as failed.
	 *
	 * @throws Exception Related to max retries reached or missing arguments on the action.
	 */
	public function maybe_handle_error_on_timeout( int $action_id ) {
		wc_deprecated_function( __METHOD__, '1.3.5' );
	}

	/**
	 * Check whether the action's failure rate is above the specified threshold within the timeframe.
	 *
	 * @param string $hook The job action hook.
	 * @param ?array $args The job arguments.
	 *
	 * @return bool True if the action's error rate is above the threshold, and false otherwise.
	 *
	 * @since 1.3.1
	 */
	protected function is_failure_rate_above_threshold( string $hook, ?array $args = null ): bool {
		/**
		 * Threshold of failed actions.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		$threshold = apply_filters( 'pinterest_for_woocommerce_action_failure_threshold', 3 );
		/**
		 * Time period of failed actions.
		 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
		 */
		$time_period    = apply_filters( 'pinterest_for_woocommerce_action_failure_time_period', 30 * MINUTE_IN_SECONDS );
		$failed_actions = $this->action_scheduler->search(
			array(
				'hook'         => $hook,
				'args'         => $args,
				'status'       => ActionSchedulerInterface::STATUS_FAILED,
				'date'         => gmdate( 'U' ) - $time_period,
				'date_compare' => '>',
			),
			'ids'
		);

		return count( $failed_actions ) >= $threshold;
	}
}
