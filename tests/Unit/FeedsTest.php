<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Exception\FeedNotFoundException;
use Automattic\WooCommerce\Pinterest\FeedOwnership;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\LocaleMapper;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests the Feeds class.
 *
 * @version x.x.x
 */
class FeedsTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		LocalFeedConfigs::deregister();
		delete_option( FeedOwnership::OPTION_NAME );
	}

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'site_url' );
		remove_all_filters( 'upload_dir' );
		LocalFeedConfigs::deregister();
		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * Tests feed deletion produces an admin notice in case feed deletion has failed,
	 * and keeps the feed on the ownership record because it still exists on Pinterest.
	 *
	 * @return void
	 */
	public function test_feed_delete_produces_an_admin_notification() {
		FeedOwnership::record( '1574695656968' );
		add_filter( 'pre_http_request', array( self::class, 'feed_delete_failure' ), 10, 3 );

		$result = Feeds::delete_feed( '1574695656968' );

		$this->assertFalse( $result );
		$this->assertTrue( FeedDeletionFailure::note_exists() );
		$this->assertTrue( FeedOwnership::is_recorded( '1574695656968' ) );
	}

	/**
	 * A feed Pinterest confirmed as deleted is removed from the ownership record.
	 *
	 * @return void
	 */
	public function test_feed_delete_forgets_the_deleted_feed() {
		FeedOwnership::record( '1574695656968' );
		add_filter( 'pre_http_request', array( self::class, 'feed_delete_success' ), 10, 3 );

		$result = Feeds::delete_feed( '1574695656968' );

		$this->assertTrue( $result );
		$this->assertFalse( FeedOwnership::is_recorded( '1574695656968' ) );
	}

	/**
	 * Creating a feed records the ID Pinterest assigned to it, independently of the
	 * location matching that produces the returned feed ID.
	 *
	 * @return void
	 */
	public function test_create_feed_records_the_created_feed_id() {
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array( Pinterest_For_Woocommerce::get_base_country() => 'fIOasjj' )
		);
		add_filter( 'pre_http_request', array( self::class, 'feed_creation' ), 10, 3 );

		$feed_id = Feeds::create_feed();

		$this->assertSame( '1558987740004', $feed_id );
		$this->assertTrue( FeedOwnership::is_recorded( '1558987740004' ) );
	}

	/**
	 * Fetching every feed follows the bookmark until the API returns no further page.
	 *
	 * @return void
	 */
	public function test_get_all_feeds_follows_the_bookmark() {
		add_filter( 'pre_http_request', array( self::class, 'paginated_feeds' ), 10, 3 );

		$feeds = Feeds::get_all_feeds();

		$this->assertSame(
			array( 'first-page-feed-id', 'second-page-feed-id' ),
			array_column( $feeds, 'id' )
		);
	}

	/**
	 * A feed Pinterest reports as DELETED must not be matched as the registered feed,
	 * otherwise a force-deleted feed would be picked again instead of creating a new one.
	 *
	 * @return void
	 */
	public function test_registered_feed_match_skips_deleted_feeds() {
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array( Pinterest_For_Woocommerce::get_base_country() => 'fIOasjj' )
		);
		$location = wp_get_upload_dir()['baseurl'] . '/pinterest-for-woocommerce-fIOasjj.xml';
		$feed     = array(
			'id'              => 'deleted-feed-id',
			'status'          => Feeds::FEED_STATUS_DELETED,
			'location'        => $location,
			'default_locale'  => LocaleMapper::get_locale_for_api(),
			'default_country' => Pinterest_For_Woocommerce::get_base_country(),
		);
		$active   = array_merge(
			$feed,
			array(
				'id'     => 'active-feed-id',
				'status' => Feeds::FEED_STATUS_ACTIVE,
			)
		);

		$this->assertSame( '', Feeds::match_local_feed_configuration_to_registered_feeds( array( $feed ) ) );
		$this->assertSame( 'active-feed-id', Feeds::match_local_feed_configuration_to_registered_feeds( array( $feed, $active ) ) );
	}

	/**
	 * Fakes a two-page feeds endpoint: the first page carries a bookmark, the second does not.
	 *
	 * @param false|array $response    Preempted response.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url         Request URL.
	 * @return false|array
	 */
	public static function paginated_feeds( $response, $parsed_args, $url ) {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' === $url ) {
			return self::response(
				array(
					'items'    => array( array( 'id' => 'first-page-feed-id' ) ),
					'bookmark' => 'page-2',
				)
			);
		}

		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212&bookmark=page-2' === $url ) {
			return self::response( array( 'items' => array( array( 'id' => 'second-page-feed-id' ) ) ) );
		}

		return $response;
	}

	/**
	 * Fakes a successful feed deletion.
	 *
	 * @param false|array $response    Preempted response.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url         Request URL.
	 * @return false|array
	 */
	public static function feed_delete_success( $response, $parsed_args, $url ) {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds/1574695656968?ad_account_id=114141241212' === $url ) {
			return self::response( array(), 204 );
		}
		return $response;
	}

	/**
	 * Fakes the feeds endpoint during feed creation: the POST echoes the submitted feed back
	 * with a Pinterest ID, the GET lists no feeds.
	 *
	 * @param false|array $response    Preempted response.
	 * @param array       $parsed_args Request arguments.
	 * @param string      $url         Request URL.
	 * @return false|array
	 */
	public static function feed_creation( $response, $parsed_args, $url ) {
		if ( 'https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=114141241212' !== $url ) {
			return $response;
		}

		if ( 'POST' === $parsed_args['method'] ) {
			$feed           = json_decode( $parsed_args['body'], true );
			$feed['id']     = '1558987740004';
			$feed['status'] = 'ACTIVE';
			return self::response( $feed, 201 );
		}

		return self::response( array( 'items' => array() ) );
	}

	/**
	 * Builds a WordPress HTTP API response.
	 *
	 * @param array $body        Response body.
	 * @param int   $status_code Response status code.
	 * @return array
	 */
	private static function response( array $body, int $status_code = 200 ): array {
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status_code,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => '',
		);
	}

	public function test_maybe_remote_feed_returns_feed_id() {
		add_filter(
			'upload_dir',
			function ( $uploads ) {
				$uploads['baseurl'] = 'https://example-1.com';
				return $uploads;
			}
		);
		add_filter( 'pre_http_request', array( self::class, 'get_feeds' ), 10, 3 );

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( 'fIOasjj', $feed );
	}

	/**
	 * Tests matching an HTTPS remote feed when the local upload URL is HTTP.
	 *
	 * @return void
	 */
	public function test_maybe_remote_feed_matches_when_upload_dir_is_http() {
		add_filter(
			'upload_dir',
			function ( $uploads ) {
				$uploads['baseurl'] = 'http://example-1.com';
				return $uploads;
			}
		);
		add_filter( 'pre_http_request', array( self::class, 'get_feeds' ), 10, 3 );

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( 'fIOasjj', $feed );
	}

	/**
	 * Tests registered feed matching normalizes the remote feed URL scheme.
	 *
	 * @return void
	 */
	public function test_registered_feed_match_normalizes_remote_location_scheme() {
		Pinterest_For_Woocommerce::save_data(
			'local_feed_ids',
			array(
				'US' => 'fIOasjj',
			)
		);
		add_filter(
			'upload_dir',
			function ( $uploads ) {
				$uploads['baseurl'] = 'https://example-1.com';
				return $uploads;
			}
		);

		$feed = Feeds::match_local_feed_configuration_to_registered_feeds(
			array(
				array(
					'id'              => '278913891236895123895',
					'location'        => 'http://example-1.com/pinterest-for-woocommerce-fIOasjj.xml',
					'default_locale'  => 'en-US',
					'default_country' => 'US',
				),
			)
		);

		$this->assertEquals( '278913891236895123895', $feed );
	}

	public function test_maybe_remote_feed_returns_empty_feed_id() {
		add_filter(
			'upload_dir',
			function ( $uploads ) {
				$uploads['baseurl'] = 'https://example-11.com';
				return $uploads;
			}
		);
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
