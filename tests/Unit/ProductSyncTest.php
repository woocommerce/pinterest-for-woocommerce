<?php
/**
 * Pinterest for WooCommerce ProductSync tests.
 *
 * @package Pinterest_For_WooCommerce/Tests
 */

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\ActionSchedulerJobFramework\Proxies\ActionSchedulerInterface;
use Automattic\WooCommerce\Pinterest\FeedFileOperations;
use Automattic\WooCommerce\Pinterest\FeedGenerator;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductSync;
use ReflectionProperty;
use WC_Helper_Product;
use WC_Product_Simple;

/**
 * Covers the hooks that flag the feed for regeneration when a product changes.
 */
class ProductSyncTest extends \WP_UnitTestCase {

	/**
	 * Feed generator wired into ProductSync for the duration of a test.
	 *
	 * @var FeedGenerator
	 */
	private $feed_generator;

	/**
	 * Sets up a real feed generator and registers the production hooks.
	 *
	 * ProductSync::maybe_init() needs a verified domain and an enabled sync, so the
	 * generator is injected and the hooks are registered by hand instead.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$action_scheduler = $this->createMock( ActionSchedulerInterface::class );
		$action_scheduler->method( 'search' )->willReturn( array() );
		$action_scheduler->method( 'next_scheduled_action' )->willReturn( false );

		$local_feed_configs = $this->createMock( LocalFeedConfigs::class );
		$local_feed_configs->method( 'get_configurations' )->willReturn( array() );

		$this->feed_generator = new FeedGenerator(
			$action_scheduler,
			$this->createMock( FeedFileOperations::class ),
			$local_feed_configs
		);

		$this->set_static_property( 'feed_generator', $this->feed_generator );
		$this->set_static_property( 'flagged_product_ids', array() );

		add_action( 'woocommerce_new_product', array( ProductSync::class, 'mark_feed_dirty_on_new_product' ), 10, 1 );
		add_action( 'woocommerce_product_object_updated_props', array( ProductSync::class, 'mark_feed_dirty_on_updated_props' ), 10, 2 );
	}

	/**
	 * Removes the hooks and the state shared between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_action( 'woocommerce_new_product', array( ProductSync::class, 'mark_feed_dirty_on_new_product' ), 10 );
		remove_action( 'woocommerce_product_object_updated_props', array( ProductSync::class, 'mark_feed_dirty_on_updated_props' ), 10 );

		as_unschedule_all_actions( 'pinterest-for-woocommerce-start-feed-generation', null, 'pinterest-for-woocommerce' );
		delete_option( FeedGenerator::OPTION_FEED_DIRTY );

		$this->set_static_property( 'feed_generator', null );
		$this->set_static_property( 'flagged_product_ids', array() );

		parent::tearDown();
	}

	/**
	 * Writes a private static property of ProductSync.
	 *
	 * @param string $name  Property name.
	 * @param mixed  $value Value to write.
	 *
	 * @return void
	 */
	private function set_static_property( string $name, $value ) {
		$property = new ReflectionProperty( ProductSync::class, $name );
		$property->setAccessible( true );
		$property->setValue( null, $value );
	}

	/**
	 * Creates a published product and clears the flag raised by its creation.
	 *
	 * @return \WC_Product The saved product.
	 */
	private function create_clean_product() {
		$product = WC_Helper_Product::create_simple_product();

		$this->feed_generator->mark_feed_clean();
		$this->set_static_property( 'flagged_product_ids', array() );

		return $product;
	}

	/**
	 * A price change is rendered by the feed, so it must schedule a regeneration.
	 *
	 * @return void
	 */
	public function test_price_change_marks_feed_dirty() {
		$product = $this->create_clean_product();

		$product->set_regular_price( 20 );
		$product->save();

		$this->assertTrue( $this->feed_generator->feed_is_dirty(), 'A price change must mark the feed dirty.' );
	}

	/**
	 * The checkout regression: reducing the stock of a product that stays in stock only
	 * writes stock_quantity, which the feed does not render, so it must not schedule a
	 * regeneration. The feed's only stock field is g:availability, taken from the status.
	 *
	 * @return void
	 */
	public function test_stock_quantity_only_change_does_not_mark_feed_dirty() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'manage_stock'   => true,
				'stock_quantity' => 50,
			)
		);

		$this->feed_generator->mark_feed_clean();
		$this->set_static_property( 'flagged_product_ids', array() );

		$updated_props = array();
		$spy           = function ( $saved_product, $props ) use ( &$updated_props ) {
			$updated_props = $props;
		};
		add_action( 'woocommerce_product_object_updated_props', $spy, 10, 2 );

		$product->set_stock_quantity( 49 );
		$product->save();

		remove_action( 'woocommerce_product_object_updated_props', $spy, 10 );

		$this->assertContains( 'stock_quantity', $updated_props, 'The save must have written the stock quantity.' );
		$this->assertNotContains( 'stock_status', $updated_props, 'The product must stay in stock for this scenario.' );
		$this->assertFalse( $this->feed_generator->feed_is_dirty(), 'A stock quantity decrement must not mark the feed dirty.' );
	}

	/**
	 * A stock status transition changes g:availability, so it must schedule a regeneration.
	 *
	 * @return void
	 */
	public function test_stock_status_change_marks_feed_dirty() {
		$product = $this->create_clean_product();

		$product->set_stock_status( 'outofstock' );
		$product->save();

		$this->assertTrue( $this->feed_generator->feed_is_dirty(), 'A stock status change must mark the feed dirty.' );
	}

	/**
	 * Several hooks fire for a single save, and bulk operations save many products in one
	 * request, so a product is only allowed to flag the feed once per request.
	 *
	 * @return void
	 */
	public function test_guard_flags_the_same_product_only_once_per_request() {
		$product = $this->create_clean_product();

		$product->set_regular_price( 20 );
		$product->save();
		$this->assertTrue( $this->feed_generator->feed_is_dirty(), 'The first save must mark the feed dirty.' );

		// Clear the flag without clearing the guard: a second save of the same product
		// within the same request must not write it again.
		$this->feed_generator->mark_feed_clean();

		$product->set_regular_price( 30 );
		$product->save();

		$this->assertFalse( $this->feed_generator->feed_is_dirty(), 'The guard must collapse repeated saves of one product.' );
	}

	/**
	 * A different product in the same request is a separate change and must flag the feed.
	 *
	 * @return void
	 */
	public function test_guard_does_not_swallow_a_second_product() {
		$first = $this->create_clean_product();

		$first->set_regular_price( 20 );
		$first->save();

		$this->feed_generator->mark_feed_clean();

		WC_Helper_Product::create_simple_product();

		$this->assertTrue( $this->feed_generator->feed_is_dirty(), 'A second product must still mark the feed dirty.' );
	}

	/**
	 * Creating a published product must schedule a regeneration: edit_post never fires for
	 * a create, so this hook is the only one covering it.
	 *
	 * @return void
	 */
	public function test_published_product_creation_marks_feed_dirty() {
		WC_Helper_Product::create_simple_product();

		$this->assertTrue( $this->feed_generator->feed_is_dirty(), 'Creating a published product must mark the feed dirty.' );
	}

	/**
	 * FeedGenerator::get_items_for_batch() reads published products only, so creating a
	 * draft cannot change the feed and must not schedule a regeneration.
	 *
	 * @return void
	 */
	public function test_draft_product_creation_does_not_mark_feed_dirty() {
		$product = new WC_Product_Simple();
		$product->set_props(
			array(
				'name'          => 'Draft product',
				'regular_price' => 10,
				'status'        => 'draft',
			)
		);
		$product->save();

		$this->assertFalse( $this->feed_generator->feed_is_dirty(), 'Creating a draft product must not mark the feed dirty.' );
	}

	/**
	 * Editing a draft product cannot change the feed either.
	 *
	 * @return void
	 */
	public function test_draft_product_price_change_does_not_mark_feed_dirty() {
		$product = new WC_Product_Simple();
		$product->set_props(
			array(
				'name'          => 'Draft product',
				'regular_price' => 10,
				'status'        => 'draft',
			)
		);
		$product->save();

		$this->feed_generator->mark_feed_clean();
		$this->set_static_property( 'flagged_product_ids', array() );

		$product->set_regular_price( 20 );
		$product->save();

		$this->assertFalse( $this->feed_generator->feed_is_dirty(), 'Editing a draft product must not mark the feed dirty.' );
	}
}
