<?php

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class ConversionsTest extends WP_UnitTestCase {

	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		Logger::$logger = null;
		wp_set_current_user( 0 );
		unset( $_GET['epik'], $_COOKIE['_epik'] );

		if ( function_exists( 'WC' ) && isset( WC()->session ) ) {
			WC()->session->__unset( 'pinterest_for_woocommerce_click_id' );
		}

		parent::tearDown();
	}

	public function test_conversions_track_page_visit_event() {
		Pinterest_For_Woocommerce::save_settings(
			array(
				'tracking_advertiser' => 'PFW-123456789',
			)
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$this->assertEquals(
					"https://api.pinterest.com/v5/ad_accounts/PFW-123456789/events",
					$url
				);
				$this->assertEquals( 'POST', $parsed_args['method'] );

				$body = json_decode( $parsed_args['body'], true );
				// Time is dynamic, so we unset it.
				unset( $body['data'][0]['event_time'] );

				$this->assertEquals(
					array(
						'data' => array(
							array(
								'event_id'         => 'event-id-123',
								'event_name'       => 'page_visit',
								'action_source'    => 'web',
								'event_source_url' => $this->get_event_source_url(),
								'partner_name'     => 'ss-woocommerce',
								'user_data'        => array(
									'client_ip_address' => 'Some IP address.',
									'client_user_agent' => 'Some user agent string.',
								),
								'language'         => 'en',
							),
						),
					),
					$body
				);

				// Returning dummy but success response.
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'num_events_received'  => 1,
							'num_events_processed' => 1,
							'events'               => array(
								array(
									'status' => 'processed',
								)
							),
						),
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
			3
		);

		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );
		$conversions->track_event( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );
	}

	/**
	 * Tests that hashed customer identifiers are merged into default user data.
	 */
	public function test_default_data_keeps_ip_user_agent_with_logged_in_customer_identifiers() {
		$user_id = self::factory()->user->create(
			array(
				'user_email' => 'customer@example.com',
			)
		);
		wp_set_current_user( $user_id );

		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );

		$this->assertEquals(
			array(
				'client_ip_address' => 'Some IP address.',
				'client_user_agent' => 'Some user agent string.',
				'em'                => array( hash( 'sha256', 'customer@example.com' ) ),
				'external_id'       => array( hash( 'sha256', (string) $user_id ) ),
			),
			$data['user_data']
		);
	}

	/**
	 * Tests that the click ID is captured from the landing URL and persisted.
	 *
	 * @return void
	 */
	public function test_default_data_uses_and_persists_epik_query_parameter() {
		$_GET['epik'] = 'pinterest-click-id';

		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );
		$data        = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );

		$this->assertSame( 'pinterest-click-id', $data['user_data']['click_id'] );
		$this->assertSame( 'pinterest-click-id', WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );

		unset( $_GET['epik'] );
		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-456' ) );

		$this->assertSame( 'pinterest-click-id', $data['user_data']['click_id'] );
	}

	/**
	 * Tests that the Pinterest tag cookie supplies the click ID.
	 *
	 * @return void
	 */
	public function test_default_data_uses_epik_cookie() {
		$_COOKIE['_epik'] = 'pinterest-cookie-click-id';

		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );
		$data        = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );

		$this->assertSame( 'pinterest-cookie-click-id', $data['user_data']['click_id'] );
	}

	/**
	 * Tests that an over-length click ID in the query string is discarded.
	 *
	 * @return void
	 */
	public function test_default_data_discards_over_length_epik_query_parameter() {
		$_GET['epik'] = str_repeat( 'a', 513 );

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that discarding an over-length click ID writes the debug diagnostic.
	 *
	 * @return void
	 */
	public function test_discarding_over_length_click_id_is_logged() {
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );

		$logger = $this->createMock( \WC_Logger_Interface::class );
		$logger->expects( $this->once() )
			->method( 'log' )
			->with(
				'debug',
				'Discarding Pinterest click ID longer than 512 bytes.',
				array( 'source' => 'pinterest-for-woocommerce-conversions' )
			);
		Logger::$logger = $logger;

		$_GET['epik'] = str_repeat( 'a', 513 );

		$this->prepare_page_visit_data();
	}

	/**
	 * An over-length value is reported even when sanitizing would also have altered it.
	 *
	 * @return void
	 */
	public function test_discarding_over_length_click_id_is_logged_for_mutated_values() {
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );

		$logger = $this->createMock( \WC_Logger_Interface::class );
		$logger->expects( $this->once() )
			->method( 'log' )
			->with(
				'debug',
				'Discarding Pinterest click ID longer than 512 bytes.',
				array( 'source' => 'pinterest-for-woocommerce-conversions' )
			);
		Logger::$logger = $logger;

		// Over the cap and carrying a percent sequence sanitize_text_field() would strip.
		$_GET['epik'] = str_repeat( 'a', 512 ) . '%41';

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
	}

	/**
	 * Tests that a click ID at the maximum length is kept and persisted.
	 *
	 * @return void
	 */
	public function test_default_data_keeps_epik_query_parameter_at_maximum_length() {
		$click_id     = str_repeat( 'a', 512 );
		$_GET['epik'] = $click_id;

		$data = $this->prepare_page_visit_data();

		$this->assertSame( $click_id, $data['user_data']['click_id'] );
		$this->assertSame( $click_id, WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that an over-length click ID in the Pinterest tag cookie is discarded.
	 *
	 * @return void
	 */
	public function test_default_data_discards_over_length_epik_cookie() {
		$_COOKIE['_epik'] = str_repeat( 'a', 513 );

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that an over-length click ID already stored in the session is discarded.
	 *
	 * @return void
	 */
	public function test_default_data_discards_over_length_session_click_id() {
		WC()->session->set( 'pinterest_for_woocommerce_click_id', str_repeat( 'a', 513 ) );

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that an over-length click ID in the event source URL is discarded.
	 *
	 * @return void
	 */
	public function test_default_data_discards_over_length_epik_in_event_source_url() {
		$source_url = add_query_arg( 'epik', str_repeat( 'a', 513 ), home_url( '/p/' ) );

		$data = $this->prepare_page_visit_data( $source_url );

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that a non-string query parameter yields no click ID.
	 *
	 * @return void
	 */
	public function test_default_data_ignores_array_epik_query_parameter() {
		$_GET['epik'] = array( 'x' );

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that values changed by sanitization are discarded rather than stored mutated.
	 *
	 * @dataProvider mutated_click_id_provider
	 *
	 * @param string $click_id Raw click ID that sanitize_text_field() would alter.
	 *
	 * @return void
	 */
	public function test_default_data_discards_click_id_changed_by_sanitization( string $click_id ) {
		$_GET['epik'] = $click_id;

		$data = $this->prepare_page_visit_data();

		$this->assertArrayNotHasKey( 'click_id', $data['user_data'] );
		$this->assertNull( WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Click ID values that sanitize_text_field() alters.
	 *
	 * @return array[]
	 */
	public function mutated_click_id_provider() {
		return array(
			'percent sequence' => array( 'click%41id' ),
			'html tag'         => array( 'click<b>id</b>' ),
		);
	}

	/**
	 * Tests that a literal "0" click ID is kept and persisted.
	 *
	 * @return void
	 */
	public function test_default_data_keeps_zero_click_id() {
		$_GET['epik'] = '0';

		$data = $this->prepare_page_visit_data();

		$this->assertSame( '0', $data['user_data']['click_id'] );
		$this->assertSame( '0', WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * Tests that an empty query parameter falls through to the cookie.
	 *
	 * @return void
	 */
	public function test_default_data_uses_cookie_when_epik_query_parameter_is_empty() {
		$_GET['epik']     = '';
		$_COOKIE['_epik'] = 'cookie1';

		$data = $this->prepare_page_visit_data();

		$this->assertSame( 'cookie1', $data['user_data']['click_id'] );
	}

	/**
	 * Tests that an empty event source URL parameter falls through to the query string.
	 *
	 * @return void
	 */
	public function test_default_data_uses_query_parameter_when_event_source_url_epik_is_empty() {
		$_GET['epik'] = 'getval';

		$data = $this->prepare_page_visit_data( home_url( '/p/?epik=' ) );

		$this->assertSame( 'getval', $data['user_data']['click_id'] );
	}

	/**
	 * Tests that a rejected query parameter falls through to a valid cookie.
	 *
	 * @return void
	 */
	public function test_default_data_uses_cookie_when_epik_query_parameter_is_over_length() {
		$_GET['epik']     = str_repeat( 'a', 513 );
		$_COOKIE['_epik'] = 'pinterest-cookie-click-id';

		$data = $this->prepare_page_visit_data();

		$this->assertSame( 'pinterest-cookie-click-id', $data['user_data']['click_id'] );
		$this->assertSame( 'pinterest-cookie-click-id', WC()->session->get( 'pinterest_for_woocommerce_click_id' ) );
	}

	/**
	 * The PageVisit beacon can preserve the URL of the cached storefront page.
	 */
	public function test_uses_explicit_event_source_url() {
		$source_url  = home_url( '/cached-product/?campaign=pinterest' );
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user, $source_url );

		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );

		$this->assertSame( $source_url, $data['event_source_url'] );
	}

	/**
	 * Invalid explicit source URLs fall back to the current request URL.
	 */
	public function test_invalid_explicit_event_source_url_uses_default() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user, 'javascript:alert(1)' );

		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );

		$this->assertSame( $this->get_event_source_url(), $data['event_source_url'] );
	}

	public function test_get_checkout_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$checkout = new Tracking\Data\Checkout(
			'event-id-123',
			'1234567890',
			'29.97',
			3,
			'USD',
			array(
				new Tracking\Data\Product( 'eid1', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 1 ),
				new Tracking\Data\Product( 'eid2', 2, 'Name 2', 'Furniture', 'Brand', '9.99', 'USD', 1 ),
				new Tracking\Data\Product( 'eid3', 3, 'Name 3', 'Accessories', 'Brand', '9.99', 'USD', 1 ),
			)
		);

		$data = $conversions->prepare_request_data( Tracking::EVENT_CHECKOUT, $checkout );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-123',
				'event_name'       => 'checkout',
				'action_source'    => 'web',
				'event_source_url' => $this->get_event_source_url(),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'order_id'    => '1234567890',
					'currency'    => 'USD',
					'value'       => '29.97',
					'content_ids' => array( 1, 2, 3 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 2, 'item_price' => '9.99', 'quantity' => 1 ),
						array( 'id' => 3, 'item_price' => '9.99', 'quantity' => 1 ),
					),
					'num_items'   => 3,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_add_to_cart_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$product = new Tracking\Data\Product( 'event-id-321', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 2 );

		$data = $conversions->prepare_request_data( Tracking::EVENT_ADD_TO_CART, $product );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-321',
				'event_name'       => 'add_to_cart',
				'action_source'    => 'web',
				'event_source_url' => $this->get_event_source_url(),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'currency'    => 'USD',
					'value'       => '19.98',
					'content_ids' => array( 1 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 2 ),
					),
					'num_items'   => 2,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_view_category_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$category = new Tracking\Data\Category( 'event-id-312', 1, 'Category 1' );

		$data = $conversions->prepare_request_data( Tracking::EVENT_VIEW_CATEGORY, $category );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-312',
				'event_name'       => 'view_category',
				'action_source'    => 'web',
				'event_source_url' => $this->get_event_source_url(),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_page_visit_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$product = new Tracking\Data\Product( 'event-id-132', 1, 'Name 1', 'Furniture', 'Brand', '9.99', 'USD', 1 );

		$data = $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, $product );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-132',
				'event_name'       => 'page_visit',
				'action_source'    => 'web',
				'event_source_url' => $this->get_event_source_url(),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'currency'    => 'USD',
					'value'       => '9.99',
					'content_ids' => array( 1 ),
					'contents'    => array(
						array( 'id' => 1, 'item_price' => '9.99', 'quantity' => 1 ),
					),
					'num_items'   => 1,
				),
				'language'         => 'en',
			),
			$data
		);
	}

	public function test_get_search_data() {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user );

		$search = new Tracking\Data\Search( 'event-id-111', 'Search query string...' );

		$data = $conversions->prepare_request_data( Tracking::EVENT_SEARCH, $search );

		// Remove event time data on purposes since it is dynamic and we can not test it.
		unset( $data['event_time'] );

		$this->assertEquals(
			array(
				'event_id'         => 'event-id-111',
				'event_name'       => 'search',
				'action_source'    => 'web',
				'event_source_url' => $this->get_event_source_url(),
				'partner_name'     => 'ss-woocommerce',
				'user_data'        => array(
					'client_ip_address' => 'Some IP address.',
					'client_user_agent' => 'Some user agent string.',
				),
				'custom_data'      => array(
					'search_string' => 'Search query string...',
				),
				'language'         => 'en',
			),
			$data
		);
	}

	/**
	 * Prepares page visit request data for a fresh Conversions tracker.
	 *
	 * @param string $event_source_url Optional URL where the event occurred.
	 *
	 * @return array Prepared request data.
	 */
	private function prepare_page_visit_data( string $event_source_url = '' ) {
		$user        = new User( 'Some IP address.', 'Some user agent string.' );
		$conversions = new Conversions( $user, $event_source_url );

		return $conversions->prepare_request_data( Tracking::EVENT_PAGE_VISIT, new Data\None( 'event-id-123' ) );
	}

	/**
	 * Gets the expected event source URL for the active test site.
	 *
	 * @return string
	 */
	private function get_event_source_url() {
		global $wp;

		return home_url( $wp->request );
	}
}
