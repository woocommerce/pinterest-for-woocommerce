<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\CommerceIntegrationSync;
use Automattic\WooCommerce\Pinterest\Heartbeat;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class CommerceIntegrationSyncTest extends WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		Pinterest_For_Woocommerce::remove_data( 'integration_data' );
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Test daily refresh token action is added.
	 *
	 * @return void
	 */
	public function test_scheduled_event_added_with_plugin() {
		Pinterest_For_Woocommerce()->init_plugin();
		$this->assertEquals( 10, has_action( 'init', [ CommerceIntegrationSync::class, 'schedule_event' ] ) );
	}

	public function test_schedule_event_adds_weekly_action_if_integration_id_present() {
		// Jobs will schedule only if Pinterest is connected (means integration data is set and has the ID).
		Pinterest_For_Woocommerce::save_data( 'integration_data', array( 'id' => '678903145607896071' ) );

		CommerceIntegrationSync::schedule_event();
		$this->assertEquals( 20, has_action( Heartbeat::WEEKLY, array( CommerceIntegrationSync::class, 'handle_sync' ) ) );
	}

	public function test_schedule_event_doesnt_add_weekly_action_if_integration_id_missing() {
		// Jobs will schedule only if Pinterest is connected (means integration data is set and has the ID).
		Pinterest_For_Woocommerce::save_data( 'integration_data', [] );

		CommerceIntegrationSync::schedule_event();
		$this->assertFalse( has_action( Heartbeat::WEEKLY, array( CommerceIntegrationSync::class, 'handle_sync' ) ) );
	}

	public function test_schedule_event_doesnt_add_weekly_action_if_integration_data_missing() {
		CommerceIntegrationSync::schedule_event();
		$this->assertFalse( has_action( Heartbeat::WEEKLY, array( CommerceIntegrationSync::class, 'handle_sync' ) ) );
	}

	public function test_handle_sync_creates_commerce_integration_if_external_business_id_missing() {
		global $wp_version;

		Pinterest_For_Woocommerce::save_data(
			'connection_info_data',
			array(
				'advertiser_id' => 'advertiser-id-jksd76788',
				'merchant_id'   => 'merchant-id-hjkasdf',
				'tag_id'        => 'tag-id-3245671356787',
			)
		);
		add_filter(
			'wc_pinterest_external_business_id',
			function () {
				return 'woo-example-2.com-2k3Gdf91D';
			}
		);
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				global $wp_version;

				$this->assertEquals( 'https://api.pinterest.com/v5/integrations/commerce', $url );
				return array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode(
						array(
							'external_business_id'    => 'woo-example-2.com-2k3Gdf91D',
							'connected_merchant_id'   => 'merchant-id-hjkasdf',
							'connected_advertiser_id' => 'advertiser-id-jksd76788',
							'connected_tag_id'        => 'tag-id-3245671356787',
							'partner_metadata'        => json_encode(
								array(
									'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
									'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
									'wp_version'     => $wp_version,
									'locale'         => get_locale(),
									'currency'       => get_woocommerce_currency(),
								)
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

		$result = CommerceIntegrationSync::handle_sync();
		$this->assertTrue( $result );
		$this->assertEquals(
			array(
				'external_business_id'    => 'woo-example-2.com-2k3Gdf91D',
				'connected_merchant_id'   => 'merchant-id-hjkasdf',
				'connected_advertiser_id' => 'advertiser-id-jksd76788',
				'connected_tag_id'        => 'tag-id-3245671356787',
				'partner_metadata'        => json_encode(
					array(
						'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
						'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
						'wp_version'     => $wp_version,
						'locale'         => get_locale(),
						'currency'       => get_woocommerce_currency(),
					)
				),
			),
			Pinterest_For_Woocommerce::get_data( 'integration_data' )
		);
		$this->assertEquals(
			'advertiser-id-jksd76788',
			Pinterest_For_Woocommerce::get_setting( 'tracking_advertiser' )
		);
		$this->assertEquals(
			'tag-id-3245671356787',
			Pinterest_For_Woocommerce::get_setting( 'tracking_tag' )
		);
	}

	public function test_handle_sync_does_nothing_if_partner_metadata_matches() {
		Pinterest_For_Woocommerce::save_data(
			'connection_info_data',
			array(
				'advertiser_id' => 'advertiser-id-jksd76788',
				'merchant_id'   => 'merchant-id-hjkasdf',
				'tag_id'        => 'tag-id-3245671356787',
			)
		);
		Pinterest_For_Woocommerce::save_data(
			'integration_data',
			array( 'external_business_id' => 'woo-example.com-2k3Gdf91D' )
		);
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				global $wp_version;

				$this->assertEquals( 'https://api.pinterest.com/v5/integrations/commerce/woo-example.com-2k3Gdf91D', $url );
				return array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode(
						array(
							'external_business_id'    => 'woo-example.com-2k3Gdf91D',
							'connected_merchant_id'   => 'merchant-id-hjkasdf',
							'connected_advertiser_id' => 'advertiser-id-jksd76788',
							'connected_tag_id'        => 'tag-id-3245671356787',
							'partner_metadata'        => json_encode(
								array(
									'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
									'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
									'wp_version'     => $wp_version,
									'locale'         => get_locale(),
									'currency'       => get_woocommerce_currency(),
								)
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

		$result = CommerceIntegrationSync::handle_sync();
		$this->assertTrue( $result );
	}

	public function test_handle_sync_updates_if_partner_metadata_mismatch() {
		Pinterest_For_Woocommerce::save_data(
			'connection_info_data',
			array(
				'advertiser_id' => 'advertiser-id-jksd76788',
				'merchant_id'   => 'merchant-id-hjkasdf',
				'tag_id'        => 'tag-id-3245671356787',
			)
		);
		Pinterest_For_Woocommerce::save_data(
			'integration_data',
			array( 'external_business_id' => 'woo-example.com-2k3Gdf91D' )
		);
		add_filter(
			'pinterest_for_woocommerce_commerce_integration_data',
			function ( $data ) {
				$data['partner_metadata'] = json_decode( $data['partner_metadata'], true );
				$data['partner_metadata'] = json_encode(
					array_merge(
						$data['partner_metadata'],
						array(
							'plugin_version' => '99999.01',
							'wc_version'     => '99999.02',
							'wp_version'     => '99999.03',
						)
					)
				);
				return $data;
			},
			10,
			1
		);
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				global $wp_version;

				$this->assertEquals( 'https://api.pinterest.com/v5/integrations/commerce/woo-example.com-2k3Gdf91D', $url );

				$data = array(
					'external_business_id'    => 'woo-example.com-2k3Gdf91D',
					'connected_merchant_id'   => 'merchant-id-hjkasdf',
					'connected_advertiser_id' => 'advertiser-id-jksd76788',
					'connected_tag_id'        => 'tag-id-3245671356787',
					'partner_metadata'        => json_encode(
						array(
							'plugin_version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
							'wc_version'     => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
							'wp_version'     => $wp_version,
							'locale'         => get_locale(),
							'currency'       => get_woocommerce_currency(),
						)
					),
				);

				if ( 'PATCH' === $parsed_args['method'] ) {
					$data['partner_metadata'] = json_encode(
						array(
							'plugin_version' => '99999.01',
							'wc_version'     => '99999.02',
							'wp_version'     => '99999.03',
							'locale'         => get_locale(),
							'currency'       => get_woocommerce_currency(),
						)
					);
					$this->assertEquals( $data, json_decode( $parsed_args['body'], true ) );
				}

				return array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode( $data ),
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

		$result = CommerceIntegrationSync::handle_sync();
		$this->assertTrue( $result );
		$this->assertEquals(
			array(
				'external_business_id'    => 'woo-example.com-2k3Gdf91D',
				'connected_merchant_id'   => 'merchant-id-hjkasdf',
				'connected_advertiser_id' => 'advertiser-id-jksd76788',
				'connected_tag_id'        => 'tag-id-3245671356787',
				'partner_metadata'        => json_encode(
					array(
						'plugin_version' => '99999.01',
						'wc_version'     => '99999.02',
						'wp_version'     => '99999.03',
						'locale'         => get_locale(),
						'currency'       => get_woocommerce_currency(),
					)
				),
			),
			Pinterest_For_Woocommerce::get_data( 'integration_data' )
		);
	}
}
