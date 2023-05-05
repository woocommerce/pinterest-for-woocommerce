<?php
/**
 * Pinterest for WooCommerce Refresh Token.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Exception;
use Pinterest_For_Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling Token Refresh.
 */
class RefreshToken {

	/**
	 * Initialize Refresh Token actions and Action Scheduler hooks.
	 *
	 * @since x.x.x
	 */
	public static function schedule_event() {
		if ( ! has_action( Heartbeat::DAILY, array( self::class, 'handle_refresh' ) ) ) {
			add_action( Heartbeat::DAILY, array( self::class, 'handle_refresh' ), 20 );
		}
	}

	/**
	 * Checks if it is time to refresh the token and refreshes it if needed.
	 *
	 * @since x.x.x
	 *
	 * @return true
	 */
	public static function handle_refresh(): bool {
		$token_data   = Pinterest_For_Woocommerce::get_data( 'token_data', true );
		$expires_in   = intval( $token_data['expires_in'] );
		$refresh_date = intval( $token_data['refresh_date'] );

		if ( $refresh_date + $expires_in - 2 * DAY_IN_SECONDS <= time() ) {
			try {
				$endpoint = Pinterest_For_Woocommerce::get_connection_proxy_url() . 'renew/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE;
				$response = wp_safe_remote_post(
					$endpoint,
					array(
						'headers' => array(
							'Content-Type' => 'application/x-www-form-urlencoded',
						),
						'body'    => array(
							'refresh_token' => Crypto::decrypt( $token_data['refresh_token'] ),
						),
						'sslverify' => false,
					)
				);
				$body = trim( wp_remote_retrieve_body( $response ) );
				$body = json_decode( $body, true );
				Pinterest_For_Woocommerce::save_token_data( $body );
			} catch ( Exception $e ) {
				// Log the error.
				return false;
			}
		}
		return true;
	}
}
