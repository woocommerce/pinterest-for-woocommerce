<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\CommerceIntegration;
use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Native commerce integration persistence and scheduled retry contracts.
 *
 * @version x.x.x
 */
class CommerceIntegrationTest extends WP_UnitTestCase {

	private const RETRY = 'pinterest-for-woocommerce-create-commerce-integration-retry';
	/** @var array Captured native HTTP requests. */
	private $requests = array();
	/** @var int Remote response status. */
	private $response_code = 500;
	/** @var string Remote response body. */
	private $response_body = '{"code":1,"message":"Try again later."}';
	/** @var mixed Request-local API token cache before this test. */
	private $previous_api_token;

	/** @return void */
	public function setUp(): void {
		parent::setUp();
		$token = new \ReflectionProperty( \Automattic\WooCommerce\Pinterest\API\Base::class, 'token' );
		$token->setAccessible( true );
		$this->previous_api_token = $token->getValue();
		$token->setValue( null, null );
		Pinterest_For_Woocommerce::save_token_data( array( 'access_token' => 'local-commerce-token' ) );
		Pinterest_For_Woocommerce::save_connection_info_data(
			array(
				'merchant_id'   => 'merchant-42',
				'advertiser_id' => 'advertiser-7',
				'tag_id'        => 'tag-9',
			)
		);
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
		Pinterest_For_Woocommerce::save_integration_data(
			array(
				'id'                   => 'previous-integration',
				'external_business_id' => 'previous-business',
				'partner_metadata'     => 'previous-metadata',
			)
		);
		add_filter( 'pre_http_request', array( $this, 'receive_request' ), 10, 3 );
		add_filter( 'wc_pinterest_external_business_id', array( $this, 'business_id' ) );
	}

	/** @return void */
	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'receive_request' ), 10 );
		remove_filter( 'wc_pinterest_external_business_id', array( $this, 'business_id' ) );
		$token = new \ReflectionProperty( \Automattic\WooCommerce\Pinterest\API\Base::class, 'token' );
		$token->setAccessible( true );
		$token->setValue( null, $this->previous_api_token );
		parent::tearDown();
	}

	/** @return string Store identity supplied by the documented extension point. */
	public function business_id() {
		return 'business-42';
	}

	/**
	 * Substitute only the remote transport; record native requests for assertions outside it.
	 *
	 * @param mixed  $response Existing response.
	 * @param array  $args Request arguments.
	 * @param string $url Request URL.
	 * @return array HTTP response.
	 */
	public function receive_request( $response, $args, $url ) {
		$this->requests[] = array(
			'method' => $args['method'],
			'path'   => wp_parse_url( $url, PHP_URL_PATH ),
			'body'   => is_string( $args['body'] ) ? json_decode( $args['body'], true ) : $args['body'],
		);
		return array(
			'headers'  => array(),
			'body'     => $this->response_body,
			'response' => array(
				'code'    => $this->response_code,
				'message' => '',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * @dataProvider backoffs
	 * @param int $attempt Attempt number.
	 * @param int $delay Intended backoff, or zero when exhausted.
	 */
	public function test_failed_create_retains_connection_and_schedules_one_intended_retry( $attempt, $delay ) {
		$before = Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		$start  = time();
		$this->assertSame( array(), CommerceIntegration::handle_create( $attempt ) );
		$this->assertSame( array(), CommerceIntegration::handle_create( $attempt ) );
		$end = time();
		$this->assertSame( $before, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertSame(
			array( 'access_token' => 'local-commerce-token' ),
			Pinterest_For_Woocommerce::get_access_token()
		);
		$this->assertSame(
			'advertiser-7',
			Pinterest_For_Woocommerce::get_data( 'connection_info_data', true )['advertiser_id']
		);
		$this->assertTrue( Pinterest_For_Woocommerce::get_setting( 'enable_debug_logging' ) );
		$this->assertCount( 2, $this->requests );
		foreach ( $this->requests as $request ) {
			$this->assertSame( 'POST', $request['method'] );
			$this->assertSame( '/v5/integrations/commerce', $request['path'] );
			$this->assertSame( 'merchant-42', $request['body']['connected_merchant_id'] );
			$this->assertSame( 'advertiser-7', $request['body']['connected_advertiser_id'] );
			$this->assertSame( 'tag-9', $request['body']['connected_tag_id'] );
		}
		$jobs = $this->pending_retries();
		$this->assertCount( $delay ? 1 : 0, $jobs );
		if ( $delay ) {
			$job = reset( $jobs );
			$this->assertSame( array( 'attempt' => $attempt + 1 ), $job->get_args() );
			$this->assertGreaterThanOrEqual( $start + $delay, $job->get_schedule()->get_date()->getTimestamp() );
			$this->assertLessThanOrEqual( $end + $delay, $job->get_schedule()->get_date()->getTimestamp() );
		}
	}

	/** @return array Independent expected backoffs. */
	public function backoffs() {
		return array(
			'minute'    => array( 0, 60 ),
			'hour'      => array( 1, 3600 ),
			'day'       => array( 2, 86400 ),
			'exhausted' => array( 3, 0 ),
		);
	}

	/** @return void */
	public function test_native_retry_success_persists_response_and_unchanged_sync_sends_nothing() {
		CommerceIntegration::handle_create();
		$jobs = $this->pending_retries();
		$this->assertCount( 1, $jobs );
		$id                  = key( $jobs );
		$metadata            = $this->expected_metadata();
		$expected            = array(
			'id'                   => 'integration-88',
			'external_business_id' => 'business-42',
			'connected_user_id'    => 'user-3',
			'partner_metadata'     => $metadata,
		);
		$this->response_code = 200;
		$this->response_body = wp_json_encode( $expected );
		\ActionScheduler_QueueRunner::instance()->process_action( $id );
		$this->assertSame( 'complete', \ActionScheduler::store()->get_status( $id ) );
		$this->assertSame( $expected, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 0, $this->pending_retries() );
		$this->assertCount( 2, $this->requests );
		CommerceIntegration::handle_sync();
		$this->assertCount( 2, $this->requests );
		$this->assertSame( $expected, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
	}

	/** @return void */
	public function test_sync_suppresses_duplicate_creation_then_patches_changed_metadata_once() {
		Pinterest_For_Woocommerce::remove_data( 'integration_data' );
		CommerceIntegration::handle_create();
		$this->assertCount( 1, $this->pending_retries() );
		CommerceIntegration::handle_sync();
		$this->assertCount( 1, $this->requests );
		CommerceIntegration::maybe_unregister_retries();
		Pinterest_For_Woocommerce::save_integration_data(
			array(
				'id'                   => 'integration-88',
				'external_business_id' => 'business-42',
				'partner_metadata'     => 'outdated',
			)
		);
		$before    = Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		$exception = null;
		try {
			CommerceIntegration::handle_sync();
		} catch ( PinterestApiException $error ) {
			$exception = $error;
		}
		$this->assertInstanceOf( PinterestApiException::class, $exception );
		$this->assertSame( 500, $exception->getCode() );
		$this->assertSame( $before, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 0, $this->pending_retries() );
		$this->response_code = 200;
		$metadata            = $this->expected_metadata();
		$expected            = array(
			'id'                   => 'integration-88',
			'external_business_id' => 'business-42',
			'partner_metadata'     => $metadata,
		);
		$this->response_body = wp_json_encode( $expected );
		CommerceIntegration::handle_sync();
		$this->assertSame( $expected, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 3, $this->requests );
		$this->assertSame(
			array(
				'method' => 'PATCH',
				'path'   => '/v5/integrations/commerce/business-42',
				'body'   => array(
					'external_business_id'    => 'business-42',
					'connected_merchant_id'   => 'merchant-42',
					'connected_advertiser_id' => 'advertiser-7',
					'partner_metadata'        => $metadata,
					'connected_tag_id'        => 'tag-9',
				),
			),
			$this->requests[2]
		);
		CommerceIntegration::handle_sync();
		$this->assertCount( 3, $this->requests );
	}

	/**
	 * @dataProvider malformed_responses
	 * @param string $body Remote malformed body.
	 * @return void
	 */
	public function test_malformed_create_response_preserves_state_and_uses_retry( $body ) {
		$this->response_code = 200;
		$this->response_body = $body;
		$before              = Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		$this->assertSame( array(), CommerceIntegration::handle_create() );
		$this->assertSame( $before, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 1, $this->requests );
		$this->assertCount( 1, $this->pending_retries() );
		$this->assertTrue( Pinterest_For_Woocommerce::is_connected() );
	}

	/** @return array Malformed integration responses, independent of request data. */
	public function malformed_responses() {
		return array(
			'invalid JSON'     => array( '{bad' ),
			'null'             => array( 'null' ),
			'scalar'           => array( '42' ),
			'empty object'     => array( '{}' ),
			'missing identity' => array( '{"id":"integration-88"}' ),
			'numeric identity' => array( '{"external_business_id":123}' ),
			'empty identity'   => array( '{"external_business_id":""}' ),
		);
	}

	/** @return void */
	public function test_public_transport_retains_partial_array_response_compatibility() {
		$this->response_code = 200;
		$this->response_body = '{"id":"integration-88"}';
		$this->assertSame(
			array( 'id' => 'integration-88' ),
			APIV5::create_commerce_integration( array( 'external_business_id' => 'business-42' ) )
		);
		$this->assertSame(
			array( 'id' => 'integration-88' ),
			APIV5::update_commerce_integration( 'business-42', array() )
		);
		$this->assertCount( 2, $this->requests );
	}

	/** @return void */
	public function test_create_without_metadata_retains_identity_and_next_sync_repairs_it() {
		$this->response_code = 200;
		$partial             = array(
			'id'                   => 'integration-88',
			'external_business_id' => 'business-42',
		);
		$this->response_body = wp_json_encode( $partial );
		$this->assertSame( $partial, CommerceIntegration::handle_create() );
		$this->assertSame( $partial, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 0, $this->pending_retries() );
		$metadata            = $this->expected_metadata();
		$expected            = $partial + array( 'partner_metadata' => $metadata );
		$this->response_body = wp_json_encode( $expected );
		CommerceIntegration::handle_sync();
		$this->assertSame( $expected, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 2, $this->requests );
		$this->assertSame( 'PATCH', $this->requests[1]['method'] );
		CommerceIntegration::handle_sync();
		$this->assertCount( 2, $this->requests );
	}

	/**
	 * @dataProvider malformed_responses
	 * @param string $body Remote malformed body.
	 * @return void
	 */
	public function test_malformed_sync_response_retains_saved_integration( $body ) {
		$this->response_code = 200;
		$this->response_body = $body;
		$before              = Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		$exception           = null;
		try {
			CommerceIntegration::handle_sync();
		} catch ( PinterestApiException $error ) {
			$exception = $error;
		}
		$this->assertInstanceOf( PinterestApiException::class, $exception );
		$this->assertSame( 502, $exception->getCode() );
		$this->assertSame( $before, Pinterest_For_Woocommerce::get_data( 'integration_data', true ) );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'PATCH', $this->requests[0]['method'] );
		$this->assertCount( 0, $this->pending_retries() );
	}

	/** @return string Independently specified metadata expected by the remote protocol. */
	private function expected_metadata() {
		global $wp_version;
		return wp_json_encode(
			array(
				'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				'wc_version'     => WC_VERSION,
				'wp_version'     => $wp_version,
				'locale'         => get_locale(),
				'currency'       => get_woocommerce_currency(),
			)
		);
	}

	/** @return array Pending native retry actions indexed by action ID. */
	private function pending_retries() {
		return as_get_scheduled_actions(
			array(
				'hook'     => self::RETRY,
				'group'    => PINTEREST_FOR_WOOCOMMERCE_PREFIX,
				'status'   => 'pending',
				'per_page' => -1,
			)
		);
	}
}
