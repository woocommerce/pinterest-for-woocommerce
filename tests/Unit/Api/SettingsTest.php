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

class SettingsTest extends WP_Test_REST_TestCase {

	/**
	 * Tests if the settings route is registered.
	 *
	 * @return void
	 */
	public function test_settings_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/settings', $routes );
	}

	/**
	 * Tests if the get/set settings endpoints reject access.
	 *
	 * @return void
	 */
	public function test_settings_endpoint_rejects_access() {
		// 1. No authentication.
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 401, $response->get_status() );

		// 2. No authorisation.
		$user = $this->factory->user->create( array( 'role' => 'guest' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Tests get settings endpoint returns settings stored inside wp_options.
	 *
	 * @return void
	 */
	public function test_get_settings_endpoint_returns_settings_stored_inside_options() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Pinterest_For_Woocommerce::save_settings(
			array (
				'automatic_enhanced_match_support' => true,
				'last_synced_settings'             => '31 May 2023, 03:52:53 pm',
				'track_conversions'                => true,
				'enhanced_match_support'           => true,
				'save_to_pinterest'                => true,
				'rich_pins_on_posts'               => true,
				'rich_pins_on_products'            => true,
				'product_sync_enabled'             => true,
				'enable_debug_logging'             => true,
				'erase_plugin_data'                => false,
				'ads_campaign_is_active'           => true,
				'did_redirect_to_onboarding'       => false,
				'account_data'                     => array (
					'username'                => 'dmytromaksiuta1',
					'full_name'               => '',
					'id'                      => '8842280425079444297',
					'image_medium_url'        => 'https://i.pinimg.com/600x600_R/42/f5/36/42f5364f737aff4749a8e9046510828f.jpg',
					'is_partner'              => true,
					'verified_user_websites'  => array ( 'pinterest.dima.works', 'wordpress.dima.works' ),
					'is_any_website_verified' => true,
					'is_billing_setup'        => false,
					'coupon_redeem_info'      => array (
						'redeem_status' => false,
					),
					'available_discounts' => false,
				),
				'tracking_advertiser' => '549765662491',
				'tracking_tag'        => '2613286171854',
			)
		);

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				'pinterest_for_woocommerce' => Pinterest_For_Woocommerce::get_settings( true ),
			),
			$response->get_data()
		);
	}

	/**
	 * Tests set settings endpoint successfully sets settings.
	 *
	 * @return void
	 */
	public function test_set_settings_endpoint_successfully_sets_settings() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Pinterest_For_Woocommerce::save_settings( array() );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/settings' );
		$request->set_body_params(
			array(
				'pinterest_for_woocommerce' => array(
					'automatic_enhanced_match_support' => true,
					'last_synced_settings'             => '31 May 2023, 03:52:53 pm',
					'track_conversions'                => true,
					'enhanced_match_support'           => true,
					'save_to_pinterest'                => true,
					'rich_pins_on_posts'               => true,
					'rich_pins_on_products'            => true,
					'product_sync_enabled'             => true,
					'enable_debug_logging'             => true,
					'erase_plugin_data'                => false,
					'ads_campaign_is_active'           => true,
					'did_redirect_to_onboarding'       => false,
					'account_data'                     => array(
						'username'                => 'dmytromaksiuta1',
						'full_name'               => '',
						'id'                      => '8842280425079444297',
						'image_medium_url'        => 'https://i.pinimg.com/600x600_R/42/f5/36/42f5364f737aff4749a8e9046510828f.jpg',
						'is_partner'              => true,
						'verified_user_websites'  => array( 'pinterest.dima.works', 'wordpress.dima.works' ),
						'is_any_website_verified' => true,
						'is_billing_setup'        => false,
						'coupon_redeem_info'      => array(
							'redeem_status' => false,
						),
						'available_discounts' => false,
					),
					'tracking_advertiser' => '549765662491',
					'tracking_tag'        => '2613286171854',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals(
			array(
				'pinterest_for_woocommerce' => true,
			),
			$response->get_data()
		);
	}

	/**
	 * Tests set settings endpoint returns error if wrong data key is passed.
	 *
	 * @return void
	 */
	public function test_set_settings_endpoint_returns_error_if_data_is_missing() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		Pinterest_For_Woocommerce::save_settings( array() );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/settings' );
		$request->set_body_params(
			array(
				'some_other_parameter_name' => array(),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals(
			'Missing option parameters.',
			$response->get_data()['message'] ?? ''
		);
	}

	/**
	 * Tests set settings endpoint returns error if save fails.
	 *
	 * @return void
	 */
	public function test_set_settings_endpoint_returns_error_if_save_fails() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		// Make update_option fail.
		add_filter(
			'pre_update_option',
			function () {
				return false;
			}
		);

		Pinterest_For_Woocommerce::save_settings( array() );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/settings' );
		$request->set_body_params(
			array(
				'pinterest_for_woocommerce' => array(
					'automatic_enhanced_match_support' => true,
					'last_synced_settings'             => '31 May 2023, 03:52:53 pm',
					'track_conversions'                => true,
					'enhanced_match_support'           => true,
					'save_to_pinterest'                => true,
					'rich_pins_on_posts'               => true,
					'rich_pins_on_products'            => true,
					'product_sync_enabled'             => true,
					'enable_debug_logging'             => true,
					'erase_plugin_data'                => false,
					'ads_campaign_is_active'           => true,
					'did_redirect_to_onboarding'       => false,
					'account_data'                     => array(
						'username'                => 'dmytromaksiuta1',
						'full_name'               => '',
						'id'                      => '8842280425079444297',
						'image_medium_url'        => 'https://i.pinimg.com/600x600_R/42/f5/36/42f5364f737aff4749a8e9046510828f.jpg',
						'is_partner'              => true,
						'verified_user_websites'  => array( 'pinterest.dima.works', 'wordpress.dima.works' ),
						'is_any_website_verified' => true,
						'is_billing_setup'        => false,
						'coupon_redeem_info'      => array(
							'redeem_status' => false,
						),
						'available_discounts' => false,
					),
					'tracking_advertiser' => '549765662491',
					'tracking_tag'        => '2613286171854',
				),
			)
		);
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 500, $response->get_status() );
		$this->assertEquals(
			'There was an error saving the settings.',
			$response->get_data()['message'] ?? ''
		);
	}
}
