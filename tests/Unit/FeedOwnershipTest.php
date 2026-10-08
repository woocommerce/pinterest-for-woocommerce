<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\FeedOwnership;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Tests the record of the feeds the plugin owns on Pinterest.
 *
 * @version x.x.x
 */
class FeedOwnershipTest extends WP_UnitTestCase {

	/**
	 * Sets up the test case.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		Pinterest_For_Woocommerce::set_default_settings();
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '114141241212' );
		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * Tears down the test case.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		parent::tearDown();

		delete_option( FeedOwnership::OPTION_NAME );
	}

	/**
	 * A recorded feed is found under the connected ad account only.
	 *
	 * @return void
	 */
	public function test_recorded_feed_is_found_under_the_connected_ad_account() {
		FeedOwnership::record( '1558987740004' );

		$this->assertTrue( FeedOwnership::is_recorded( '1558987740004' ) );
		$this->assertTrue( FeedOwnership::is_recorded( '1558987740004', '114141241212' ) );
		$this->assertFalse( FeedOwnership::is_recorded( '1558987740004', '999' ) );
		$this->assertFalse( FeedOwnership::is_recorded( '1563009726401' ) );
	}

	/**
	 * Recording a feed twice keeps the first timestamp.
	 *
	 * @return void
	 */
	public function test_record_keeps_the_original_timestamp() {
		FeedOwnership::record( '1558987740004' );
		$recorded_at = FeedOwnership::get_recorded()['1558987740004'];

		$option                                  = get_option( FeedOwnership::OPTION_NAME );
		$option['114141241212']['1558987740004'] = $recorded_at - HOUR_IN_SECONDS;
		update_option( FeedOwnership::OPTION_NAME, $option );

		FeedOwnership::record( '1558987740004' );

		$this->assertSame( $recorded_at - HOUR_IN_SECONDS, FeedOwnership::get_recorded()['1558987740004'] );
	}

	/**
	 * An empty feed ID is never recorded.
	 *
	 * @return void
	 */
	public function test_record_ignores_an_empty_feed_id() {
		FeedOwnership::record( '' );

		$this->assertFalse( get_option( FeedOwnership::OPTION_NAME ) );
	}

	/**
	 * Nothing is recorded without a connected ad account to key the record by.
	 *
	 * @return void
	 */
	public function test_record_ignores_a_feed_when_no_ad_account_is_connected() {
		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', null );

		FeedOwnership::record( '1558987740004' );

		$this->assertFalse( get_option( FeedOwnership::OPTION_NAME ) );
	}

	/**
	 * Forgetting a feed leaves the other feeds and ad accounts untouched.
	 *
	 * @return void
	 */
	public function test_forget_removes_only_the_given_feed() {
		FeedOwnership::record( '1558987740004' );
		FeedOwnership::record( '1563009726401' );
		FeedOwnership::record( '1558987740004', '999' );

		FeedOwnership::forget( '1558987740004' );

		$this->assertFalse( FeedOwnership::is_recorded( '1558987740004' ) );
		$this->assertTrue( FeedOwnership::is_recorded( '1563009726401' ) );
		$this->assertTrue( FeedOwnership::is_recorded( '1558987740004', '999' ) );
	}

	/**
	 * An ad account without recorded feeds is removed from the option.
	 *
	 * @return void
	 */
	public function test_forget_drops_an_ad_account_without_feeds() {
		FeedOwnership::record( '1558987740004' );

		FeedOwnership::forget( '1558987740004' );

		$this->assertSame( array(), get_option( FeedOwnership::OPTION_NAME ) );
	}

	/**
	 * A corrupted option value reads as an empty record.
	 *
	 * @return void
	 */
	public function test_get_recorded_tolerates_a_corrupted_option() {
		update_option( FeedOwnership::OPTION_NAME, 'not-an-array' );

		$this->assertSame( array(), FeedOwnership::get_recorded() );
		$this->assertFalse( FeedOwnership::is_recorded( '1558987740004' ) );
	}

	/**
	 * Disconnecting flushes the plugin data option. The ownership record must survive it so
	 * that a feed Pinterest refused to delete stays known after the merchant reconnects.
	 *
	 * @return void
	 */
	public function test_record_survives_disconnecting_the_account() {
		FeedOwnership::record( '1558987740004' );

		Pinterest_For_Woocommerce::disconnect();

		$this->assertNull( Pinterest_For_Woocommerce::get_data( 'feed_registered', true ) );
		$this->assertTrue( FeedOwnership::is_recorded( '1558987740004', '114141241212' ) );
	}
}
