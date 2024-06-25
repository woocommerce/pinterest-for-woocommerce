<?php

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Tracking\Conversions;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Tag;
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

		$pinterest_tag_tracker = $this->createMock( Tag::class );
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

		$pinterest_tag_tracker = $this->createMock( Tag::class );
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
}
