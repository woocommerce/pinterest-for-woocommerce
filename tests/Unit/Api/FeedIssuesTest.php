<?php
declare(strict_types=1);

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Pinterest_For_Woocommerce;
use WC_Helper_Product;
use WP_REST_Request;
use WP_Test_REST_TestCase;

class FeedIssuesTest extends WP_Test_REST_TestCase {

	/**
	 * Tests if the feed issues route is registered.
	 *
	 * @return void
	 */
	public function test_feed_issues_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/feed_issues', $routes );
	}

	/**
	 * Tests if the feed issues endpoint rejects access.
	 *
	 * @return void
	 */
	public function test_feed_issues_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/feed_issues' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/feed_issues' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests if the feed issues endpoint returns feed issues.
	 *
	 * @return void
	 */
	public function test_feed_issues_endpoint_returns_feed_issues() {
		$user              = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );
		$product1          = WC_Helper_Product::create_simple_product();
		$product2          = WC_Helper_Product::create_simple_product();
		$mock_account_data = array (
			'verified_user_websites'  => array ( 'mysite.test' ),
			'is_any_website_verified' => true,
		);
		$mock_feed_id      = uniqid();

		Pinterest_For_WooCommerce::save_setting( 'tracking_advertiser', 'ai-123456789' );
		Pinterest_For_WooCommerce::save_setting( 'account_data', $mock_account_data );
		Pinterest_For_Woocommerce::save_setting( 'product_sync_enabled', true );
		Pinterest_For_Woocommerce::save_data( 'feed_registered', $mock_feed_id  );

		add_filter(
			'home_url',
			function () {
				return 'https://mysite.test/';
			}
		);

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) use ( $mock_feed_id, $product1, $product2 ) {
				if ( str_contains( $url, "catalogs/feeds/{$mock_feed_id}/processing_results" ) ) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'items' => array(
									array(
										'id' => '1234567890123456789',
									)
								),
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies' => array(),
						'filename' => '',
					);
				}
				if ( str_contains( $url, "catalogs/processing_results/1234567890123456789/item_issues" ) ){
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'items' => array (
									0 =>
										array (
											'warnings' =>
												array (
													'OPTIONAL_CONDITION_MISSING' =>
														array (
															'attribute_name' => 'CONDITION',
															'provided_value' => '',
														),
													'OPTIONAL_PRODUCT_CATEGORY_MISSING' =>
														array (
															'attribute_name' => 'GOOGLE_PRODUCT_CATEGORY',
															'provided_value' => '',
														),
												),
											'item_number' => 0,
											'item_id' => $product1->get_id(),
											'errors' =>
												array (
													'IMAGE_LINK_MISSING' =>
														array (
															'attribute_name' => 'IMAGE_LINK',
															'provided_value' => '',
														),
												),
										),
									1 =>
										array (
											'warnings' =>
												array (
													'OPTIONAL_CONDITION_MISSING' =>
														array (
															'attribute_name' => 'CONDITION',
															'provided_value' => '',
														),
													'OPTIONAL_PRODUCT_CATEGORY_MISSING' =>
														array (
															'attribute_name' => 'GOOGLE_PRODUCT_CATEGORY',
															'provided_value' => '',
														),
												),
											'item_number' => 1,
											'item_id' => $product2->get_id(),
											'errors' =>
												array (
													'IMAGE_LINK_MISSING' =>
														array (
															'attribute_name' => 'IMAGE_LINK',
															'provided_value' => '',
														),
												),
										)
								)
							)
						),
						'response' => array(
							'code'    => 200,
							'message' => 'OK',
						),
						'cookies' => array(),
						'filename' => '',
					);
				}
				return $response;
			},
			10,
			3
		);

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/feed_issues' );
		$request->set_query_params( array(
			'paged' => 1,
			'per_page' => 4
		) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$response_data = $response->get_data();
		$this->assertEquals( $response_data['total_rows'], 6 );
		$this->assertCount( 4, $response_data['lines'] );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/feed_issues' );
		$request->set_query_params( array(
			'paged' => 2,
			'per_page' => 4
		) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 200, $response->get_status() );
		$response_data = $response->get_data();
		$this->assertEquals( $response_data['total_rows'], 6 );
		$this->assertCount( 2, $response_data['lines'] );
	}
}
