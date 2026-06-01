<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Tracking\Tracker;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;

class TrackingTest extends \WP_UnitTestCase {

	/**
	 * Original $_SERVER['HTTP_USER_AGENT'] value, restored after each test.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	/**
	 * Per-test setup. Snapshots the inbound User-Agent so tests can mutate it
	 * freely and have it restored in tearDown.
	 */
	public function setUp(): void {
		parent::setUp();
		// Snapshot raw value for verbatim restoration in tearDown.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
	}

	/**
	 * Per-test cleanup. Restores the inbound User-Agent and removes any
	 * filters added by tests on the crawler-detection hook.
	 */
	public function tearDown(): void {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		remove_all_filters( 'pinterest_for_woocommerce_is_crawler_request' );

		parent::tearDown();
	}

	function test_tracking_adds_actions_monitoring() {
		$tracking = new Tracking();

		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_page_visit' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_view_category' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_add_to_cart', array( $tracking, 'handle_add_to_cart' ) ) );
		$this->assertEquals( 10, has_action( 'woocommerce_before_thankyou', array( $tracking, 'handle_checkout' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $tracking, 'handle_search' ) ) );
	}

	public function test_trackers_are_empty_on_init() {
		$tracking = new Tracking();

		$this->assertEquals( array(), $tracking->get_trackers() );
	}

	public function test_tracker_is_added() {
		$tracking = new Tracking();

		$pinterest_tag_tracker = new Tag();
		$tracking->add_tracker( $pinterest_tag_tracker );

		$this->assertEquals( array( Tag::class => $pinterest_tag_tracker ), $tracking->get_trackers() );

		$this->assertEquals( 10, has_action( 'wp_footer', array( $pinterest_tag_tracker, 'print_script' ) ) );
		$this->assertEquals( 10, has_action( 'wp_footer', array( $pinterest_tag_tracker, 'print_noscript' ) ) );
		$this->assertEquals( 10, has_action( 'shutdown', array( $pinterest_tag_tracker, 'save_deferred_events' ) ) );
	}

	public function test_tracker_is_removed() {
		$tracking = new Tracking();

		$pinterest_tag_tracker = new Tag();
		$tracking->add_tracker( $pinterest_tag_tracker );

		$this->assertEquals( array( Tag::class => $pinterest_tag_tracker ), $tracking->get_trackers() );

		$tracking->remove_tracker( Tag::class );

		$this->assertEquals( array(), $tracking->get_trackers() );
		$this->assertFalse( has_action( 'wp_footer', array( $pinterest_tag_tracker, 'print_script' ) ) );
		$this->assertFalse( has_action( 'wp_footer', array( $pinterest_tag_tracker, 'print_noscript' ) ) );
		$this->assertFalse( has_action( 'shutdown', array( $pinterest_tag_tracker, 'save_deferred_events' ) ) );
	}

	public function test_tracking_calls_trackers() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$tracking = new Tracking();

		$pinterest_tag_tracker = $this->createMock( Tag::class );
		$tracking->add_tracker( $pinterest_tag_tracker );

		$data = new None( 'event_id' );
		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );

		$tracking->track_event( 'test', $data );
	}

	public function test_tracking_calls_multiple_trackers() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );

		$data = new None( 'event_id' );
		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );
		$pinterest_capi_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );

		$tracking->track_event( 'test', $data );
	}

	/**
	 * Crawler requests must skip the Conversions (CAPI) tracker while still
	 * dispatching to the Tag tracker. Browser-side rendering needs to keep
	 * happening so cached HTML carries Tag JS for real users.
	 */
	public function test_crawler_request_skips_conversions_tracker_only() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );

		$data = new None( 'event_id' );

		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );
		$pinterest_capi_tracker->expects( $this->never() )
			->method( 'track_event' );

		$tracking->track_event( 'test', $data );
	}

	/**
	 * Non-crawler requests must dispatch to BOTH trackers (regression guard
	 * against an over-broad skip).
	 */
	public function test_non_crawler_request_fires_all_trackers() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );

		$data = new None( 'event_id' );

		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );
		$pinterest_capi_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );

		$tracking->track_event( 'test', $data );
	}

	/**
	 * The `pinterest_for_woocommerce_is_crawler_request` filter must be able
	 * to flag an otherwise-human UA as a crawler and skip CAPI.
	 */
	public function test_filter_can_force_crawler_classification() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0';

		add_filter( 'pinterest_for_woocommerce_is_crawler_request', '__return_true' );

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );

		$data = new None( 'event_id' );

		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );
		$pinterest_capi_tracker->expects( $this->never() )
			->method( 'track_event' );

		$tracking->track_event( 'test', $data );
	}

	public function test_tracking_calls_no_detached_trackers() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );
		$tracking->remove_tracker( get_class( $pinterest_capi_tracker ) );

		$data = new None( 'event_id' );
		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( 'test', $data );
		$pinterest_capi_tracker->expects( $this->never() )
			->method( 'track_event' )
			->with( 'test', $data );

		$tracking->track_event( 'test', $data );
	}

	/**
	 * Tests that checkout tracking uses the paid order line unit price when a
	 * 100% discount drops the line total to zero.
	 */
	public function test_checkout_uses_paid_order_item_unit_price() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 20,
				'price'         => 20,
			)
		);

		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 2 );
		$item->set_subtotal( 40 );
		$item->set_total( 0 );
		$order->add_item( $item );
		$order->set_total( 0 );
		$order->save();

		$captured = null;
		$tracker  = $this->createMock( Tracker::class );
		$tracker->expects( $this->once() )
			->method( 'track_event' )
			->with(
				Tracking::EVENT_CHECKOUT,
				$this->callback(
					function ( Checkout $checkout ) use ( &$captured ) {
						$captured = $checkout;
						return true;
					}
				)
			);

		$tracking = new Tracking( array( $tracker ) );
		$tracking->handle_checkout( $order->get_id() );

		$items = $captured->get_items();
		$this->assertCount( 1, $items );
		$this->assertEqualsWithDelta( 0.0, (float) $captured->get_price(), 0.0001 );
		$this->assertSame( 2, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( 0.0, (float) $items[0]->get_price(), 0.0001 );
	}

	/**
	 * Tests that checkout tracking divides the paid line total by quantity
	 * for partial discounts, rather than re-reading the catalog price.
	 */
	public function test_checkout_uses_partially_discounted_per_unit_price() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 20,
				'price'         => 20,
			)
		);

		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 2 );
		$item->set_subtotal( 40 );
		$item->set_total( 20 );
		$order->add_item( $item );
		$order->set_total( 20 );
		$order->save();

		$captured = null;
		$tracker  = $this->createMock( Tracker::class );
		$tracker->expects( $this->once() )
			->method( 'track_event' )
			->with(
				Tracking::EVENT_CHECKOUT,
				$this->callback(
					function ( Checkout $checkout ) use ( &$captured ) {
						$captured = $checkout;
						return true;
					}
				)
			);

		$tracking = new Tracking( array( $tracker ) );
		$tracking->handle_checkout( $order->get_id() );

		$items = $captured->get_items();
		$this->assertCount( 1, $items );
		$this->assertEqualsWithDelta( 20.0, (float) $captured->get_price(), 0.0001 );
		$this->assertSame( 2, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( 10.0, (float) $items[0]->get_price(), 0.0001 );
	}

	/**
	 * Tests that checkout tracking excludes tax from the paid line unit price.
	 */
	public function test_checkout_uses_paid_order_item_unit_price_excluding_tax() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 20,
				'price'         => 20,
			)
		);

		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 2 );
		$item->set_subtotal( 40 );
		$item->set_total( 20 );
		$item->set_total_tax( 4 );
		$order->add_item( $item );
		$order->set_total( 24 );
		$order->save();

		$captured = null;
		$tracker  = $this->createMock( Tracker::class );
		$tracker->expects( $this->once() )
			->method( 'track_event' )
			->with(
				Tracking::EVENT_CHECKOUT,
				$this->callback(
					function ( Checkout $checkout ) use ( &$captured ) {
						$captured = $checkout;
						return true;
					}
				)
			);

		$tracking = new Tracking( array( $tracker ) );
		$tracking->handle_checkout( $order->get_id() );

		$items = $captured->get_items();
		$this->assertCount( 1, $items );
		$this->assertEqualsWithDelta( 20.0, (float) $captured->get_price(), 0.0001 );
		$this->assertSame( 2, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( 10.0, (float) $items[0]->get_price(), 0.0001 );
	}

	/**
	 * Tests that checkout tracking excludes shipping from the order value.
	 */
	public function test_checkout_value_excludes_shipping() {
		$product = WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 20,
				'price'         => 20,
			)
		);

		$order = wc_create_order();
		$item  = new \WC_Order_Item_Product();
		$item->set_product( $product );
		$item->set_quantity( 2 );
		$item->set_subtotal( 40 );
		$item->set_total( 20 );
		$order->add_item( $item );

		$shipping = new \WC_Order_Item_Shipping();
		$shipping->set_method_title( 'Flat rate' );
		$shipping->set_method_id( 'flat_rate' );
		$shipping->set_total( 5 );
		$order->add_item( $shipping );

		$order->set_shipping_total( 5 );
		$order->set_total( 25 );
		$order->save();

		$captured = null;
		$tracker  = $this->createMock( Tracker::class );
		$tracker->expects( $this->once() )
			->method( 'track_event' )
			->with(
				Tracking::EVENT_CHECKOUT,
				$this->callback(
					function ( Checkout $checkout ) use ( &$captured ) {
						$captured = $checkout;
						return true;
					}
				)
			);

		$tracking = new Tracking( array( $tracker ) );
		$tracking->handle_checkout( $order->get_id() );

		$items = $captured->get_items();
		$this->assertCount( 1, $items );
		$this->assertEqualsWithDelta( 20.0, (float) $captured->get_price(), 0.0001 );
		$this->assertSame( 2, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( 10.0, (float) $items[0]->get_price(), 0.0001 );
	}

	/**
	 * Test that checkout event ids remain stable for a given order.
	 */
	public function test_checkout_event_id_is_deterministic_for_order() {
		$product = \WC_Helper_Product::create_simple_product(
			true,
			array(
				'regular_price' => 15,
			)
		);
		$order   = wc_create_order();
		$order->add_product( $product, 2 );
		$order->set_currency( 'USD' );
		$order->calculate_totals();
		$order->save();

		$tracker  = new class() extends Tracker {
			/**
			 * @var array Tracked event calls.
			 */
			private $tracked_events = array();

			/**
			 * Records a tracked event call.
			 *
			 * @param string $event_name Event name.
			 * @param Data   $data       Event data.
			 * @return true
			 */
			public function track_event( string $event_name, Data $data ) {
				$this->tracked_events[] = array(
					'event_name' => $event_name,
					'data'       => $data,
				);
				return true;
			}

			/**
			 * Gets recorded tracked event calls.
			 *
			 * @return array
			 */
			public function get_tracked_events() {
				return $this->tracked_events;
			}
		};
		$tracking = new Tracking( array( $tracker ) );

		$tracking->handle_checkout( $order->get_id() );
		$tracking->handle_checkout( $order->get_id() );

		$expected_event_id = 'checkout_' . $order->get_id();
		$tracked_events    = $tracker->get_tracked_events();

		$this->assertCount( 2, $tracked_events );
		$this->assertSame( Tracking::EVENT_CHECKOUT, $tracked_events[0]['event_name'] );
		$this->assertSame( Tracking::EVENT_CHECKOUT, $tracked_events[1]['event_name'] );
		$this->assertInstanceOf( Checkout::class, $tracked_events[0]['data'] );
		$this->assertSame( $expected_event_id, $tracked_events[0]['data']->get_event_id() );
		$this->assertSame( $expected_event_id, $tracked_events[1]['data']->get_event_id() );

		// Per-product line item event ids stay non-deterministic (uniqid) by design.
		$first_call_items  = $tracked_events[0]['data']->get_items();
		$second_call_items = $tracked_events[1]['data']->get_items();
		$this->assertCount( 1, $first_call_items );
		$this->assertCount( 1, $second_call_items );
		$this->assertNotSame(
			$first_call_items[0]->get_event_id(),
			$second_call_items[0]->get_event_id()
		);

		$tag_data         = ( new Tag() )->prepare_request_data(
			Tracking::EVENT_CHECKOUT,
			$tracked_events[0]['data']
		);
		$conversions_data = ( new Conversions( new User( '127.0.0.1', 'test-agent' ) ) )->prepare_request_data(
			Tracking::EVENT_CHECKOUT,
			$tracked_events[0]['data']
		);

		$this->assertSame( $expected_event_id, $tag_data['event_id'] );
		$this->assertSame( $expected_event_id, $conversions_data['event_id'] );
	}
}
