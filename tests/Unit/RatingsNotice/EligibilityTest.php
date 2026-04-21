<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\RatingsNotice;

use Automattic\WooCommerce\Pinterest\ProductFeedStatus;
use Automattic\WooCommerce\Pinterest\RatingsNotice\Eligibility;
use Automattic\WooCommerce\Pinterest\Utilities\Utilities;
use Pinterest_For_Woocommerce;

/**
 * Unit tests for the ratings notice eligibility evaluator.
 *
 * @covers \Automattic\WooCommerce\Pinterest\RatingsNotice\Eligibility
 */
class EligibilityTest extends \WP_UnitTestCase {

	/**
	 * Reset persistent state between tests.
	 */
	public function setUp(): void {
		parent::setUp();
		ProductFeedStatus::deregister();
		delete_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP );
		remove_all_filters( 'pinterest_for_woocommerce_rating_notice_eligibility' );
	}

	/**
	 * With no timestamp recorded, the connected-for-X-days gate fails closed.
	 */
	public function test_connected_for_at_least_returns_false_when_timestamp_missing() {
		$this->assertFalse( Eligibility::connected_for_at_least( 14 ) );
	}

	/**
	 * The gate passes past the window and fails inside it.
	 */
	public function test_connected_for_at_least_respects_window() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, time() - ( 20 * DAY_IN_SECONDS ) );
		$this->assertTrue( Eligibility::connected_for_at_least( 14 ) );

		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, time() - ( 5 * DAY_IN_SECONDS ) );
		$this->assertFalse( Eligibility::connected_for_at_least( 14 ) );
	}

	/**
	 * Recent reconnect is true when the timestamp is within the recent window.
	 */
	public function test_recently_reconnected_when_timestamp_inside_window() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, time() - ( 2 * DAY_IN_SECONDS ) );
		$this->assertTrue( Eligibility::recently_reconnected() );
	}

	/**
	 * Recent reconnect is false for stable, long-lived connections.
	 */
	public function test_recently_reconnected_false_when_old() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, time() - ( 30 * DAY_IN_SECONDS ) );
		$this->assertFalse( Eligibility::recently_reconnected() );
	}

	/**
	 * An explicit error feed status counts as a recent sync error.
	 */
	public function test_has_recent_sync_error_on_error_status() {
		ProductFeedStatus::set( array( 'status' => 'error' ) );
		$this->assertTrue( Eligibility::has_recent_sync_error() );
	}

	/**
	 * A non-empty error message counts as a recent sync error even if status looks healthy.
	 */
	public function test_has_recent_sync_error_on_error_message() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'error_message' => 'boom',
			)
		);
		$this->assertTrue( Eligibility::has_recent_sync_error() );
	}

	/**
	 * A clean, generated feed reports no sync error.
	 */
	public function test_has_recent_sync_error_false_when_clean() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'error_message' => '',
			)
		);
		$this->assertFalse( Eligibility::has_recent_sync_error() );
	}

	/**
	 * Catalog sync is considered live with recent activity and enough synced products.
	 */
	public function test_catalog_sync_live_requires_generated_recent_and_product_count() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'last_activity' => time() - ( 2 * DAY_IN_SECONDS ),
				'product_count' => 50,
			)
		);
		$this->assertTrue( Eligibility::catalog_sync_live() );
	}

	/**
	 * Stale activity disqualifies catalog sync from being live.
	 */
	public function test_catalog_sync_live_false_when_stale() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'last_activity' => time() - ( 30 * DAY_IN_SECONDS ),
				'product_count' => 50,
			)
		);
		$this->assertFalse( Eligibility::catalog_sync_live() );
	}

	/**
	 * Sub-threshold product counts disqualify catalog sync.
	 */
	public function test_catalog_sync_live_false_when_too_few_products() {
		ProductFeedStatus::set(
			array(
				'status'        => 'generated',
				'last_activity' => time(),
				'product_count' => 2,
			)
		);
		$this->assertFalse( Eligibility::catalog_sync_live() );
	}

	/**
	 * Any non-generated status disqualifies catalog sync from being live.
	 */
	public function test_catalog_sync_live_false_when_status_not_generated() {
		ProductFeedStatus::set(
			array(
				'status'        => 'pending_config',
				'last_activity' => time(),
				'product_count' => 50,
			)
		);
		$this->assertFalse( Eligibility::catalog_sync_live() );
	}

	/**
	 * Conversion tracking requires both the toggle and a configured tag.
	 */
	public function test_conversion_tracking_active_requires_toggle_and_tag() {
		Pinterest_For_Woocommerce::save_settings(
			array(
				'track_conversions' => true,
				'tracking_tag'      => 'WD7AFW51GS',
			)
		);
		$this->assertTrue( Eligibility::conversion_tracking_active() );
	}

	/**
	 * With the toggle off, conversion tracking is considered inactive.
	 */
	public function test_conversion_tracking_active_false_when_disabled() {
		Pinterest_For_Woocommerce::save_settings(
			array(
				'track_conversions' => false,
				'tracking_tag'      => 'WD7AFW51GS',
			)
		);
		$this->assertFalse( Eligibility::conversion_tracking_active() );
	}

	/**
	 * Without a tag, conversion tracking is considered inactive.
	 */
	public function test_conversion_tracking_active_false_when_no_tag() {
		Pinterest_For_Woocommerce::save_settings(
			array(
				'track_conversions' => true,
				'tracking_tag'      => '',
			)
		);
		$this->assertFalse( Eligibility::conversion_tracking_active() );
	}

	/**
	 * Token refresh after connection indicates a durable, healthy install.
	 */
	public function test_token_refreshed_post_install_true_when_refreshed_after_connect() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, 1000 );
		Pinterest_For_Woocommerce::save_data( 'token_data', array( 'refresh_time' => 2000 ) );
		$this->assertTrue( Eligibility::token_refreshed_post_install() );
	}

	/**
	 * A refresh timestamp older than the connection timestamp is not a valid signal.
	 */
	public function test_token_refreshed_post_install_false_when_refresh_older() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, 5000 );
		Pinterest_For_Woocommerce::save_data( 'token_data', array( 'refresh_time' => 2000 ) );
		$this->assertFalse( Eligibility::token_refreshed_post_install() );
	}

	/**
	 * Missing token data disqualifies the post-install refresh signal.
	 */
	public function test_token_refreshed_post_install_false_when_missing() {
		update_option( Utilities::ACCOUNT_CONNECTION_TIMESTAMP, 1000 );
		$this->assertFalse( Eligibility::token_refreshed_post_install() );
	}

	/**
	 * Compute short-circuits with the setup-incomplete reason when no connection exists.
	 */
	public function test_compute_returns_setup_incomplete_when_not_connected() {
		$result = Eligibility::compute();

		$this->assertFalse( $result['eligible'] );
		$this->assertSame( Eligibility::REASON_SETUP_INCOMPLETE, $result['reason'] );
	}

	/**
	 * The eligibility filter can override the computed result.
	 */
	public function test_compute_filter_short_circuits_result() {
		add_filter(
			'pinterest_for_woocommerce_rating_notice_eligibility',
			static function () {
				return array(
					'eligible' => true,
					'reason'   => Eligibility::REASON_OK,
				);
			}
		);

		$result = Eligibility::compute();

		$this->assertTrue( $result['eligible'] );
		$this->assertSame( Eligibility::REASON_OK, $result['reason'] );
	}
}
