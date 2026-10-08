<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Notes\TokenInvalidFailure;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests disconnecting from Pinterest when the stored token is rejected.
 *
 * Every 401 fires the auth-failure action that resets the connection. The reset used to run
 * the full disconnect, whose remote cleanup requests got the same 401 and re-entered it
 * without bound until PHP's execution time ran out, which made wp-admin unreachable.
 *
 * @version x.x.x
 */
class DisconnectTest extends WP_UnitTestCase {

	/**
	 * Pinterest API requests the fake API received, as "METHOD path" strings.
	 *
	 * @var string[]
	 */
	private static $requests = array();

	/**
	 * Sets up a business account whose token Pinterest rejects.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		self::$requests = array();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'rejected-access-token' ) );
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		Pinterest_For_Woocommerce::save_setting(
			'account_data',
			array(
				'id'         => '123456789',
				'is_partner' => true,
			)
		);
		Pinterest_For_Woocommerce::save_data( 'integration_data', array( 'external_business_id' => 'ebi-123' ) );
		TokenInvalidFailure::delete_failure_note();

		add_filter( 'pre_http_request', array( self::class, 'reject_every_request' ), 10, 3 );
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		TokenInvalidFailure::delete_failure_note();
	}

	/**
	 * Any API call that gets a 401 resets the connection without calling the API again.
	 *
	 * @return void
	 */
	public function test_a_401_resets_the_connection_without_further_requests() {
		$this->assertTrue( Pinterest_For_Woocommerce::is_business_connected() );

		try {
			APIV5::get_advertisers();
			$this->fail( 'The rejected token should surface as a 401 exception.' );
		} catch ( PinterestApiException $e ) {
			$this->assertSame( 401, $e->getCode() );
		}

		$this->assertSame( array( 'GET ad_accounts' ), self::$requests, 'The reset must not call the API.' );
		$this->assert_connection_cleared();
	}

	/**
	 * A disconnect started while the token is rejected finishes with a bounded number of
	 * requests and still clears the local connection.
	 *
	 * @return void
	 */
	public function test_disconnect_with_a_rejected_token_is_bounded_and_clears_the_connection() {
		$this->assertTrue( Pinterest_For_Woocommerce::disconnect() );

		$this->assertSame(
			array( 'GET catalogs/feeds' ),
			self::$requests,
			'Only the feed listing runs: the nested reset clears the integration data before the commerce integration delete.'
		);
		$this->assert_connection_cleared();
	}

	/**
	 * Resetting the connection on its own never calls the API.
	 *
	 * @return void
	 */
	public function test_reset_connection_makes_no_requests() {
		Pinterest_For_Woocommerce::reset_connection();

		$this->assertSame( array(), self::$requests );
		$this->assert_connection_cleared();
	}

	/**
	 * Asserts that no connection data is left and that the merchant was told the token is invalid.
	 *
	 * @return void
	 */
	private function assert_connection_cleared() {
		$this->assertFalse( Pinterest_For_Woocommerce::is_connected() );
		$this->assertFalse( get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ) );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser', true ) );
		$this->assertFalse( Pinterest_For_Woocommerce::get_setting( 'account_data', true ) );
		$this->assertTrue( TokenInvalidFailure::note_exists() );
	}

	/**
	 * Fakes a Pinterest API that rejects the token on every request, recording each one.
	 *
	 * @param false|array $response Preempted response.
	 * @param array       $args     Request arguments.
	 * @param string      $url      Request URL.
	 * @return false|array
	 */
	public static function reject_every_request( $response, $args, $url ) {
		if ( false === strpos( $url, 'api.pinterest.com' ) ) {
			return $response;
		}

		self::$requests[] = $args['method'] . ' ' . preg_replace( '#^/v5/#', '', (string) wp_parse_url( $url, PHP_URL_PATH ) );

		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'code'    => 2,
					'message' => 'Authentication failed.',
				)
			),
			'response' => array(
				'code'    => 401,
				'message' => 'Unauthorized',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}
}
