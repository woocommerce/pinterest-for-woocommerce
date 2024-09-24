<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\Heartbeat;
use Automattic\WooCommerce\Pinterest\RefreshToken;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class RefreshTokenTest extends WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test daily refresh token action is added.
	 *
	 * @return void
	 */
	public function test_schedule_event_added_with_plugin() {
		$pinterest = Pinterest_For_Woocommerce();
		$this->assertEquals( 10, has_action( 'plugins_loaded', array( $pinterest, 'init_plugin' ) ) );
		$pinterest->init_plugin();
		$this->assertEquals( 10, has_action( 'init', [ RefreshToken::class, 'schedule_event' ] ) );
	}

	public function test_schedule_event_adds_daily_action() {
		// Jobs will schedule only if Pinterest is connected (means integration data is set and has the ID).
		Pinterest_For_Woocommerce::save_data( 'integration_data', array( 'id' => '567891567892' ) );

		RefreshToken::schedule_event();
		$this->assertEquals( 20, has_action( Heartbeat::DAILY, array( RefreshToken::class, 'handle_refresh' ) ) );
	}

	/**
	 * Tests call to refresh a token is made if token is about to expire in two days.
	 *
	 * @return void
	 */
	public function test_handle_refresh_does_a_call_to_refresh_a_token_if_token_about_to_expire_in_two_days() {
		$expires_in   = 30 * DAY_IN_SECONDS;
		$refresh_date = time() - 28 * DAY_IN_SECONDS - 10;

		update_option(
			PINTEREST_FOR_WOOCOMMERCE_DATA_NAME,
			array(
				'token_data' => array(
					'refresh_token' => Crypto::encrypt( 'pinr.refresh_token' ),
					'expires_in'    => $expires_in, // Access Token expires in 30 days.
					'refresh_date'  => $refresh_date, // Token was refreshed more than 28 days ago. Refresh.
				),
			)
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				$this->assertEquals(
					Pinterest_For_Woocommerce::get_connection_proxy_url() . 'integrations/renew/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE,
					$url
				);
				$this->assertEquals( 'pinr.refresh_token', $parsed_args['body']['refresh_token'] ?? '' );
				return array(
					'body' =>
						json_encode(
							array(
								'token_type'    => 'bearer',
								'access_token'  => 'pina.access_token',
								'expires_in'    => 30 * DAY_IN_SECONDS,
								'refresh_token' => 'pinr.new_refresh_token',
								'scope'         => 'ads:read ads:write catalogs:read catalogs:write pins:read pins:write user_accounts:read user_accounts:write',
							),
					),
				);
			},
			10,
			3
		);

		RefreshToken::handle_refresh();
	}

	/**
	 * Tests data returned from refresh token call is saved.
	 *
	 * @return void
	 */
	public function test_handle_refresh_updates_option_with_new_tokens() {
		$expires_in   = 30 * DAY_IN_SECONDS;
		$refresh_date = time() - 28 * DAY_IN_SECONDS - 10;

		update_option(
			PINTEREST_FOR_WOOCOMMERCE_DATA_NAME,
			array(
				'token_data' => array(
					'refresh_token' => Crypto::encrypt( 'pinr.refresh_token' ),
					'expires_in'    => $expires_in, // Access Token expires in 30 days.
					'refresh_date'  => $refresh_date, // Token was refreshed more than 28 days ago. Refresh.
				),
			)
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'body' => json_encode(
						array(
							'token_type'    => 'bearer',
							'access_token'  => 'pina.access_token_new',
							'expires_in'    => 30 * DAY_IN_SECONDS,
							'refresh_token' => 'pinr.refresh_token_new',
							'scope'         => 'ads:read ads:write catalogs:read catalogs:write pins:read pins:write user_accounts:read user_accounts:write',
						),
					),
				);
			},
			10,
			3
		);

		RefreshToken::handle_refresh();

		$token_data = Pinterest_For_Woocommerce::get_data( 'token_data', true );

		$this->assertEquals( 'bearer', $token_data['token_type'] );
		$this->assertEquals( 'pina.access_token_new', Crypto::decrypt( $token_data['access_token'] ) );
		$this->assertEquals( 'pinr.refresh_token_new', Crypto::decrypt( $token_data['refresh_token'] ) );
		$this->assertEquals( 'ads:read ads:write catalogs:read catalogs:write pins:read pins:write user_accounts:read user_accounts:write', $token_data['scope'] );
	}
}
