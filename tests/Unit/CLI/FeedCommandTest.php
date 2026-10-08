<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\CLI;

use Automattic\WooCommerce\Pinterest\CLI\FeedCommand;
use Automattic\WooCommerce\Pinterest\FeedOwnership;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests the rows the `wp wc pinterest feed list` command builds from the Pinterest feed list.
 *
 * @version x.x.x
 */
class FeedCommandTest extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		Pinterest_For_Woocommerce::save_data( 'feed_registered', 'registered-feed-id' );
		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		Pinterest_For_Woocommerce::save_data( 'feed_registered', false );
		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * Each row tells whether the feed is the registered one, on the ownership record, or at the plugin feed location.
	 *
	 * @return void
	 */
	public function test_get_rows_reports_how_the_plugin_relates_to_each_feed() {
		FeedOwnership::record( 'recorded-feed-id' );
		$site_url = get_site_url();

		$rows = FeedCommand::get_rows(
			array(
				array(
					'id'       => 'registered-feed-id',
					'status'   => 'ACTIVE',
					'name'     => 'Created by Pinterest for WooCommerce at example.org US|en-US|USD',
					'location' => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
				),
				array(
					'id'       => 'recorded-feed-id',
					'status'   => 'INACTIVE',
					'location' => 'https://old-domain.test/wp-content/uploads/pinterest-for-woocommerce-Zz9yX8.xml',
				),
				array(
					'id'       => 'manual-feed-id',
					'status'   => 'ACTIVE',
					'name'     => 'Manual feed',
					'location' => 'https://feeds.example.com/manual-feed.xml',
				),
				array(
					'location' => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ef3gH4.xml',
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'id'              => 'registered-feed-id',
					'status'          => 'ACTIVE',
					'registered'      => 'yes',
					'recorded'        => 'no',
					'plugin_location' => 'yes',
					'name'            => 'Created by Pinterest for WooCommerce at example.org US|en-US|USD',
					'location'        => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ab1cD2.xml',
				),
				array(
					'id'              => 'recorded-feed-id',
					'status'          => 'INACTIVE',
					'registered'      => 'no',
					'recorded'        => 'yes',
					'plugin_location' => 'no',
					'name'            => '',
					'location'        => 'https://old-domain.test/wp-content/uploads/pinterest-for-woocommerce-Zz9yX8.xml',
				),
				array(
					'id'              => 'manual-feed-id',
					'status'          => 'ACTIVE',
					'registered'      => 'no',
					'recorded'        => 'no',
					'plugin_location' => 'no',
					'name'            => 'Manual feed',
					'location'        => 'https://feeds.example.com/manual-feed.xml',
				),
				array(
					'id'              => '',
					'status'          => '',
					'registered'      => 'no',
					'recorded'        => 'no',
					'plugin_location' => 'yes',
					'name'            => '',
					'location'        => $site_url . '/wp-content/uploads/pinterest-for-woocommerce-Ef3gH4.xml',
				),
			),
			$rows
		);
	}

	/**
	 * A feed without an ID is not reported as registered when no feed is registered locally.
	 *
	 * @return void
	 */
	public function test_get_rows_does_not_mark_a_feed_without_an_id_as_registered() {
		Pinterest_For_Woocommerce::save_data( 'feed_registered', false );

		$rows = FeedCommand::get_rows( array( array( 'location' => 'https://feeds.example.com/manual-feed.xml' ) ) );

		$this->assertSame( 'no', $rows[0]['registered'] );
	}
}
