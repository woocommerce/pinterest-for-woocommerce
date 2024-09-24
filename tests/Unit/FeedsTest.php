<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Exception\FeedNotFoundException;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

class FeedsTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
	}

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'site_url' );
	}

	/**
	 * Tests feed deletion produces an admin notice in case feed deletion has failed.
	 *
	 * @return void
	 */
	public function test_feed_delete_produces_an_admin_notification() {
		add_filter( 'pre_http_request', array( self::class, 'feed_delete_failure' ), 10, 3 );

		$result = Feeds::delete_feed( '1574695656968' );

		$this->assertFalse( $result );
		$this->assertTrue( FeedDeletionFailure::note_exists() );
	}

	public function test_maybe_remote_feed_returns_feed_id() {
		add_filter( 'site_url', fn() => 'https://example-1.com' );
		add_filter( 'pre_http_request', array( self::class, 'get_feeds' ), 10, 3 );

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( 'fIOasjj', $feed );
	}

	public function test_maybe_remote_feed_returns_empty_feed_id() {
		add_filter( 'site_url', fn() => 'https://example-11.com' );
		add_filter( 'pre_http_request', array( self::class, 'get_feeds_with_empty_tail_for_the_feed_location' ), 10, 3 );

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( '', $feed );
	}

	public function test_maybe_remote_feed_returns_empty_string() {
		add_filter( 'pre_http_request', array( self::class, 'get_empty_feeds' ), 10, 3 );
		$this->expectException( FeedNotFoundException::class );
		Feeds::maybe_remote_feed();
	}

	public static function feed_delete_failure( $response, $parsed_args, $url ): array {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds/1574695656968?ad_account_id=114141241212' === $url ) {
			return array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body' => json_encode(
					array(
						'code' => 4162,
						'message' => 'We can\'t disable a Product Group with active promotions.',
					)
				),
				'response' => array(
					'code' => 409,
					'message' => 'Conflict. Can\'t delete a feed with active promotions.',
				),
				'cookies' => array(),
				'filename' => '',
			);
		}
		return $response;
	}

	public static function get_feeds( $response, $parsed_args, $url ): array {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' === $url ) {
			return array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body' => json_encode(
					array(
						'items' => array(
							array(
								"created_at" => "2022-03-14T15:15:22Z",
								"id" => "278913891236895123895",
								"updated_at" => "2022-03-14T15:16:34Z",
								"name" => "Created by Pinterest for WooCommerce at pinterest.dima.works US|en-US|USD",
								"format" => "TSV",
								"catalog_type" => "RETAIL",
								"location" => "https://example-1.com/pinterest-for-woocommerce-fIOasjj.xml",
								"status" => "ACTIVE",
								"default_currency" => "USD",
								"default_locale" => "en-US",
								"default_country" => "US",
								"default_availability" => "IN_STOCK",
							),
						),
					),
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
	}

	public static function get_empty_feeds( $response, $parsed_args, $url ): array {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' === $url ) {
			return array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body' => json_encode(
					array(
						'items' => array(),
					),
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

	public static function get_feeds_with_empty_tail_for_the_feed_location( $response, $parsed_args, $url ): array {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' === $url ) {
			return array(
				'headers' => array(
					'content-type' => 'application/json',
				),
				'body' => json_encode(
					array(
						'items' => array(
							array(
								"created_at"           => "2022-03-14T15:15:22Z",
								"id"                   => "278913891236895123895",
								"updated_at"           => "2022-03-14T15:16:34Z",
								"name"                 => "WooCommerce",
								"format"               => "TSV",
								"catalog_type"         => "RETAIL",
								"location"             => "https://example-11.com/pinterest-for-woocommerce-.xml",
								"status"               => "ACTIVE",
								"default_currency"     => "USD",
								"default_locale"       => "en-US",
								"default_country"      => "US",
								"default_availability" => "IN_STOCK",
							),
						),
					),
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
	}
}
