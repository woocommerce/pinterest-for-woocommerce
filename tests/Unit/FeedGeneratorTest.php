<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use ActionScheduler_Action;
use ActionScheduler_QueueRunner;
use ActionScheduler_SimpleSchedule;
use ActionScheduler_Store;
use ActionScheduler;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\Exception\FeedFileOperationsException;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Exception;
use WC_Helper_Product;

class FeedGeneratorTest extends \WP_UnitTestCase {

	/** @var ActionSchedulerInterface */
	private $action_scheduler;

	/** @var FeedFileOperations */
	private $feed_file_operations;

	/** @var LocalFeedConfigs */
	private $local_feed_configs;

	/** @var FeedGenerator */
	private $feed_generator;

	public function setUp() {
		parent::setUp();
		$this->action_scheduler     = $this->createMock( ActionSchedulerInterface::class );
		$this->feed_file_operations = $this->createMock( FeedFileOperations::class );
		$this->local_feed_configs   = $this->createMock( LocalFeedConfigs::class );
		$this->local_feed_configs
			->method( 'get_configurations' )
			->willReturn( array() );

		$this->feed_generator = new FeedGenerator( $this->action_scheduler, $this->feed_file_operations, $this->local_feed_configs );

		ProductFeedStatus::set( ProductFeedStatus::STATE_PROPS );
	}

	/**
	 * Tests feed generator registers the action scheduler failed execution hook.
	 *
	 * @return void
	 */
	public function test_init_adds_action_scheduler_failed_execution_hook() {
		$this->feed_generator->init();

		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_failed_execution',
				array( $this->feed_generator, 'handle_failed_execution' )
			)
		);
	}

	/**
	 * Tests feed generator registers the action scheduler shutdown hook.
	 *
	 * @return void
	 */
	public function test_init_adds_action_scheduler_unexpected_shutdown_hook() {
		$this->feed_generator->init();

		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_unexpected_shutdown',
				array( $this->feed_generator, 'handle_unexpected_shutdown' )
			)
		);
	}

	/**
	 * Tests that the feed generator reschedules itself when the feed file operations fail with exception.
	 *
	 * @return void
	 * @throws Exception
	 */
	public function test_action_scheduler_failed_execution_hook_calls_handle_failed_execution() {
		$store  = ActionScheduler::store();
		$runner = new ActionScheduler_QueueRunner( $store );

		add_action( 'action_scheduler_failed_execution', array( $this->feed_generator, 'handle_failed_execution' ), 10, 3 );

		// Add a callback to throw an exception when the action is processed.
		$callback = function () {
			throw new Exception('Action `pinterest/jobs/generate_feed/chain_batch` failed to complete.' );
		};
		add_action( 'pinterest/jobs/generate_feed/chain_batch', $callback, 10, 2 );

		as_schedule_single_action( gmdate( 'U' ) - 1, 'pinterest/jobs/generate_feed/chain_batch', array( 1, array() ), 'pinterest_for_woocommerce' );

		$runner->run();

		remove_action( 'pinterest/jobs/generate_feed/chain_batch', $callback );

		// Check feed generation status.
		list(
			'status'                    => $status,
			'error_message'             => $error_message,
			'feed_generation_wall_time' => $feed_generation_wall_time,
		) = ProductFeedStatus::get();
		$this->assertEquals( 'error', $status );
		$this->assertEquals( 'Action `pinterest/jobs/generate_feed/chain_batch` failed to complete.', $error_message );
		$this->assertEquals( -1, $feed_generation_wall_time );

		// Check the next scheduled action.
		$future_actions = as_get_scheduled_actions(
			array(
				'hook'   => 'pinterest-for-woocommerce-start-feed-generation',
				'status' => 'pending',
				'group'  => 'pinterest-for-woocommerce',
			)
		);

		$this->assertCount( 1, $future_actions );
		/** @var ActionScheduler_Action $action */
		$action = current( $future_actions );
		$delay_in_hours = (int) ceil( ( $action->get_schedule()->get_date()->getTimestamp() - time() ) / 3600 );
		$this->assertEquals( 1, $delay_in_hours );
	}

	public function test_feed_generator_start_sets_product_feed_status_generation_start_time() {
		$time_test_started = time();

		$this->feed_generator->handle_start_action( array() );

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 0, $feed_generation_wall_time );
	}

	/**
	 * When new feed generation starts, make sure not to reset previous run stats like total wall time it took to generate
	 * the feed and a number of products that feed had.
	 *
	 * @return void
	 */
	public function test_feed_generator_start_does_not_reset_recent_product_count_and_wall_time() {
		$time_test_started = time();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME      => 61461453,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 123,
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 76823678,
			)
		);

		$this->feed_generator->handle_start_action( array() );

		$feed_generation_wall_start_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME ];
		$feed_generation_wall_time       = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$feed_generation_product_count   = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_start_time );
		$this->assertEquals( 123, $feed_generation_product_count );
		$this->assertEquals( 76823678, $feed_generation_wall_time );
	}

	public function test_feed_generator_start_fails_and_exception_is_thrown() {
		$this->expectException( Exception::class );
		$this->feed_file_operations
			->method( 'prepare_temporary_files' )
			->willThrowException( new Exception() );

		$this->feed_generator->handle_start_action( array() );
	}

	public function test_feed_generator_start_fails_and_sets_wall_time_to_negative() {
		$this->feed_file_operations
			->method( 'prepare_temporary_files' )
			->willThrowException( new Exception() );

		try {
			$this->feed_generator->handle_start_action( array() );
		} catch ( Exception $e ) {
			$feed_generation_status        = ProductFeedStatus::get()[ 'status' ];
			$feed_generation_wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
			$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

			$this->assertEquals( 'error', $feed_generation_status );
			$this->assertEquals( 0, $feed_generation_product_count );
			$this->assertEquals( -1, $feed_generation_wall_time );
		}
	}

	public function test_feed_generator_end_sets_time_it_took_to_generate_the_feed() {
		$time_test_started = time();
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_START_TIME => 0,
			)
		);

		$this->feed_generator->handle_end_action( array() );

		$feed_generation_wall_time = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];

		$this->assertGreaterThanOrEqual( $time_test_started, $feed_generation_wall_time );
	}

	public function test_feed_generator_end_sets_product_count_into_persistent_state_property() {
		ProductFeedStatus::set(
			array(
				'product_count'                                              => 13,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 1,
			)
		);

		$this->feed_generator->handle_end_action( array() );

		$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertEquals( 13, $feed_generation_product_count );
	}

	public function test_feed_generator_end_fails_and_exception_is_thrown() {
		$this->expectException( Exception::class );
		$this->feed_file_operations
			->method( 'add_footer_to_temporary_feed_files' )
			->willThrowException( new Exception() );

		$this->feed_generator->handle_end_action( array() );
	}

	public function test_feed_generator_end_fails_and_sets_wall_time_to_negative() {
		$this->feed_file_operations
			->method( 'add_footer_to_temporary_feed_files' )
			->willThrowException( new Exception() );

		try {
			$this->feed_generator->handle_end_action( array() );
		} catch ( Exception $e ) {
			$feed_generation_status        = ProductFeedStatus::get()[ 'status' ];
			$feed_generation_wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
			$feed_generation_product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

			$this->assertEquals( 'error', $feed_generation_status );
			$this->assertEquals( 0, $feed_generation_product_count );
			$this->assertEquals( -1, $feed_generation_wall_time );
		}
	}

	public function test_while_feed_generator_is_in_progress_previous_wall_time_and_recent_product_count_are_not_overwritten() {
		ProductFeedStatus::set(
			array(
				ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME            => 19,
				ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT => 99,
			)
		);

		$this->feed_generator->handle_start_action( array() );

		$status        = ProductFeedStatus::get()[ 'status' ];
		$wall_time     = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_WALL_TIME ];
		$product_count = ProductFeedStatus::get()[ ProductFeedStatus::PROP_FEED_GENERATION_RECENT_PRODUCT_COUNT ];

		$this->assertEquals( 'in_progress', $status );
		$this->assertEquals( 19, $wall_time );
		$this->assertEquals( 99, $product_count );
	}

	public function test_handle_batch_action_ends_queue_when_no_more_items() {
		$this->action_scheduler
			->expects( $this->once() )
			->method( 'schedule_immediate' )
			->with(
				'pinterest/jobs/generate_feed/chain_end',
				array( array() ),
				PINTEREST_FOR_WOOCOMMERCE_PREFIX
			);

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertEquals( 0, (int) \Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ));
	}

	public function test_handle_batch_action_queues_next_batch_when_there_are_items_to_process() {
		\WC_Helper_Product::create_simple_product();

		$this->action_scheduler
			->expects( $this->once() )
			->method( 'schedule_immediate' )
			->with(
				'pinterest/jobs/generate_feed/chain_batch',
				array( 2, array() ),
				PINTEREST_FOR_WOOCOMMERCE_PREFIX
			);

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertEquals( 0, (int) \Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ));
	}

	/**
	 * Tests get feed products method returns products in stock including products on backorder.
	 *
	 * @return void
	 */
	public function test_get_feed_products_return_backorder_enabled_products() {
		update_option( 'woocommerce_hide_out_of_stock_items', 'yes' );
		$product_a = \WC_Helper_Product::create_simple_product(
			true,
			[
				'name' => 'In stock product',
			]
		);
		$product_b = \WC_Helper_Product::create_simple_product(
			true,
			[
				'name'         => 'Product on backorder',
				'stock_status' => 'onbackorder',
			]
		);
		$product_c = \WC_Helper_Product::create_simple_product(
			true,
			[
				'name'         => 'Out of stock product',
				'stock_status' => 'outofstock',
			]
		);

		$ids = [ $product_a->get_id(), $product_b->get_id(), $product_c->get_id() ];

		$products = $this->feed_generator->get_feed_products( $ids );

		$this->assertCount( 2, $products );
		$this->assertEquals( $product_a->get_id(), $products[0]->get_id() );
		$this->assertEquals( 'In stock product', $products[0]->get_name() );
		$this->assertEquals( 'instock', $products[0]->get_stock_status() );
		$this->assertEquals( $product_b->get_id(), $products[1]->get_id() );
		$this->assertEquals( 'Product on backorder', $products[1]->get_name() );
		$this->assertEquals( 'onbackorder', $products[1]->get_stock_status() );
	}
}
