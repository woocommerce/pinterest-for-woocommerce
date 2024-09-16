<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;

class FeedsTest extends \WP_UnitTestCase {

	/**
	 * Tests feed deletion produces an admin notice in case feed deletion has failed.
	 *
	 * @return void
	 */
	public function test_feed_delete_produces_an_admin_notification() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
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
			},
			10,
			3
		);

		$result = Feeds::delete_feed( '1574695656968' );

		$this->assertFalse( $result );
		$this->assertTrue( FeedDeletionFailure::note_exists() );
	}

	public function test_maybe_remote_feed_returns_feed_id() {
		add_filter(
			'site_url',
			function () {
				return 'https://example.org';
			}
		);

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers' => array(
						'content-type' => 'application/json',
					),
					'body' => json_encode(
						array(
							'items' => array(
								array(
									"created_at"                    => "2022-03-14T15:15:22Z",
									"id"                            => "278913891236895123895",
									"updated_at"                    => "2022-03-14T15:16:34Z",
									"name"                          => "Created by Pinterest for WooCommerce at pinterest.dima.works US|en-US|USD",
									"format"                        => "TSV",
									"catalog_type"                  => "RETAIL",
									"location"                      => "https://example.org/pinterest-for-woocommerce-fIOasjj.xml",
									"status"                        => "ACTIVE",
									"default_currency"              => "USD",
									"default_locale"                => "en-US",
									"default_country"               => "US",
									"default_availability"          => "IN_STOCK",
								),
							),
						),
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
		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( 'fIOasjj', $feed );
	}

	public function test_maybe_remote_feed_returns_empty_string() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
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
			},
			10,
			3
		);

		$feed = Feeds::maybe_remote_feed();

		$this->assertEquals( '', $feed );
	}
}
