<?php

use Automattic\WooCommerce\Pinterest\API\Base;
use Automattic\WooCommerce\Pinterest\UnauthorizedAccessMonitor;

class BaseTest extends WP_UnitTestCase {

	/**
	 * If Pinterest API returns 401 Unauthorized response, then it should set token renewal required flag.
	 *
	 * @return void
	 * @throws \Automattic\WooCommerce\Pinterest\PinterestApiException
	 */
	public function test_pinterest_401_unauthorized_response_sets_token_renewal_required_flag() {
		$this->expectException( Exception::class );
		$this->expectExceptionCode( 401 );

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				return array(
					'body'    => json_encode(
						array(
							'status'        => 'failure',
							'code'          => 2,
							'data'          => null,
							'message'       => 'Authentication failed.',
							'endpoint_name' => '{ENDPOINT_NAME}',
							'error'         => array(
								'message' => 'None',
							),
						)
					),
					'headers' => array(
						'content-type' => 'application/json',
					),
					'response' => array(
						'code'    => 401,
						'message' => 'Unauthorized'
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$this->assertFalse( get_transient( 'pinterest_for_woocommerce_renew_token_required' ) );

		Base::make_request( 'GET', '/v3/catalogs' );

		$this->assertTrue( get_transient( 'pinterest_for_woocommerce_renew_token_required' ) );
	}
}
