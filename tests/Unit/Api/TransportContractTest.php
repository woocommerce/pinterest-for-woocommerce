<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Transport failures, cache invalidation and preserved connection state through public callers.
 *
 * @version x.x.x
 */
class TransportContractTest extends WP_Test_REST_TestCase {

	/** @var array Captured HTTP calls. */
	private $calls = array();
	/** @var array Remote response. */
	private $response;
	/** @var array Sequential remote responses. */
	private $responses = array();
	/** @var mixed Original request-local token cache. */
	private $original_token;

	/** @return void */
	public function setUp(): void {
		parent::setUp();
		$cache = new \ReflectionProperty( Base::class, 'token' );
		$cache->setAccessible( true );
		$this->original_token = $cache->getValue();
		$cache->setValue( null, null );
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'token-before' ) );
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', 'ad-before' );
		Pinterest_For_Woocommerce::save_setting( 'tracking_tag', 'tag-before' );
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
		Pinterest_For_Woocommerce::save_setting(
			'account_data',
			array(
				'id'         => 'account-before',
				'is_partner' => true,
			)
		);
		Pinterest_For_Woocommerce::save_integration_data(
			array(
				'external_business_id'    => 'business-before',
				'connected_advertiser_id' => 'ad-before',
			)
		);
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'administrator' ) ) );
		$this->response = $this->http_response( 200, '{"items":[]}' );
		add_filter( 'pre_http_request', array( $this, 'receive' ), 10, 3 );
	}

	/** @return void */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'receive' ), 10 );
		$cache = new \ReflectionProperty( Base::class, 'token' );
		$cache->setAccessible( true );
		$cache->setValue( null, $this->original_token );
		parent::tearDown();
	}

	/**
	 * @param mixed  $response Previous HTTP response.
	 * @param array  $args Native HTTP arguments.
	 * @param string $url Native URL.
	 * @return array Fake remote response.
	 */
	public function receive( $response, $args, $url ) {
		$this->calls[] = array(
			'url'           => $url,
			'method'        => $args['method'],
			'authorization' => $args['headers']['Authorization'],
			'body'          => $args['body'],
		);
		return $this->responses ? array_shift( $this->responses ) : $this->response;
	}

	/** @return void */
	public function test_no_content_commerce_create_retains_prior_integration_and_schedules_retry() {
		Pinterest_For_Woocommerce::save_connection_info_data( array( 'advertiser_id' => 'ad-before' ) );
		$prior          = Pinterest_For_Woocommerce::get_data( 'integration_data' );
		$this->response = $this->http_response( 204, null );
		$this->assertSame( array(), Pinterest_For_Woocommerce::create_commerce_integration() );
		$this->assertSame( $prior, Pinterest_For_Woocommerce::get_data( 'integration_data' ) );
		$this->assertTrue(
			as_has_scheduled_action(
				'pinterest-for-woocommerce-create-commerce-integration-retry',
				array( 'attempt' => 1 ),
				'pinterest-for-woocommerce'
			)
		);
		$this->assertCount( 1, $this->calls );
		$this->assertSame( 'POST', $this->calls[0]['method'] );
		$this->assertSame( 'https://api.pinterest.com/v5/integrations/commerce', $this->calls[0]['url'] );
	}

	/** @return void */
	public function test_no_content_tags_list_cannot_create_a_remote_tag() {
		$this->response = $this->http_response( 204, null );
		$request        = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$request->set_param( 'advrtsr_id', 'ad-before' );
		$settings = get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'pinterest-for-woocommerce_tags_error', $response->get_data()['code'] );
		$this->assertSame( $settings, get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) );
		$this->assertCount( 1, $this->calls );
		$this->assertSame( 'GET', $this->calls[0]['method'] );
	}

	/**
	 * @dataProvider transient_failures
	 * @param int $code Remote HTTP error.
	 * @return void
	 */
	public function test_connect_failure_keeps_previous_settings_and_makes_no_billing_or_credit_calls( $code ) {
		$this->response = $this->http_response( $code, '{"code":8,"message":"Try later."}' );
		$settings       = get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME );
		$data           = get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME );
		$request        = new WP_REST_Request( 'POST', '/pinterest/v1/tagowner' );
		$request->set_param( 'advrtsr_id', 'ad-after' );
		$request->set_param( 'tag_id', 'tag-after' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'pinterest-for-woocommerce_advertiser_connect_error', $response->get_data()['code'] );
		$this->assertSame( $settings, get_option( PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME ) );
		$this->assertSame( $data, get_option( PINTEREST_FOR_WOOCOMMERCE_DATA_NAME ) );
		$this->assertSame(
			array(
				array(
					'url'           => 'https://api.pinterest.com/v5/integrations/commerce/business-before',
					'method'        => 'PATCH',
					'authorization' => 'Bearer token-before',
					'body'          => '{"connected_advertiser_id":"ad-after","connected_tag_id":"tag-after"}',
				),
			),
			$this->calls
		);
	}

	/** @return void */
	public function test_feed_cache_tracks_advertiser_changes_and_explicit_native_invalidation() {
		$this->response = $this->http_response( 200, '{"items":[{"id":"feed-a"}]}' );
		APIV5::get_feeds( 'ad-before' );
		$this->response = $this->http_response( 200, '{"items":[{"id":"feed-b"}]}' );
		$this->assertSame( array( 'items' => array( array( 'id' => 'feed-b' ) ) ), APIV5::get_feeds( 'ad-after' ) );
		$this->assertSame( array( 'items' => array( array( 'id' => 'feed-a' ) ) ), APIV5::get_feeds( 'ad-before' ) );
		$this->assertCount( 2, $this->calls );
		APIV5::invalidate_feeds_cache( 'ad-before' );
		$this->response = $this->http_response( 200, '{"items":[{"id":"feed-a-new"}]}' );
		$this->assertSame(
			array( 'items' => array( array( 'id' => 'feed-a-new' ) ) ),
			APIV5::get_feeds( 'ad-before' )
		);
		$this->assertCount( 3, $this->calls );
		$this->assertSame(
			array(
				'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=ad-before',
				'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=ad-after',
				'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=ad-before',
			),
			array_column( $this->calls, 'url' )
		);
	}

	/** @return void */
	public function test_malformed_throttle_body_keeps_http_status_and_is_never_cached() {
		$this->response = $this->http_response( 429, '<html>Slow down</html>' );
		for ( $attempt = 0; $attempt < 2; $attempt++ ) {
			$exception = null;
			try {
				APIV5::get_feeds( 'ad-before' );
			} catch ( PinterestApiException $error ) {
				$exception = $error;
			}
			$this->assertInstanceOf( PinterestApiException::class, $exception );
			$this->assertSame( 429, $exception->getCode() );
		}
		$this->assertCount( 2, $this->calls );
		$this->assertFalse(
			APIV5::get_cached_response( 'catalogs/feeds?ad_account_id=ad-before', 'GET', array(), '' )
		);
		$this->assertSame( array( 'access_token' => 'token-before' ), Pinterest_For_Woocommerce::get_access_token() );
	}

	/** @return array Retriable remote account errors. */
	public function transient_failures() {
		return array(
			'throttled'    => array( 429 ),
			'server error' => array( 500 ),
		);
	}

	/**
	 * @param int         $code HTTP response status.
	 * @param string|null $body HTTP response body.
	 * @return array WordPress HTTP response.
	 */
	private function http_response( $code, $body ) {
		$response = array(
			'headers'  => array(),
			'response' => array(
				'code'    => $code,
				'message' => '',
			),
			'cookies'  => array(),
		);
		if ( null !== $body ) {
			$response['body'] = $body;
		}
		return $response;
	}
}
