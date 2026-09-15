<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
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
	 * An uncached render reuses the PHP event ID while fresh and otherwise
	 * generates one in the browser; the beacon only runs for stale HTML.
	 */
	public function test_print_script_reuses_server_event_id_only_while_fresh() {
		$product = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$code    = $this->get_script( $this->get_product_data( $product ), true, true );

		$this->assertStringContainsString( 'var serverId="page_server12345678",renderedAt=', $code );
		$this->assertStringContainsString( 'serverSent=1;', $code );
		$this->assertStringContainsString( 'Math.abs(Date.now()/1000-renderedAt)<300', $code );
		$this->assertStringContainsString( 'window.crypto.randomUUID', $code );
		$this->assertStringContainsString( 'if(window.pintrk){var eventData={"product_id":' . $product->get_id(), $code );
		$this->assertStringContainsString( 'eventData.event_id=eventId;pintrk("track","PageVisit",eventData);}', $code );
		$this->assertStringContainsString( 'if(!fresh){', $code );
		$this->assertStringContainsString( PageVisit::AJAX_ACTION, $code );
		$this->assertStringContainsString( 'requestData.append("product_id","' . $product->get_id() . '")', $code );
		$this->assertMatchesRegularExpression( '/requestData\.append\("product_token","[a-f0-9]{64}"\)/', $code );
		$this->assertStringNotContainsString( '_wpnonce', $code );
	}

	/**
	 * HTML rendered without a server-side send tells the browser to beacon.
	 */
	public function test_print_script_marks_unsent_render() {
		$code = $this->get_script( new None( 'page_server12345678' ), false, true );

		$this->assertStringContainsString( 'serverSent=0;', $code );
		$this->assertStringContainsString( 'var eventData={};', $code );
		$this->assertStringNotContainsString( 'product_token', $code );
	}

	/**
	 * Merchants without an active Tag still get the CAPI beacon and no pintrk call.
	 */
	public function test_print_script_prints_beacon_without_tag() {
		$code = $this->get_script( new None( 'page_server12345678' ), true, false );

		$this->assertStringNotContainsString( 'pintrk', $code );
		$this->assertStringContainsString( PageVisit::AJAX_ACTION, $code );
	}

	/**
	 * Tag-only tracking does not add an unnecessary server beacon.
	 */
	public function test_print_script_omits_beacon_when_capi_is_disabled() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		$code = $this->get_script( new None( 'page_server12345678' ), false, true );

		$this->assertStringContainsString( 'pintrk("track","PageVisit",eventData)', $code );
		$this->assertStringNotContainsString( PageVisit::AJAX_ACTION, $code );
		$this->assertStringNotContainsString( 'sendBeacon', $code );
	}

	/**
	 * Nothing is printed when neither the Tag nor CAPI can use the script.
	 */
	public function test_print_script_prints_nothing_without_tag_or_capi() {
		Pinterest_For_Woocommerce::save_setting( 'track_conversions_capi', false );

		$this->assertSame( '', $this->get_script( new None( 'page_server12345678' ), false, false ) );
	}

	/**
	 * A server-issued product token lets the beacon report the product even when
	 * the source URL cannot be resolved to a post.
	 */
	public function test_beacon_accepts_product_id_with_valid_token() {
		$product    = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$source_url = home_url( '/unresolvable-product-path/' );
		$this->assertSame( 0, url_to_postid( $source_url ) );

		$event = $this->send_product_beacon( $product, $source_url, $this->get_product_token( $product ) );

		$this->assertSame( array( (string) $product->get_id() ), $event['custom_data']['content_ids'] );
	}

	/**
	 * A tampered product token is ignored and the event falls back to the URL.
	 */
	public function test_beacon_rejects_product_id_with_tampered_token() {
		$product    = WC_Helper_Product::create_simple_product( true, array( 'regular_price' => 25 ) );
		$source_url = home_url( '/unresolvable-product-path/' );

		$event = $this->send_product_beacon( $product, $source_url, str_repeat( '0', 64 ) );

		$this->assertArrayNotHasKey( 'custom_data', $event );
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

	/**
	 * Captures the printed PageVisit script.
	 *
	 * @param Data $data        PageVisit event data.
	 * @param bool $server_sent Whether the server already sent the CAPI event.
	 * @param bool $tag_active  Whether the Tag is active.
	 *
	 * @return string
	 */
	private function get_script( Data $data, bool $server_sent, bool $tag_active ) {
		ob_start();
		PageVisit::print_script( $data, $server_sent, $tag_active );
		return ob_get_clean();
	}

	/**
	 * Builds PageVisit product data with a fixed server event ID.
	 *
	 * @param \WC_Product $product Product.
	 *
	 * @return Product
	 */
	private function get_product_data( \WC_Product $product ) {
		return new Product( 'page_server12345678', $product->get_id(), $product->get_name(), '', 'brand', 25, 'USD', 1 );
	}

	/**
	 * Reads the product token from the rendered script.
	 *
	 * @param \WC_Product $product Product.
	 *
	 * @return string
	 */
	private function get_product_token( \WC_Product $product ) {
		preg_match( '/product_token","([a-f0-9]{64})"/', $this->get_script( $this->get_product_data( $product ), true, false ), $matches );
		return $matches[1];
	}

	/**
	 * Sends a product beacon and returns the event received by the Conversions API.
	 *
	 * @param \WC_Product $product    Product.
	 * @param string      $source_url Event source URL.
	 * @param string      $token      Product token to post.
	 *
	 * @return array
	 */
	private function send_product_beacon( \WC_Product $product, string $source_url, string $token ) {
		$event = null;
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args ) use ( &$event ) {
				$event = json_decode( $parsed_args['body'], true )['data'][0];
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
			},
			10,
			2
		);

		$_POST = array(
			'event_id'         => 'page_1234567890abcdef',
			'event_source_url' => $source_url,
			'product_id'       => (string) $product->get_id(),
			'product_token'    => $token,
		);

		PageVisit::handle_request();

		$this->assertNotNull( $event );
		return $event;
	}
}
