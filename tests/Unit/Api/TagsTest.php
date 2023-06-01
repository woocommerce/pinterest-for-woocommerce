<?php
/**
 * Class TagsTest
 *
 * @since x.x.x
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use WP_REST_Request;
use WP_Test_REST_TestCase;

class TagsTest extends WP_Test_REST_TestCase {

	/**
	 * Tests if the tags route is registered.
	 *
	 * @return void
	 */
	public function test_tags_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/tags', $routes );
	}

	/**
	 * Tests if the tags endpoint rejects access.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests if the tags endpoint returns an error when the advertiser is missing.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_advertiser_missing() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals(
			'pinterest-for-woocommerce_tags_error',
			$response->get_data()['code'] ?? ''
		);
		$this->assertEquals(
			'No tracking tag available. [Advertiser missing]',
			$response->get_data()['message'] ?? ''
		);
	}

	/**
	 * Tests if the tags endpoint returns a list of tags available.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_tags_list() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$enhanced_match = array(
			'aem_enabled' => false,
			'md_frequency' => 1,
			'aem_fnln_enabled' => false,
			'aem_ph_enabled' => false,
			'aem_ge_enabled' => false,
			'aem_db_enabled' => false,
			'aem_loc_enabled' => false,
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) use ( $enhanced_match) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'items' => array(
								array(
									'ad_account_id'         => 'ai-123456789',
									'code_snippet'          => '<!-- Pinterest Tag 1 -->',
									'enhanced_match_status' => 'VALIDATION_COMPLETE',
									'id'                    => 'tag-id-1',
									'last_fired_time_ms'    => 0,
									'name'                  => 'Test Tag 1',
									'status'                => 'ACTIVE',
									'version'               => 2,
									'configs'               => $enhanced_match,
								),
								array(
									'ad_account_id'         => 'ai-123456789',
									'code_snippet'          => '<!-- Pinterest Tag 2 -->',
									'enhanced_match_status' => 'VALIDATION_COMPLETE',
									'id'                    => 'tag-id-2',
									'last_fired_time_ms'    => 0,
									'name'                  => 'Test Tag 2',
									'status'                => 'ACTIVE',
									'version'               => 2,
									'configs'               => $enhanced_match,
								),
								array(
									'ad_account_id'         => 'ai-123456789',
									'code_snippet'          => '<!-- Pinterest Tag 3 -->',
									'enhanced_match_status' => 'VALIDATION_COMPLETE',
									'id'                    => 'tag-id-3',
									'last_fired_time_ms'    => 0,
									'name'                  => 'Test Tag 3',
									'status'                => 'ACTIVE',
									'version'               => 2,
									'configs'               => $enhanced_match,
								),
								array(
									'ad_account_id'         => 'ai-123456789',
									'code_snippet'          => '<!-- Pinterest Tag 4 -->',
									'enhanced_match_status' => 'VALIDATION_COMPLETE',
									'id'                    => 'tag-id-4',
									'last_fired_time_ms'    => 0,
									'name'                  => 'Test Tag 4',
									'status'                => 'ACTIVE',
									'version'               => 2,
									'configs'               => $enhanced_match,
								),
							),
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
			3
		);

		$request = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$request->set_query_params(
			array(
				'advrtsr_id' => 'ai-123456789',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				array(
					'id'   => 'tag-id-1',
					'name' => 'Test Tag 1',
				),
				array(
					'id'   => 'tag-id-2',
					'name' => 'Test Tag 2',
				),
				array(
					'id'   => 'tag-id-3',
					'name' => 'Test Tag 3',
				),
				array(
					'id'   => 'tag-id-4',
					'name' => 'Test Tag 4',
				),
			),
			$response->get_data()
		);
	}

	/**
	 * Tests if the tags endpoint returns a newly created tag.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_newly_created_tag() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$body = array();

				// 1. GET tags list.
				if ( 'GET' === $parsed_args['method'] ) {
					$body = array(
						'items' => array(),
					);
				}

				// 2. POST new tag.
				if ( 'POST' === $parsed_args['method'] ) {
					$body = array(
						'ad_account_id'         => 'ai-123456789',
						'code_snippet'          => '<!-- Pinterest Tag New -->',
						'enhanced_match_status' => 'VALIDATION_COMPLETE',
						'id'                    => 'tag-id-new',
						'last_fired_time_ms'    => 0,
						'name'                  => 'Test Tag New',
						'status'                => 'ACTIVE',
						'version'               => 2,
						'configs'               => array(
							'aem_enabled' => false,
							'md_frequency' => 1,
							'aem_fnln_enabled' => false,
							'aem_ph_enabled' => false,
							'aem_ge_enabled' => false,
							'aem_db_enabled' => false,
							'aem_loc_enabled' => false,
						),
					);
				}

				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode( $body ),
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

		$request = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$request->set_query_params(
			array(
				'advrtsr_id' => 'ai-123456789',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				array(
					'id'   => 'tag-id-new',
					'name' => 'Test Tag New',
				)
			),
			$response->get_data()
		);
	}

	/**
	 * Tests if the tags endpoint returns a tags list error.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_tags_list_error() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 0,
							'message' => 'string',
						)
					),
					'response' => array(
						'code' => 500,
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$request->set_query_params(
			array(
				'advrtsr_id' => 'ai-123456789',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals(
			'No tracking tag available. [Response error]',
			$response->get_data()['message'] ?? ''
		);
	}

	/**
	 * Tests if the tags endpoint returns a tag create error.
	 *
	 * @return void
	 */
	public function test_tags_endpoint_returns_tag_create_error() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$body    = array();
				$code    = 200;
				$message = 'OK';

				// 1. GET tags list.
				if ( 'GET' === $parsed_args['method'] ) {
					$body = array(
						'items' => array(),
					);
				}

				// 2. POST new tag.
				if ( 'POST' === $parsed_args['method'] ) {
					$code    = 500;
					$message = 'Unexpected error';
					$body    = array(
						'code' => 0,
						'message' => 'string',
					);
				}

				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode( $body ),
					'response' => array(
						'code'    => $code,
						'message' => $message,
					),
					'cookies'  => array(),
					'filename' => '',
				);
			},
			10,
			3
		);

		$request = new WP_REST_Request( 'GET', '/pinterest/v1/tags' );
		$request->set_query_params(
			array(
				'advrtsr_id' => 'ai-123456789',
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals(
			'No tracking tag available. [Could not create a tag. Please check the logs for additional information.]',
			$response->get_data()['message'] ?? ''
		);
	}
}
