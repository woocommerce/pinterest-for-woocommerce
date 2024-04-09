<?php
/**
 * Pinterest API Token exchange class.
 *
 * @class       TokenExchangeV3ToV5
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Crypto;
use Automattic\WooCommerce\Pinterest\Logger;
use Throwable;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * V3 to V5 token exchange class.
 * Contains the methods API methods and the update procedure.
 *
 * @since 1.4.0
 */
class TokenExchangeV3ToV5 extends APIV5 {

	const API_DOMAIN = 'https://api.pinterest.com/v3';

	/**
	 * Pull ads supported countries information from the API.
	 *
	 * @since 1.4.0
	 *
	 * @return array {
	 *     Contains the status and token details.
	 *
	 *     @type string $status                         The status of the token exchange.
	 *     @type array  $data {
	 *         Contains token details.
	 *
	 *         @type string $access_token                   The access token for authentication.
	 *         @type string $refresh_token                  The refresh token for acquiring a new access token.
	 *         @type string $token_type                     Type of the token, usually "bearer".
	 *         @type int    $expires_in                     Time in seconds when the access token expires.
	 *         @type int    $refresh_token_expires_in       Time in seconds when the refresh token expires.
	 *         @type string $scope                          The scope for which the access token has permission.
	 *     }
	 * }
	 */
	public static function exchange_token() {
		$request_url = 'oauth/commerce_integrations/token/exchange/';
		return self::make_request( $request_url );
	}
	/**
	 * Update token from V3 to V5.
	 *
	 * @since 1.4.0
	 *
	 * @throws Exception PHP Exception.
	 *
	 * @return bool $success Whether the token was updated successfully.
	 */
	public static function token_update() {

		// Try to exchange the token.
		try {
			$response = self::exchange_token();
			if ( 'success' !== $response['status'] ) {
				throw new Exception(
					sprintf(
						/* translators: %s connection status code. */
						esc_html__(
							'Connection status: %s',
							'pinterest-for-woocommerce'
						),
						$response['status']
					)
				);
			}
		} catch ( Throwable $th ) {
			Logger::log(
				sprintf(
					/* translators: 1. Error message. */
					esc_html__(
						'Automatic token exchange failed. Try reconnecting to Pinterest manually. [%1$s]',
						'pinterest-for-woocommerce'
					),
					$th->getMessage()
				),
				'error'
			);
			return false;
		}

		$token_data = $response['data'];

		Pinterest_For_Woocommerce()::save_token_data( $token_data );

		$info_data = array(
			'advertiser_id' => Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' ),
			'tag_id'        => Pinterest_For_WooCommerce()::get_setting( 'tracking_tag' ),
			'merchant_id'   => Pinterest_For_Woocommerce()::get_data( 'merchant_id' ),
		);
		Pinterest_For_Woocommerce()::save_connection_info_data( $info_data );

		try {
			/**
			 * Actions to perform after getting the authorization token.
			 *
			 * @since 1.4.0
			 */
			do_action( 'pinterest_for_woocommerce_token_saved' );
		} catch ( Throwable $th ) {
			Logger::log(
				sprintf(
					/* translators: 1. Error message. */
					esc_html__(
						'Could not finish the Pinterest API connection flow. Try reconnecting to Pinterest. [%1$s]',
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
	 * @since 1.4.0
	 *
	 * @return string $token The V3 token.
	 */
	public static function get_token() {
		$token = Pinterest_For_Woocommerce()::get_data( 'token', true );

		try {
			$token['access_token'] = empty( $token['access_token'] ) ? '' : Crypto::decrypt( $token['access_token'] );
		} catch ( Exception $th ) {
			/* Translators: The error description */
			Logger::log( sprintf( esc_html__( 'Could not decrypt the Pinterest API access token. Try reconnecting to Pinterest. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() ), 'error' );
		}

		return $token;
	}
}
