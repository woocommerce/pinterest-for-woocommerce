<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Logger;
use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\RefreshToken;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;
use WC_Logger;
use WP_Error;
use WP_UnitTestCase;

/**
 * HTTP diagnostics must not copy credentials or customer data into logs.
 */
class LoggerTest extends WP_UnitTestCase {

	/** @var mixed Original logger. */
	private $original_logger;

	/** @var array Captured messages. */
	private $messages = array();

	/** @var array Captured log sources. */
	private $sources = array();

	/**
	 * Capture native logger calls.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->original_logger = Logger::$logger;
		$this->messages        = array();
		$this->sources         = array();
		Logger::$logger        = $this->getMockBuilder( WC_Logger::class )->disableOriginalConstructor()->onlyMethods( array( 'log' ) )->getMock();
		Logger::$logger->method( 'log' )->willReturnCallback(
			function ( $level, $message, $context ) {
				$this->messages[] = $message;
				$this->sources[]  = $context['source'];
			}
		);
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', true );
	}

	/**
	 * Restore the caller logger.
	 */
	public function tearDown(): void {
		Logger::$logger = $this->original_logger;
		parent::tearDown();
	}

	/**
	 * Log transport diagnostics without request or response contents.
	 */
	public function test_http_diagnostics_omit_sensitive_fields() {
		Logger::log_request(
			'https://example.test/v5/local-path?access_token=local-secret-query',
			array(
				'method'  => 'POST',
				'headers' => array( 'Authorization' => 'Bearer local-secret-header' ),
				'body'    => array( 'nested' => array( 'refresh_token' => 'local-secret-body' ) ),
			)
		);
		Logger::log_response(
			array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'headers'  => array( 'set-cookie' => 'local-secret-cookie' ),
				'body'     => 'local-secret-opaque-body',
			)
		);
		Logger::log_response( new WP_Error( 'http_request_failed', 'local-transport-error' ) );
		Logger::log_request( '', array( 'method' => 'GET' ) );

		$this->assertCount( 4, $this->messages );
		$messages = implode( "\n", $this->messages );
		$this->assertStringNotContainsString( 'local-secret', $messages );
		$this->assertStringNotContainsString( '?', $messages );
		$this->assertStringContainsString( 'POST Request: /v5/local-path', $this->messages[0] );
		$this->assertStringContainsString( 'Status: 200 OK', $this->messages[1] );
		$this->assertStringContainsString( 'http_request_failed: local-transport-error', $this->messages[2] );
		$this->assertStringContainsString( 'GET Request: (unknown path)', $this->messages[3] );
	}

	/**
	 * Subclasses written against the original two-parameter log_response() still load.
	 */
	public function test_log_response_keeps_its_two_parameter_signature() {
		$logger = new class() extends Logger {
			/**
			 * Original signature override that raises every response to an error.
			 *
			 * @param array|WP_Error $response The body of the response.
			 * @param string         $level    The level of the message.
			 */
			public static function log_response( $response, $level = 'debug' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
				parent::log_response( $response, 'error' );
			}
		};

		$logger::log_response( new WP_Error( 'http_request_failed', 'local-transport-error' ) );
		Logger::log_feature_response( new WP_Error( 'http_request_failed', 'local-transport-error' ), 'local-feature' );

		$this->assertSame( array( PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX, PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-local-feature' ), $this->sources );
	}

	/**
	 * Keep native token exchange output while omitting credentials from logs.
	 */
	public function test_token_exchange_retains_tokens_without_logging_them() {
		$body = array(
			'status' => 'success',
			'data'   => array(
				'access_token'  => 'local-secret-access',
				'refresh_token' => 'local-secret-refresh',
			),
		);
		$http = function () use ( $body ) {
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => wp_json_encode( $body ),
			);
		};
		add_filter( 'pre_http_request', $http );
		try {
			$this->assertSame( $body, APIV5::exchange_token() );
		} finally {
			remove_filter( 'pre_http_request', $http );
		}
		$this->assertNotEmpty( $this->messages );
		$this->assertStringNotContainsString( 'local-secret', implode( "\n", $this->messages ) );
	}

	/**
	 * Renewal diagnostics use the status-only logger and preserve parsed output.
	 */
	public function test_renewal_response_uses_status_only_diagnostics() {
		$body = array(
			'access_token' => 'fixture-access',
			'expires_in'   => DAY_IN_SECONDS,
		);
		$http = static function () use ( $body ) {
			return array(
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'headers'  => array( 'set-cookie' => 'fixture-session=value' ),
				'cookies'  => array(
					new \WP_Http_Cookie(
						array(
							'name'  => 'fixture',
							'value' => 'value',
						)
					),
				),
				'body'     => wp_json_encode( $body ),
			);
		};
		add_filter( 'pre_http_request', $http );
		try {
			$method = new \ReflectionMethod( RefreshToken::class, 'refresh_token' );
			$method->setAccessible( true );
			$this->assertSame( $body, $method->invoke( null, array( 'refresh_token' => Crypto::encrypt( 'fixture-refresh' ) ) ) );
		} finally {
			remove_filter( 'pre_http_request', $http );
		}
		$this->assertCount( 2, $this->messages );
		$this->assertSame( "Response: \n\nStatus: 200 OK\n", $this->messages[1] );
		$this->assertSame( $this->sources[0], $this->sources[1] );
	}

	/**
	 * @dataProvider debug_modes
	 * @param bool $debug Whether debug logging is enabled.
	 */
	public function test_http_failure_logs_diagnostics_without_query_or_body( $debug ) {
		Pinterest_For_Woocommerce::save_setting( 'enable_debug_logging', $debug );
		$http = function () {
			return array(
				'response' => array(
					'code'    => 400,
					'message' => 'Bad Request',
				),
				'headers'  => array( 'set-cookie' => 'local-secret-cookie' ),
				'body'     => wp_json_encode(
					array(
						'code'    => 123,
						'message' => 'local-upstream-message',
						'details' => 'local-secret-body',
					)
				),
			);
		};
		add_filter( 'pre_http_request', $http );
		try {
			APIV5::make_request( 'local-path?access_token=local-secret-query' );
			$this->fail( 'The API failure must still be thrown.' );
		} catch ( PinterestApiException $error ) {
			$this->assertSame( 400, $error->getCode() );
			$this->assertSame( 123, $error->get_pinterest_code() );
			$this->assertSame( 'local-upstream-message', $error->getMessage() );
		} finally {
			remove_filter( 'pre_http_request', $http );
		}
		$messages = implode( "\n", $this->messages );
		$this->assertStringNotContainsString( 'local-secret', $messages );
		$this->assertStringNotContainsString( '?', $messages );
		$this->assertStringContainsString( "POST Request: /v5/local-path\nStatus Code: 400\nAPI response: local-upstream-message\nPinterest Code: 123", $messages );
	}

	/**
	 * Cover diagnostics with debug logging both off and on.
	 */
	public function debug_modes() {
		return array(
			'disabled' => array( false ),
			'enabled'  => array( true ),
		);
	}
}
