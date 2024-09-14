<?php declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\E2e;

use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\Notes\TokenInvalidFailure;
use Pinterest_For_Woocommerce;

class LocalFeedConfigsE2eTest extends \WP_UnitTestCase {

	protected function setUp(): void {
		parent::setUp();

		self::get_feeds_request_stub();

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
	 * Tests if local feed config is empty we attempt to fetch a feed from Pinterest in case it exists.
	 * This test aims to check if we restore empty local feed config from the server in case of auto-disconnect.
	 *
	 * @return void
	 */
	public function test_when_local_feed_config_is_empty_we_attempt_to_get_active_feed_from_pinterest() {
		$local_feed_configs = LocalFeedConfigs::get_instance();

		$configurations = $local_feed_configs->get_configurations();

		$this->assertEquals(
			array(
				array(
					'feed_id'   => 'taLlmN',
					'feed_file' => '/taLlmN.xml',
					'tmp_file'  => '/taLlmN-tmp.xml',
					'feed_url'  => 'https://pinterest.dima.works/wp-content/uploads/pinterest-for-woocommerce-taLlmN.xml',
				),
			),
			$configurations
		);
	}

	private static function get_feeds_request_stub() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=8901267167247612734708' === $url ) {
					$response = array(
						'headers' => array(
							'content-type' => 'application/json',
						),
						'body' => json_encode(
							array (
								'items' => array(
									"created_at"                    => "2022-03-14T15:15:22Z",
									"id"                            => "547381235776346598",
									"updated_at"                    => "2022-03-14T15:16:34Z",
									"name"                          => "string",
									"format"                        => "TSV",
									"catalog_type"                  => "RETAIL",
									"credentials"                   => array(
										"password" => "string",
										"username" => "string",
									),
									"location"                      => "https://pinterest.dima.works/wp-content/uploads/pinterest-for-woocommerce-taLlmN.xml",
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
	}
}
