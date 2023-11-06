<?php
/**
 * Pinterest API V5 class
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API V5 Methods
 */
class TokenExchangeV3ToV5 extends APIV5 {

	const API_DOMAIN = 'https://api.pinterest.com/v3';

	/**
	 * Return array with response data.
	 * We need this funcion becase all ther API endpoints return request data in body but this returns in data.
	 *
	 * @since x.x.x
	 *
	 * @param mixed $response The response to parse.
	 *
	 * @return array
	 *
	 * @throws Exception PHP exception.
	 */
	protected static function parse_response( $response ) {

		if ( ! array_key_exists( 'body', (array) $response ) ) {
			throw new Exception( __( 'Empty data response', 'pinterest-for-woocommerce' ), 204 );
		}

		return json_decode( $response['data'], true );
	}

	/**
	 * Pull ads supported countries information from the API.
	 *
	 * @since x.x.x
	 *
	 * @return array $data {
	 *     Contains token details.
	 *
	 *     @type string $access_token                   The access token for authentication.
	 *     @type string $refresh_token                  The refresh token for acquiring a new access token.
	 *     @type string $token_type                     Type of the token, usually "bearer".
	 *     @type int    $expires_in                     Time in seconds when the access token expires.
	 *     @type int    $refresh_token_expires_in       Time in seconds when the refresh token expires.
	 *     @type string $scope                          The scope for which the access token has permission.
	 * }
	 */
	public static function exchange_token() {
		$request_url = 'oauth/commerce_integrations/token/exchange/';
		return self::make_request( $request_url );
	}

}
