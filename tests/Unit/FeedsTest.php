<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;

class FeedsTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		// Cleanup API call stub before each test.
		remove_filter( 'pre_http_request', array( self::class, 'get_feeds_request_stub' ) );

		Pinterest_For_Woocommerce::save_data('local_feed_ids', array());
		Pinterest_For_Woocommerce::save_setting('tracking_advertiser', '8901267167247612734708');
		add_filter('woocommerce_get_base_location', 'US:CA');
		add_filter('locale', 'en_US');
		add_filter(
			'site_url',
			function ($url) {
				return 'https://pinterest.dima.works';
			},
			10,
			1
		);
	}

	/**
	 * Tests feed deletion produces an admin notice in case feed deletion has failed.
	 *
	 * @return void
	 */
	public function test_feed_delete_produces_an_admin_notification() {
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '549765662491' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://api.pinterest.com/v5/catalogs/feeds/1574695656968?ad_account_id=549765662491' === $url ) {
					$response = array(
						'headers'  => array(
							'content-type' => 'application/json',
						),
						'body'     => json_encode(
							array(
								'code'    => 4162,
								'message' => 'We can\'t disable a Product Group with active promotions.',
							)
						),
						'response' => array(
							'code'    => 409,
							'message' => 'Conflict. Can\'t delete a feed with active promotions.',
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

		$result = Feeds::delete_feed( '1574695656968' );

		$this->assertFalse( $result );
		$this->assertTrue( FeedDeletionFailure::note_exists() );
	}

	public function test_maybe_remote_feed_returns_feed_id() {
		self::get_feeds_request_stub(
			array(
				'items' => array(
					array(
						"created_at"                    => "2022-03-14T15:15:22Z",
						"id"                            => "278913891236895123895",
						"updated_at"                    => "2022-03-14T15:16:34Z",
						"name"                          => "Created by Pinterest for WooCommerce at pinterest.dima.works US|en-US|USD",
						"format"                        => "TSV",
						"catalog_type"                  => "RETAIL",
						"credentials"                   => array(
							"password" => "string",
							"username" => "string",
						),
						"location"                      => "",
						"preferred_processing_schedule" => array(
							"time"     => "02:59",
							"timezone" => "Africa/Abidjan",
						),
						"status"                        => "ACTIVE",
						"default_currency"              => "USD",
						"default_locale"                => "en-US",
						"default_country"               => "US",
						"default_availability"          => "IN_STOCK",
					),
				),
			)
		);

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( '278913891236895123895', $feed );
	}

	public function test_maybe_remote_feed_returns_empty_string() {
		self::get_feeds_request_stub(
			array(
				'items' => array(),
			)
		);

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( '', $feed );
	}

	private static function get_feeds_request_stub( array $response ) {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) use ( $response ) {
				if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=8901267167247612734708' === $url ) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode( $response ),
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
	}
}
