<?php
/**
 * Pinterest API Token exchnge class.
 *
 * @class       TokenExchangeV3ToV5
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * V3 to V5 token exchange class.
 * Contains the methods API methods and the update procedure.
 *
 * @since x.x.x
 */
class TokenExchangeV3ToV5 extends APIV5 {

	const API_DOMAIN = 'https://api.pinterest.com/v3';

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
