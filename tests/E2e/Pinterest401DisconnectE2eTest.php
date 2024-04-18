<?php

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\Notes\TokenInvalidFailure;

class Pinterest401DisconnectE2eTest extends WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		remove_all_actions( 'pinterest_for_woocommerce_token_saved' );

		$token_data = array(
			'access_token' => 'def50200fd71a25bca8b0732a0449818bb58774bc022a75b6a954d233aa8fc06f31fbed01e816cc6d2518a24ecd1018878d1568341b5412a52b33ef4b3627b296d76a6fdd53a9541dfe57bc8a304a837779b78c42b44516c473948941c928d68c9277db225931e3bfd87bfd36212d49018650d167c4fffc1600b27d76bd5debb6733aef64ff4c1de40740f10417f9c98145a12d994bad15b750cf0dbb48ac2ce78e8cec610707e3a087df58c90445ed4fb5acafbb6c60a07f4',
			'refresh_token' => 'def50200c637373b990d843d5be0b37415610a077359085993d42b7ef032ae368ab2a28e02e90438b5a34fcd92f62d938f65a17de11574743126cddefb30a662440881fe9197911940fddea93b3e0d32e34e4bd348bb74586d4980d304dc6a4b9cc577bc4533f32df239fb91044f4c17dd72842d2900a6aea8518939ddebc4073d11f2c61b92430272451c71a878e70b651dce2214657bd0b333c9517b2694168eb8431fa6c7d82d15720ffea853ea052382cbbcf2f4d2826851dcae7e1c3295433bd9cd586a21a6642ebbb6d5158f8c2a5f8c4f62c8b524ffbb5f13a90df9078feaf1ddda4627b1398eab80a3b57efac3de38cbafd162496f7908dd0cdc48752c4ef26848788fec7e7e48adacadf3144894ea6a1166dacda3649885709aacf049de326bef0f366dd776908b95024a1ef6c0b320ef091a104a127e1b2a5c7600e5b07af918ded1705989431e1ad84fac85c4da4ffc5f29cb3c6753b51eebf3b577bf73422c6d5750fcbb25b83e593c9e5ae03ddbbc5a59214b9e4ec89a93cd7e621a44889dd5e68e03e755b40bb9b1402ad3d327b16ebcc3f92e487929fdb22d9ed5a3a28400f04d17f492592488651c71edb5b93a7c87ee0043ad21a96989dd66cde55dd6ebbd0ba6e23040e809674cdb71bdb68d95183925e2702ab3188815bdddd49a4d76403f3190a8b2011a1e6440e2032589ff6f7d060ec20f6b0c651d77e9c9927cee4a20e4423f3a622b7134fe770f7cd9231569eb252e4f4a252f2e973d5a1e7feb8171fea9d00f62257937885ff193dd28e2fc1b3fbd9da3764a5c06e4ef51654d59fd8e4bb3155d443a4995c83c997c8965587322207957149636b0c1eeccd3d1f5e427ed8896cca3c5',
			'token_type' => 'bearer',
			'expires_in' => 2592000,
			'refresh_token_expires_in' => 31536000,
			'scope' => 'ads:read ads:write catalogs:read catalogs:write pins:read pins:write user_accounts:read user_accounts:write',
			'refresh_date' => 1685694065,
		);
		Pinterest_For_Woocommerce()::save_token_data( $token_data );
		$info_data = array(
			'advertiser_id' => '549765662491',
			'tag_id' => '2613286171854',
			'merchant_id' => '1479839719476',
			'clientHash' => 'MTQ4NjE3MzpkNWJjNTM4ZmVhMTZhYzIwMmZiNDZhMTFjMGNkZGVmNzFhOWU1YWY0',
		);
		Pinterest_For_Woocommerce()::save_connection_info_data( $info_data );

		// API Request filters to stub Pinterest API requests.
		$this->create_commerce_integration_request_stub();
		$this->get_account_info_request_stub();
		$this->get_user_websites_request_stub();
		$this->get_linked_businesses_request_stub();

		add_action( 'pinterest_for_woocommerce_token_saved', array( Pinterest_For_Woocommerce::class, 'set_default_settings' ) );
		add_action( 'pinterest_for_woocommerce_token_saved', array( Pinterest_For_Woocommerce::class, 'create_commerce_integration' ) );
		add_action( 'pinterest_for_woocommerce_token_saved', array( Pinterest_For_Woocommerce::class, 'update_account_data' ) );
		add_action( 'pinterest_for_woocommerce_token_saved', array( Pinterest_For_Woocommerce::class, 'update_linked_businesses' ) );
		add_action( 'pinterest_for_woocommerce_token_saved', array( Pinterest_For_Woocommerce::class, 'post_update_cleanup' ) );
		add_action( 'pinterest_for_woocommerce_token_saved', array( TokenInvalidFailure::class, 'possibly_delete_note' ) );

		do_action( 'pinterest_for_woocommerce_token_saved' );
	}

	/**
	 * Tests .
	 *
	 * @return void
	 */
	public function test_401_disconnect_resets_integration_data_and_shows_notice() {
		add_action( 'pinterest_for_woocommerce_disconnect', array( Pinterest_For_Woocommerce::class, 'reset_connection' ) );

		do_action( 'pinterest_for_woocommerce_disconnect' );

		$this->assertEmpty( Pinterest_For_Woocommerce::get_data( 'integration_data' ) );
		$this->assertTrue( TokenInvalidFailure::note_exists() );
	}

	/**
	 * Tests .
	 *
	 * @return void
	 */
	public function test_401_disconnect_resets_integration_data_and_shows_notice_for_actions_scheduler() {
		$this->expectException( Exception::class );
		$this->expectExceptionCode( 401 );
		$this->expectExceptionMessage( 'Authentication failed.' );

		add_action( 'action_scheduler_failed_execution', array( Pinterest_For_Woocommerce::class, 'action_scheduler_reset_connection' ), 10, 2 );

		do_action( 'action_scheduler_failed_execution', '987654', new Exception( 'Authentication failed.', 401 ) );

		$this->assertEmpty( Pinterest_For_Woocommerce::get_data( 'integration_data' ) );
		$this->assertTrue( TokenInvalidFailure::note_exists() );
	}

	private function create_commerce_integration_request_stub() {
		add_filter(
			'pre_http_request',
			function ($response, $parsed_args, $url) {
				if ('https://api.pinterest.com/v5/integrations/commerce' === $url) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'id' => '6491114367052267731',
								'external_business_id' => 'wordpresspinterest-6479a6713160b',
								'connected_merchant_id' => '1479839719476',
								'connected_user_id' => '1144266355231574943',
								'connected_advertiser_id' => '549765662491',
								'connected_tag_id' => '2613286171854',
								'connected_lba_id' => '',
								'partner_access_token_expiry' => 0,
								'partner_refresh_token_expiry' => 0,
								'scopes' => '',
								'created_timestamp' => 1685694065000,
								'updated_timestamp' => 1685694065000,
								'additional_id_1' => '',
								'partner_metadata' => '',
							)
						),
						'response' => array(
							'code' => 200,
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
	}

	private function get_account_info_request_stub() {
		add_filter(
			'pre_http_request',
			function ($response, $parsed_args, $url) {
				if ('https://api.pinterest.com/v5/user_account' === $url) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'username' => 'dmytromaksiuta1',
								'profile_image' => 'https://i.pinimg.com/600x600_R/42/f5/36/42f5364f737aff4749a8e9046510828f.jpg',
								'account_type' => 'BUSINESS',
							)
						),
						'response' => array(
							'code' => 200,
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
	}

	private function get_user_websites_request_stub() {
		add_filter(
			'pre_http_request',
			function ($response, $parsed_args, $url) {
				if ('https://api.pinterest.com/v5/user_account/websites' === $url) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								'items' => array(
									array(
										'status' => 'verified',
										'website' => 'wordpress.dima.works',
									),
									array(
										'status' => 'verified',
										'website' => 'pinterest.dima.works',
									)
								),
							)
						),
						'response' => array(
							'code' => 200,
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
	}

	private function get_linked_businesses_request_stub() {
		add_filter(
			'pre_http_request',
			function ($response, $parsed_args, $url) {
				if ('https://api.pinterest.com/v5/user_account/businesses' === $url) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array(
								array(
									'username' => 'dmytromaksiuta1',
									'image_small_url' => 'https://www.example.com/dj23454f53dfk2324.jpg',
									'image_medium_url' => 'https://www.example.com/dj23454f53dfk2324.jpg',
									'image_large_url' => 'https://www.example.com/dj23454f53dfk2324.jpg',
									'image_xlarge_url' => 'https://www.example.com/dj23454f53dfk2324.jpg'
								),
							)
						),
						'response' => array(
							'code' => 200,
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
	}
}
