<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Tracking;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;
use WP_UnitTestCase;

/**
 * Tests for cache-safe PageVisit tracking.
 */
class PageVisitTest extends WP_UnitTestCase {

	/**
	 * Original User-Agent value.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	/**
	 * Original remote address value.
	 *
	 * @var string|null
	 */
	private $original_remote_address;

	/**
	 * Set up tracking settings and a human browser request.
	 */
	public function setUp(): void {
		parent::setUp();

		// Snapshot raw request values for verbatim restoration in tearDown.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput
		$this->original_user_agent     = $_SERVER['HTTP_USER_AGENT'] ?? null;
		$this->original_remote_address = $_SERVER['REMOTE_ADDR'] ?? null;
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/140.0.0.0';
		$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
		$_POST                      = array();

		Pinterest_For_Woocommerce::save_settings(
			array(
				'track_conversions'      => true,
				'track_conversions_capi' => true,
				'tracking_advertiser'    => 'PFW-123456789',
			)
		);
	}

	/**
	 * Restore request globals and filters.
	 */
	public function tearDown(): void {
		$_POST = array();
		remove_all_filters( 'pre_http_request' );

		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		if ( null === $this->original_remote_address ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->original_remote_address;
		}

		parent::tearDown();
	}

	/**
	 * Public beacon hooks are registered for logged-in and logged-out visitors.
	 */
	public function test_registers_public_beacon_hooks() {
		$this->assertNotFalse( has_action( 'wp_ajax_' . PageVisit::AJAX_ACTION, array( PageVisit::class, 'handle_request' ) ) );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_' . PageVisit::AJAX_ACTION, array( PageVisit::class, 'handle_request' ) ) );
	}

	/**
	 * The rendered event contains runtime ID generation, not a cached PHP ID.
	 */
	public function test_tag_event_code_generates_event_id_in_browser() {
		$code = PageVisit::get_tag_event_code(
			array(
				'event_id'   => 'page_frozen_in_cache',
				'product_id' => 123,
			)
		);

		$this->assertStringNotContainsString( 'page_frozen_in_cache', $code );
		$this->assertStringContainsString( 'window.crypto.randomUUID', $code );
		$this->assertStringContainsString( 'eventData.event_id=eventId', $code );
		$this->assertStringContainsString( 'pintrk("track","PageVisit",eventData)', $code );
		$this->assertStringContainsString( PageVisit::AJAX_ACTION, $code );
		$this->assertStringNotContainsString( 'requestData.append("product_id"', $code );
		$this->assertStringNotContainsString( '_wpnonce', $code );
	}

	/**
	 * Tag-only tracking does not add an unnecessary server beacon.
	 */
	public function test_tag_event_code_omits_beacon_when_capi_is_disabled() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		$code = PageVisit::get_tag_event_code( array( 'event_id' => '' ) );

		$this->assertStringContainsString( 'var eventData={};', $code );
		$this->assertStringContainsString( 'pintrk("track","PageVisit",eventData)', $code );
		$this->assertStringNotContainsString( PageVisit::AJAX_ACTION, $code );
		$this->assertStringNotContainsString( 'sendBeacon', $code );
	}

	/**
	 * A nonce-free beacon sends one matching PageVisit event to CAPI.
	 */
	public function test_beacon_dispatches_page_visit_without_nonce() {
		$product         = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$spoofed_product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 50 ) );
		$source_url      = add_query_arg( 'campaign', 'pinterest', $product->get_permalink() );
		$requests        = 0;

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( $product, $source_url, &$requests ) {
				++$requests;
				$body  = json_decode( $parsed_args['body'], true );
				$event = $body['data'][0];

				$this->assertSame( 'page_1234567890abcdef', $event['event_id'] );
				$this->assertSame( 'page_visit', $event['event_name'] );
				$this->assertSame( $source_url, $event['event_source_url'] );
				$this->assertSame( array( (string) $product->get_id() ), $event['custom_data']['content_ids'] );

				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'events' => array( array( 'status' => 'processed' ) ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			2
		);

		$_POST = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => $source_url,
			'product_id'       => (string) $spoofed_product->get_id(),
		);

		PageVisit::handle_request();

		$this->assertSame( 1, $requests );
	}

	/**
	 * Malformed browser event IDs are rejected before CAPI dispatch.
	 */
	public function test_beacon_rejects_invalid_event_id() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);

		$_POST = array(
			'event_id'         => 'cached-id',
			'event_source_url' => home_url( '/shop/' ),
		);

		PageVisit::handle_request();

		$this->assertSame( 0, $requests );
	}

	/**
	 * Non-scalar required beacon fields are rejected before sanitization or dispatch.
	 */
	public function test_beacon_rejects_non_scalar_fields() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$requests ) {
				++$requests;
				return false;
			}
		);

		$valid_request = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => home_url( '/shop/' ),
		);

		foreach ( array_keys( $valid_request ) as $field ) {
			$_POST           = $valid_request;
			$_POST[ $field ] = array( 'invalid' );
			PageVisit::handle_request();
		}

		$this->assertSame( 0, $requests );
	}

	/**
	 * A non-product URL produces a generic PageVisit event.
	 */
	public function test_beacon_ignores_product_id_for_non_product_url() {
		$product    = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$page_id    = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$source_url = get_permalink( $page_id );
		$requests   = 0;

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( $source_url, &$requests ) {
				++$requests;
				$body  = json_decode( $parsed_args['body'], true );
				$event = $body['data'][0];

				$this->assertSame( $source_url, $event['event_source_url'] );
				$this->assertArrayNotHasKey( 'custom_data', $event );

				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode(
						array(
							'events' => array( array( 'status' => 'processed' ) ),
						)
					),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			2
		);

		$_POST = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => $source_url,
			'product_id'       => (string) $product->get_id(),
		);

		PageVisit::handle_request();

		$this->assertSame( 1, $requests );
	}
}
