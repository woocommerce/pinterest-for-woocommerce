<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Logger;
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
		$_POST          = array();
		Logger::$logger = null;
		remove_all_filters( 'pre_http_request' );

		if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
			WC()->session->__unset( 'pinterest_for_woocommerce_click_id' );
		}

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
	 * The standalone beacon generates the event ID in the browser and never calls pintrk.
	 */
	public function test_print_beacon_script_omits_pintrk() {
		ob_start();
		PageVisit::print_beacon_script();
		$code = ob_get_clean();

		$this->assertStringStartsWith( '<script>(function(){var eventId="page_"+', $code );
		$this->assertStringEndsWith( '}());</script>', $code );
		$this->assertStringContainsString( 'requestData.append("action","' . PageVisit::AJAX_ACTION . '")', $code );
		$this->assertStringContainsString( 'sendBeacon', $code );
		$this->assertStringNotContainsString( 'pintrk', $code );
	}

	/**
	 * The standalone beacon prints nothing when the Conversions API is disabled.
	 */
	public function test_print_beacon_script_prints_nothing_when_capi_is_disabled() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		ob_start();
		PageVisit::print_beacon_script();

		$this->assertSame( '', ob_get_clean() );
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
	 * A first landing on `?epik=...` reports the click ID even though the beacon
	 * request itself carries no query parameter, cookie, or session value yet.
	 */
	public function test_beacon_reads_click_id_from_source_url() {
		$product    = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$source_url = add_query_arg( 'epik', 'landing-click-id', $product->get_permalink() );
		$requests   = 0;

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( &$requests ) {
				++$requests;
				$body = json_decode( $parsed_args['body'], true );

				$this->assertSame( 'landing-click-id', $body['data'][0]['user_data']['click_id'] );

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
		);

		PageVisit::handle_request();

		$this->assertSame( 1, $requests );
		$this->assertSame( 'landing-click-id', WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
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
				return new \WP_Error( 'pfw_test_blocked', 'Unexpected HTTP request in test.' );
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
	 * Dropped beacons leave a debug log entry in the conversions log.
	 */
	public function test_rejected_beacon_is_logged() {
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );

		$logger = $this->createMock( \WC_Logger_Interface::class );
		$logger->expects( $this->once() )
			->method( 'log' )
			->with(
				'debug',
				'PageVisit beacon rejected: event_id is malformed.',
				array( 'source' => 'pinterest-for-woocommerce-conversions' )
			);
		Logger::$logger = $logger;

		$_POST = array(
			'event_id'         => 'cached-id',
			'event_source_url' => home_url( '/shop/' ),
		);

		PageVisit::handle_request();
	}

	/**
	 * Protocol-relative source URLs are rejected even when the host matches.
	 */
	public function test_beacon_rejects_protocol_relative_source_url() {
		$this->assert_source_url_rejected( '//' . wp_parse_url( home_url(), PHP_URL_HOST ) . '/shop/' );
	}

	/**
	 * Source URLs carrying user info are rejected.
	 */
	public function test_beacon_rejects_source_url_with_user_info() {
		$this->assert_source_url_rejected( str_replace( '://', '://visitor@', home_url( '/shop/' ) ) );
	}

	/**
	 * Uppercase schemes are valid URLs and must not be rejected.
	 */
	public function test_beacon_accepts_uppercase_scheme_in_source_url() {
		$requests = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$requests ) {
				++$requests;
				return array(
					'headers'  => array( 'content-type' => 'application/json' ),
					'body'     => wp_json_encode( array( 'events' => array( array( 'status' => 'processed' ) ) ) ),
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
					'filename' => '',
				);
			}
		);

		$_POST = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => str_replace( array( 'http://', 'https://' ), array( 'HTTP://', 'HTTPS://' ), home_url( '/shop/' ) ),
		);

		PageVisit::handle_request();

		$this->assertSame( 1, $requests );
	}

	/**
	 * Over-long source URLs are rejected.
	 */
	public function test_beacon_rejects_over_long_source_url() {
		$this->assert_source_url_rejected( home_url( '/' . str_repeat( 'a', 2048 ) . '/' ) );
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
				return new \WP_Error( 'pfw_test_blocked', 'Unexpected HTTP request in test.' );
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

	/**
	 * Sends an otherwise valid beacon with the given source URL and asserts no CAPI request is made.
	 *
	 * @param string $source_url Event source URL to post.
	 */
	private function assert_source_url_rejected( string $source_url ) {
		$requests = 0;
		add_filter(
			'pre_http_request',
			function () use ( &$requests ) {
				++$requests;
				return new \WP_Error( 'pfw_test_blocked', 'Unexpected HTTP request in test.' );
			}
		);

		$_POST = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => $source_url,
		);

		PageVisit::handle_request();

		$this->assertSame( 0, $requests );
	}
}
