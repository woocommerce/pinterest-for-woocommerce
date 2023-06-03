<?php
/**
 * Class DomainVerificationTest
 *
 * @since x.x.x
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use WP_REST_Request;
use WP_Test_REST_TestCase;

class DomainVerificationTest extends WP_Test_REST_TestCase {

	/**
	 * Tests if the domain verification route is registered.
	 *
	 * @return void
	 */
	public function test_domain_verification_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/domain_verification', $routes );
	}

	/**
	 * Tests if the domain verification endpoint rejects access.
	 *
	 * @return void
	 */
	public function test_domain_verification_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/domain_verification' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/domain_verification' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests if the domain verification endpoint returns domain verification status and saves appropriate settings.
	 *
	 * @return void
	 */
	public function test_domain_verification_endpoint_verifies_a_domain() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		// Partial mock of account_data data.
		Pinterest_For_Woocommerce()::save_setting(
			'account_data',
			array(
				'username'                => 'dmytromaksiuta1',
				'id'                      => '1234567890123456789',
				'is_partner'              => true,
				'is_billing_setup'        => false,
				'verified_user_websites'  => array(),
				'is_any_website_verified' => false,
			)
		);

		add_filter( 'home_url', fn () => 'https://mysite.test/' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://api.pinterest.com/v5/user_account/websites/verification' === $url ) {
					$response = array(
						'headers'  => array(
							'content-type' => 'application/json',
						),
						'body'     => json_encode(
							array(
								'verification_code' => 'e1edcc1a43976c646367e9c6c9a9b7b6',
								'dns_txt_record'    => 'pinterest-site-verification=e1edcc1a43976c646367e9c6c9a9b7b6',
								'metatag'           => '<meta name="p:domain_verify" content="e1edcc1a43976c646367e9c6c9a9b7b6"/>',
								'filename'          => 'pinterest-e1edc.html',
								'file_content'      => 'string',
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => '',
					);
				}
				if ( 'https://api.pinterest.com/v5/user_account/websites' === $url ) {
					$response = array(
						'headers'  => array(
							'content-type' => 'application/json',
						),
						'body'     => json_encode(
							array(
								'website'     => 'mysite.test',
								'status'      => 'success',
								'verified_at' => '2022-12-14T21:03:01.602000'
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies'  => array(),
						'filename' => '',
					);
				}
				return $response;
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'POST', '/pinterest/v1/domain_verification' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				'website'      => 'mysite.test',
				'status'       => 'success',
				'verified_at'  => '2022-12-14T21:03:01.602000',
				'account_data' => array(
					'username'                => 'dmytromaksiuta1',
					'id'                      => '1234567890123456789',
					'is_partner'              => true,
					'is_billing_setup'        => false,
					'verified_user_websites'  => array( 'mysite.test' ),
					'is_any_website_verified' => true,
				)
			),
			$response->get_data()
		);
		$this->assertEquals(
			array(
				'username'                => 'dmytromaksiuta1',
				'id'                      => '1234567890123456789',
				'is_partner'              => true,
				'is_billing_setup'        => false,
				'verified_user_websites'  => array( 'mysite.test' ),
				'is_any_website_verified' => true,
			),
			Pinterest_For_Woocommerce()::get_setting( 'account_data', true )
		);
	}
}
