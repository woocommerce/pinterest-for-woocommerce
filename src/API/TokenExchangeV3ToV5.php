<?php
/**
 * Pinterest API Token exchnge class.
 *
 * @class       TokenExchangeV3ToV5
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\Logger;
use Throwable;

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
	/**
	 * Update token from V3 to V5.
	 *
	 * @since x.x.x
	 *
	 * @return bool $success Whether the token was updated successfully.
	 */
	public static function token_update() {
		$respone = self::exchange_token();

		if ( 'success' !== $respone['status'] ) {
			return false;
		}

		$token_data = $respone['data'];

		Pinterest_For_Woocommerce()::save_token_data( $token_data );

		$info_data = array(
			'advertiser_id' => Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' ),
			'tag_id'        => Pinterest_For_WooCommerce()::get_setting( 'tracking_tag' ),
			'merchant_id'   => Pinterest_For_Woocommerce()::get_data( 'merchant_id' ),
			'feature_flags' => array(
				'tags'    => true,
				'CAPI'    => true,
				'catalog' => true,
			),
		);
		Pinterest_For_Woocommerce()::save_connection_info_data( $info_data );

		try {
			do_action( 'pinterest_for_woocommerce_token_saved' );
		} catch ( Throwable $th ) {
			/* Translators: The error description */
			Logger::log(
				sprintf(
					esc_html__(
						'Could not finish the Pinterest API connection flow. Try reconnecting to Pinterest. [%s]',
						'pinterest-for-woocommerce'
					),
					$th->getMessage()
				),
				'error'
			);
			return false;
		}

		return true;
	}

	/**
	 * Get the V3 token.
	 *
	 * @since x.x.x
	 *
	 * @return string $token The V3 token.
	 */
	public static function get_token() {
		$token = Pinterest_For_Woocommerce()::get_data( 'token', true );

		try {
			$token['access_token'] = empty( $token['access_token'] ) ? '' : Crypto::decrypt( $token['access_token'] );
		} catch ( \Exception $th ) {
			/* Translators: The error description */
			Logger::log( sprintf( esc_html__( 'Could not decrypt the Pinterest API access token. Try reconnecting to Pinterest. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() ), 'error' );
		}

		return $token;
	}

}
