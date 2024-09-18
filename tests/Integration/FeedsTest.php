<?php declare(strict_types=1);

namespace Automattic\WooCommerce\Pinterest\Tests\Integration;

use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Pinterest_For_Woocommerce;

class FeedsTest extends \WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Tests emulates Pinterest create feed endpoint response when a feed with the same name already exists.
	 *
	 * @return void
	 */
	public function test_feed_registration_handles_422_name_already_exists()
	{
		$this->expectException( PinterestApiException::class );
		$this->expectExceptionCode( 422 );
		$this->expectExceptionMessage( 'Unprocessable Entity' );

		Pinterest_For_Woocommerce::save_setting( 'tracking_advertiser', '123' );
		Pinterest_For_Woocommerce::save_data( 'local_feed_ids', '' );

		$this->create_feed_request_stub();

		Feeds::create_feed();
	}

	private function create_feed_request_stub()
	{
		add_filter(
			'pre_http_request',
			function ($response, $parsed_args, $url) {
				if ('https://api.pinterest.com/v5/catalogs/feeds?ad_account_id=123' === $url) {
					$response = array(
						'headers'  => array(
							'content-type' => 'application/json',
						),
						'body'     => json_encode(
							array(
								'code'    => 4170,
								'message' => 'Unprocessable Entity',
							)
						),
						'response' => array(
							'code'    => 422,
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
