<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\Merchants;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Merchant registration retry tests.
 *
 * @version x.x.x
 */
class MerchantsTest extends WP_UnitTestCase {

	/**
	 * Retries wait for an expiring cache, including after a successful registration reset.
	 *
	 * @dataProvider retry_delays
	 * @param mixed $stored_delay Previously saved delay.
	 * @param int   $delay        Expected initial retry delay.
	 * @return void
	 */
	public function test_registration_failure_backs_off( $stored_delay, $delay ) {
		LocalFeedConfigs::deregister();
		Pinterest_For_Woocommerce::save_data( 'local_feed_ids', array( Pinterest_For_Woocommerce::get_base_country() => 'retry-test' ) );
		Pinterest_For_Woocommerce::save_data( 'create_merchant_delay', $stored_delay );
		$requests = 0;
		$cached   = array();
		$respond  = function ( $response, $args, $url ) use ( &$requests ) {
			if ( false === strpos( $url, '/catalogs/partner/connect/' ) ) {
				return $response;
			}
			++$requests;
			return array(
				'headers'  => array(),
				'response' => array(
					'code'    => 429,
					'message' => 'Too Many Requests',
				),
				'body'     => wp_json_encode( array( 'message' => 'Try again later.' ) ),
			);
		};
		$capture  = function ( $key, $value, $expiration ) use ( &$cached ) {
			if ( 0 === strpos( $key, PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_create_merchant_error_v2_' ) ) {
				$cached = array( $key, $expiration );
			}
		};
		add_filter( 'pre_http_request', $respond, 10, 3 );
		add_action( 'set_transient', $capture, 10, 3 );
		try {
			$this->assert_registration_failure();
			$this->assertSame( 1, $requests );
			$this->assertSame( $delay, $cached[1] );
			$this->assertSame( min( $delay * 2, 6 * HOUR_IN_SECONDS ), Pinterest_For_Woocommerce::get_data( 'create_merchant_delay' ) );
			$this->assert_registration_failure();
			$this->assertSame( 1, $requests, 'The cached failure must suppress repeated requests.' );

			// Expire the observed retry interval with any cache backend.
			delete_transient( $cached[0] );
			// Earlier zero-delay failures can leave permanent entries even after another request resets the delay.
			$legacy_key = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' . substr( $cached[0], strrpos( $cached[0], '_' ) + 1 );
			set_transient( $legacy_key, 429, 0 );
			$this->assert_registration_failure();
			$this->assertSame( 2, $requests );
			$this->assertSame( min( $delay * 2, 6 * HOUR_IN_SECONDS ), $cached[1] );
		} finally {
			remove_filter( 'pre_http_request', $respond, 10 );
			remove_action( 'set_transient', $capture, 10 );
			if ( $cached ) {
				delete_transient( $cached[0] );
			}
			if ( isset( $legacy_key ) ) {
				delete_transient( $legacy_key );
			}
			LocalFeedConfigs::deregister();
		}
	}

	/**
	 * Stored delays from new, successful and repeatedly failing registrations.
	 *
	 * @return array
	 */
	public function retry_delays() {
		return array(
			'new registration'   => array( null, MINUTE_IN_SECONDS ),
			'successful reset'   => array( false, MINUTE_IN_SECONDS ),
			'repeated failures'  => array( 2 * MINUTE_IN_SECONDS, 2 * MINUTE_IN_SECONDS ),
			'capped retry delay' => array( 6 * HOUR_IN_SECONDS, 6 * HOUR_IN_SECONDS ),
		);
	}

	/**
	 * Verify the original HTTP error remains available to callers.
	 *
	 * @return void
	 */
	private function assert_registration_failure() {
		try {
			Merchants::update_or_create_merchant();
			$this->fail( 'Expected a failed merchant registration.' );
		} catch ( \Exception $e ) {
			$this->assertSame( 429, $e->getCode() );
		}
	}
}
