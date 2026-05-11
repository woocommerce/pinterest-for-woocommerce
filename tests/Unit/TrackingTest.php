<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Checkout;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
use Automattic\WooCommerce\Pinterest\Tracking\Tracker;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;

class TrackingTest extends \WP_UnitTestCase {

	function setUp(): void {
		parent::setUp();
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
		$this->assertSame( 2, $items[0]->get_quantity() );
		$this->assertEqualsWithDelta( 10.0, (float) $items[0]->get_price(), 0.0001 );
	}
}
