<?php
/**
 * Class OptionsTest.
 *
 * @since 1.4.0
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Pinterest_For_Woocommerce;
use WP_REST_Request;
use WP_Test_REST_TestCase;

class SyncSettingsTest extends WP_Test_REST_TestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Tests if the sync settings route is registered.
	 *
	 * @return void
	 */
	public function test_settings_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/sync_settings', $routes );
	}

	/**
	 * Tests if the sync settings endpoints reject access.
	 *
	 * @return void
	 */
	public function test_sync_settings_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/sync_settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/sync_settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests endpoint returns settings synced with Pinterest side.
	 *
	 * @return void
	 */
	public function test_sync_settings_endpoint_returns_settings_synced_with_pinterest() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Pinterest_For_WooCommerce::save_setting( 'track_conversions', true );
		Pinterest_For_Woocommerce::save_setting( 'automatic_enhanced_match_support', false );
		Pinterest_For_WooCommerce::save_setting( 'tracking_advertiser', 'ai-123456789' );
		Pinterest_For_WooCommerce::save_setting( 'tracking_tag', 'ti-123456789' );

		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( 'https://api.pinterest.com/v5/ad_accounts/ai-123456789/conversion_tags/ti-123456789' === $url ) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'configs' => array(
									'aem_enabled' => true,
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
				return $response;
			},
			10,
			3
		);

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/sync_settings' );
		$response = rest_get_server()->dispatch( $request );

		[
			'success'         => $success,
			'synced_settings' => $synced_settings,
		] = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $success );
		$this->assertTrue( $synced_settings['automatic_enhanced_match_support'] );
		$this->assertTrue( Pinterest_For_WooCommerce::get_setting( 'automatic_enhanced_match_support' ) );
		$this->assertEquals(
			$response->get_data()['synced_settings']['last_synced_settings'],
			Pinterest_For_WooCommerce::get_setting( 'last_synced_settings' )
		);
	}

	/**
	 * Tests endpoint returns settings synced with Pinterest side.
	 *
	 * @return void
	 */
	public function test_sync_settings_when_tracking_disabled() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Pinterest_For_WooCommerce::save_setting( 'track_conversions', false );
		Pinterest_For_Woocommerce::save_setting( 'automatic_enhanced_match_support', true );
		Pinterest_For_WooCommerce::save_setting( 'tracking_advertiser', 'ai-123456789' );
		Pinterest_For_WooCommerce::save_setting( 'tracking_tag', 'ti-123456789' );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/sync_settings' );
		$response = rest_get_server()->dispatch( $request );

		[
			'success'         => $success,
			'synced_settings' => $synced_settings,
		] = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $success );
		$this->assertFalse( $synced_settings['automatic_enhanced_match_support'] );
	}

}
