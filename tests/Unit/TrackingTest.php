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
		$this->reset_cart_state();
	}

	/**
	 * Empties the cart and the AddToCart repeat guard so tests start clean.
	 */
	private function reset_cart_state() {
		if ( ! function_exists( 'WC' ) ) {
			return;
		}
		if ( isset( WC()->cart ) ) {
			WC()->cart->empty_cart();
		}
		if ( isset( WC()->session ) ) {
			WC()->session->__unset( 'pinterest_for_woocommerce_add_to_cart_signatures' );
		}
	}

	/**
	 * Builds a tracker that records the events handed to it.
	 *
	 * @return Tracker
	 */
	private function get_recording_tracker() {
		return new class() extends Tracker {
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
		$this->reset_cart_state();

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
	 * PageVisit CAPI is sent by the browser beacon, not during HTML rendering.
	 */
	public function test_page_visit_skips_synchronous_conversions_tracker() {
		Pinterest_For_Woocommerce::save_settings( array( 'tracking_tag' => 'WD7AFW51GS' ) );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0';

		$tracking = new Tracking();

		$pinterest_tag_tracker  = $this->createMock( Tag::class );
		$pinterest_capi_tracker = $this->createMock( Conversions::class );

		$tracking->add_tracker( $pinterest_tag_tracker );
		$tracking->add_tracker( $pinterest_capi_tracker );

		$data = new None( '' );

		$pinterest_tag_tracker->expects( $this->once() )
			->method( 'track_event' )
			->with( Tracking::EVENT_PAGE_VISIT, $data );
		$pinterest_capi_tracker->expects( $this->never() )
			->method( 'track_event' );

		$tracking->track_event( Tracking::EVENT_PAGE_VISIT, $data );
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

	/**
	 * Repeated hook fires that leave the cart in the same state describe one
	 * customer action, so only the first is reported. A fresh Tracking instance
	 * per fire stands in for the separate requests a retried add-to-cart makes.
	 */
	public function test_repeated_add_to_cart_for_unchanged_cart_is_reported_once() {
		$product = \WC_Helper_Product::create_simple_product();
		$tracker = $this->get_recording_tracker();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );

		for ( $fire = 0; $fire < 4; $fire++ ) {
			$tracking = new Tracking( array( $tracker ) );
			$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );
			$tracking->remove_tracker( get_class( $tracker ) );
		}

		$tracked_events = $tracker->get_tracked_events();
		$this->assertCount( 1, $tracked_events );
		$this->assertSame( Tracking::EVENT_ADD_TO_CART, $tracked_events[0]['event_name'] );
	}

	/**
	 * A second add that actually changes the cart is a separate customer action
	 * and is reported, with its own event id.
	 */
	public function test_add_to_cart_is_reported_again_when_cart_quantity_changes() {
		$product = \WC_Helper_Product::create_simple_product();
		$tracker = $this->get_recording_tracker();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		$tracking      = new Tracking( array( $tracker ) );
		$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );

		WC()->cart->set_quantity( $cart_item_key, 2, false );
		$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );
		$tracking->remove_tracker( get_class( $tracker ) );

		$tracked_events = $tracker->get_tracked_events();
		$this->assertCount( 2, $tracked_events );
		$this->assertNotSame(
			$tracked_events[0]['data']->get_event_id(),
			$tracked_events[1]['data']->get_event_id()
		);
		$this->assertSame( 1, $tracked_events[0]['data']->get_quantity() );
	}

	/**
	 * Two different products added in the same request are both reported.
	 */
	public function test_add_to_cart_guard_is_scoped_to_the_cart_item() {
		$first   = \WC_Helper_Product::create_simple_product();
		$second  = \WC_Helper_Product::create_simple_product();
		$tracker = $this->get_recording_tracker();

		$first_key  = WC()->cart->add_to_cart( $first->get_id(), 1 );
		$second_key = WC()->cart->add_to_cart( $second->get_id(), 1 );

		$tracking = new Tracking( array( $tracker ) );
		$tracking->handle_add_to_cart( $first_key, $first->get_id(), 1, 0 );
		$tracking->handle_add_to_cart( $second_key, $second->get_id(), 1, 0 );
		$tracking->remove_tracker( get_class( $tracker ) );

		$this->assertCount( 2, $tracker->get_tracked_events() );
	}

	/**
	 * Express checkout buttons price a product by adding it to a throwaway cart.
	 * That runs add_to_cart() for real, firing the global hook, while the
	 * customer's cart is untouched. No customer action happened, so nothing is
	 * reported. Mirrors WooCommerce PayPal Payments' IsolatedCartSimulator.
	 */
	public function test_add_to_cart_on_an_isolated_cart_is_not_reported() {
		$product = \WC_Helper_Product::create_simple_product();
		$tracker = $this->get_recording_tracker();

		$prevent_session = '__return_false';
		add_filter( 'woocommerce_cart_session_initialize', $prevent_session );
		$isolated_cart = new \WC_Cart();
		remove_filter( 'woocommerce_cart_session_initialize', $prevent_session );

		$tracking      = new Tracking( array( $tracker ) );
		$cart_item_key = $isolated_cart->add_to_cart( $product->get_id(), 1 );
		$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );
		$tracking->remove_tracker( get_class( $tracker ) );

		$this->assertNotEmpty( $cart_item_key );
		$this->assertEmpty( WC()->cart->get_cart_contents() );
		$this->assertCount( 0, $tracker->get_tracked_events() );
	}

	/**
	 * A simulation of a product the customer already holds produces the cart item
	 * key that is in their cart, so the cart lookup cannot tell it apart. The
	 * simulation flag the pricing extension raises closes that case.
	 */
	public function test_add_to_cart_during_a_declared_cart_simulation_is_not_reported() {
		$product = \WC_Helper_Product::create_simple_product();
		$tracker = $this->get_recording_tracker();

		$cart_item_key = WC()->cart->add_to_cart( $product->get_id(), 1 );
		$tracking      = new Tracking( array( $tracker ) );

		add_filter( 'woocommerce_paypal_payments_is_simulating_cart', '__return_true' );
		$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );
		remove_filter( 'woocommerce_paypal_payments_is_simulating_cart', '__return_true' );

		$this->assertCount( 0, $tracker->get_tracked_events() );

		// The customer's own add is still reported once the simulation ends.
		$tracking->handle_add_to_cart( $cart_item_key, $product->get_id(), 1, 0 );
		$tracking->remove_tracker( get_class( $tracker ) );

		$this->assertCount( 1, $tracker->get_tracked_events() );
	}
}
