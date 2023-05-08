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
use WC_Log_Levels;

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

		/**
		 * Filters to determine if it is time to start refreshing the token.
		 *
		 * @since x.x.x
		 *
		 * @param bool $maybe_refresh - True if it is time to refresh the token.
		 */
		$maybe_refresh = apply_filters(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_maybe_refresh_token',
			$refresh_date + $expires_in - 2 * DAY_IN_SECONDS <= time()
		);

		// Translators: %s is the value of $maybe_refresh.
		$refresh_log = sprintf( 'Should refresh pinterest access token: %s', wc_bool_to_string( $maybe_refresh ) );
		Logger::log( $refresh_log, WC_Log_Levels::DEBUG, 'pinterest-for-woocommerce' );

		if ( $maybe_refresh ) {
			try {
				$endpoint = Pinterest_For_Woocommerce::get_connection_proxy_url() . 'renew/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE;
				$options  = array(
					'headers' => array(
						'Content-Type' => 'application/x-www-form-urlencoded',
					),
					'body'    => array(
						'refresh_token' => Crypto::decrypt( $token_data['refresh_token'] ),
					),
					'sslverify' => false,
				);

				// Translators: %1$s is the endpoint, %2$s is the options.
				$request_log = sprintf( 'Refresh token request to %1$s with %2$s', $endpoint, json_encode( $options ) );
				Logger::log( $request_log, WC_Log_Levels::DEBUG, 'pinterest-for-woocommerce' );

				$response = wp_safe_remote_post( $endpoint, $options );
				$body     = trim( wp_remote_retrieve_body( $response ) );

				Logger::log( $body, WC_Log_Levels::DEBUG, 'pinterest-for-woocommerce' );

				$body = json_decode( $body, true );
				Pinterest_For_Woocommerce::save_token_data( $body );
			} catch ( Exception $e ) {
				Logger::log( $e->getMessage(), WC_Log_Levels::ERROR, 'pinterest-for-woocommerce' );
				return false;
			}
		}
		return true;
	}
}
