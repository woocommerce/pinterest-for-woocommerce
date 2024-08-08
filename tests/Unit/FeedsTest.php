<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit;

use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;
use Pinterest_For_Woocommerce;
use WC_Helper_Product;

class FeedsTest extends \WP_UnitTestCase {

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
}
