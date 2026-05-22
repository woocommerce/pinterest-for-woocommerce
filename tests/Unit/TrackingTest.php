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
