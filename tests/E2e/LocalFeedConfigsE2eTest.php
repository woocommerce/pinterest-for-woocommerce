<?php declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\E2e;

use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Pinterest_For_Woocommerce;

class LocalFeedConfigsE2eTest extends \WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '475245723456346' );
		Pinterest_For_Woocommerce::save_setting( 'product_sync_enabled', true );

		// LocalFeedConfigs is a singleton object, resetting its state before each test.
		LocalFeedConfigs::deregister();

		add_filter( 'woocommerce_get_base_location', fn() => 'US:CA' );
		add_filter( 'locale', fn() => 'en_US' );
		add_filter( 'site_url', fn( $url ) => 'https://example-2.com' );
		add_filter( 'upload_dir', fn( $data ) => array_merge( $data, array( 'baseurl' => 'https://example-2.com/wp-content/uploads' ) ) );
		add_filter( 'pre_http_request', array( self::class, 'get_feeds' ), 10, 3 );
	}

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'site_url' );
		remove_all_filters( 'woocommerce_get_base_location' );
		remove_all_filters( 'upload_dir' );
		remove_all_filters( 'locale' );
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
				'US' => array(
					'feed_id'   => 'taLlmN',
					'feed_file' => wp_upload_dir()['basedir'] . '/pinterest-for-woocommerce-taLlmN.xml',
					'tmp_file'  => wp_upload_dir()['basedir'] . '/pinterest-for-woocommerce-taLlmN-tmp.xml',
					'feed_url'  => wp_upload_dir()['baseurl'] . '/pinterest-for-woocommerce-taLlmN.xml',
				),
			),
			$configurations
		);
	}

	public static function get_feeds( $response, $parsed_args, $url ) {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=475245723456346' === $url ) {
			return array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body' => json_encode(
					array (
						'items' => array(
							array(
								"created_at"           => "2022-03-14T15:15:22Z",
								"id"                   => "547381235776346598",
								"updated_at"           => "2022-03-14T15:16:34Z",
								"name"                 => "string",
								"format"               => "TSV",
								"catalog_type"         => "RETAIL",
								"location"             => "https://example-2.com/wp-content/uploads/pinterest-for-woocommerce-taLlmN.xml",
								"status"               => "ACTIVE",
								"default_currency"     => "USD",
								"default_locale"       => "en-US",
								"default_country"      => "US",
								"default_availability" => "IN_STOCK",
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
		}
		return $response;
	}
}
