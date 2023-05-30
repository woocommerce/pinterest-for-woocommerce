<?php
/**
 * Class AdvertiserConnectTest
 *
 * @since x.x.x
 */
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\AdvertiserConnect;
use Exception;
use WP_HTTP_Requests_Response;
use WpOrg\Requests\Response;

class AdvertiserConnectTest extends \WP_UnitTestCase {

	public function test_connect_advertiser_and_tag_successfully_connects() {
		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							"id"                           => "987654321234567890",
							"external_business_id"         => "cbi-1234567890",
							"connected_merchant_id"        => "cmi-1234567890",
							"connected_user_id"            => "cui-1234567890",
							"connected_advertiser_id"      => $parsed_args['body']['advertiser_id'],
							"connected_lba_id"             => "cli-1234567890",
							"connected_tag_id"             => $parsed_args['body']['tag_id'],
							"partner_access_token_expiry"  => 1621350033000,
							"partner_refresh_token_expiry" => 1621350033000,
							"scopes"                       => "s-c-o-p-e-s",
							"created_timestamp"            => 1621350033000,
							"updated_timestamp"            => 1621350033300,
							"additional_id_1"              => "ai1-1234567890",
							"partner_metadata"             => "partner-meta-data",
						)
					),
					'response' => array(
						'code' => 200,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
				);
			},
			10,
			3
		);

		$output = AdvertiserConnect::connect_advertiser_and_tag(
			'ai-1234567890',
			'ti-1234567890'
		);

		$this->assertEquals(
			array(
				'connected'   => 'ai-1234567890',
				'reconnected' => true,
			),
			$output
		);
	}

	public function test_connect_advertiser_and_tag_returns_error() {
		$this->expectException( Exception::class );
		$this->expectExceptionCode( 400 );
		$this->expectExceptionMessage( 'Sorry! We could not find your integration.' );

		add_filter(
			'pre_http_request',
			function ( $response, $parsed_args, $url ) {
				return array(
					'headers'  => array(
						'content-type' => 'application/json',
					),
					'body'     => json_encode(
						array(
							'code'    => 4180,
							'message' => 'Sorry! We could not find your integration.',
						)
					),
					'response' => array(
						'code' => 404,
					),
					'cookies'  => array(),
					'filename' => '',
					'http_response' => new WP_HTTP_Requests_Response(
						new Response(),
						''
					),
				);
			},
			10,
			3
		);

		AdvertiserConnect::connect_advertiser_and_tag(
			'ai-1234567890',
			'ti-1234567890'
		);
	}
}
