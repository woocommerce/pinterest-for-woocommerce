<?php
/**
 * Pinterest API V5 class
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\PinterestApiException as ApiException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API V5 Methods
 */
class APIV5 extends Base {

	const API_DOMAIN = 'https://api.pinterest.com/v5';

	/**
	 * Prepare request
	 *
	 * @param string $endpoint        the endpoint to perform the request on.
	 * @param string $method          eg, POST, GET, PUT etc.
	 * @param array  $payload         Payload to be sent on the request's body.
	 * @param string $api             The specific Endpoints subset.
	 *
	 * @return array
	 */
	public static function prepare_request( $endpoint, $method = 'POST', $payload = array(), $api = '' ) {

		return array(
			'url'         => static::API_DOMAIN . "/{$endpoint}",
			'method'      => $method,
			'args'        => $payload,
			'headers'     => array(
				'Pinterest-Woocommerce-Version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				'Content-Type'                  => 'application/json',
			),
			'data_format' => 'body',
		);
	}

	/**
	 * Returns basic user information.
	 *
	 * @since x.x.x
	 *
	 * @return array|mixed
	 * @throws ApiException
	 */
	public static function get_account_info() {
		$integration_data = \Pinterest_For_Woocommerce::get_data( 'integration_data', true );
		return self::make_request(
			'user_account',
			'GET'/*,
			json_encode(
				array(
					'ad_account_id' => $integration_data['connected_advertiser_id'] ?? '',
				)
			)*/
		);
	}


	/**
	 * Returns the list of the user's websites.
	 *
	 * @since x.x.x
	 *
	 * @return array
	 * @throws ApiException
	 */
	public static function get_user_websites() {
		return self::make_request(
			'user_account/websites',
			'GET'
		);
	}

	/**
	 * Returns the list of linked businesses.
	 *
	 * @since x.x.x
	 *
	 * @return array|mixed
	 * @throws ApiException
	 */
	public static function get_linked_businesses() {
		return self::make_request( 'user_account/businesses', 'GET' );
	}

	/**
	 * Get the advertiser object from the Pinterest API for the given User ID.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 */
	public static function get_advertisers( $pinterest_user = null ) {
		return self::make_request( 'ad_accounts', 'GET' );
	}

	/**
	 * Get the advertiser's tracking tags.
	 *
	 * @param string $ad_account_id the advertiser_id to request the tags for.
	 *
	 * @return mixed
	 */
	public static function get_advertiser_tags( $ad_account_id ) {
		return self::make_request( "ad_accounts/{$ad_account_id}/conversion_tags", 'GET' );
	}
}
