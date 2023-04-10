<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler;
use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use \Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionScheduler as ActionSchedulerProxy;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Exception;
use Pinterest_For_Woocommerce;
use Throwable;

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

	public function test_init_adds_action_scheduler_failed_action_hook() {
		$this->feed_generator->init();

		$this->assertEquals(
			10,
			has_action(
				'action_scheduler_failed_action',
				array( $this->feed_generator, 'maybe_handle_error_on_timeout' )
			)
		);
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
				''
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
				''
			);

		$this->feed_generator->handle_batch_action( 1, array() );

		$this->assertEquals( 0, (int) \Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ));
	}

	public function test_handle_batch_action_retries_up_to_two_times_on_exception() {
		try {
			\WC_Helper_Product::create_simple_product();
			add_filter(
				'pinterest_for_woocommerce_included_product_types',
				function () {
					throw new \Exception('Dummy exception to emulate processing items failure somewhere.');
				}
			);
			$this->action_scheduler
				->expects( $this->exactly( FeedGenerator::MAX_RETRIES_PER_BATCH ) )
				->method( 'schedule_immediate' )
				->with(
					'pinterest/jobs/generate_feed/chain_batch',
					array( 1, array() ),
					''
				);

			$retries = 0;
			while( $retries < FeedGenerator::MAX_RETRIES_PER_BATCH ) {
				$this->feed_generator->handle_batch_action( 1, array() );
				$this->assertEquals( ++$retries, (int) Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ) );
			}
			$this->feed_generator->handle_batch_action(1, array());
		} catch ( Throwable $e ) {
			$this->assertEquals(
				'Dummy exception to emulate processing items failure somewhere.',
				$e->getMessage()
			);
			$this->assertEquals( 0, (int) Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ) );
		}
	}

	public function test_handle_batch_action_retries_up_to_two_times_on_timeout() {

		\WC_Helper_Product::create_simple_product();

		$this->action_scheduler
			->expects( $this->exactly( FeedGenerator::MAX_RETRIES_PER_BATCH ) )
			->method( 'schedule_immediate' )
			->with(
				'pinterest/jobs/generate_feed/chain_batch',
				array( 1, array() ),
				''
			);

		$action_scheduler = new ActionSchedulerProxy();
		$action_id        = $action_scheduler->schedule_immediate( 'pinterest/jobs/generate_feed/chain_batch', array( 1, array() ) );

		$retries = 0;

		while( $retries < FeedGenerator::MAX_RETRIES_PER_BATCH ) {
			$this->feed_generator->maybe_handle_error_on_timeout( $action_id );
			$this->assertEquals( ++$retries, (int) Pinterest_For_Woocommerce::get_data( 'feed_generation_retries' ) );
		}
		$this->feed_generator->maybe_handle_error_on_timeout( $action_id );

		$this->assertEquals( 'error', ProductFeedStatus::get()['status'] );
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
