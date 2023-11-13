'url'           => get_site_url(),<?php
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
		$refresh_time = intval( $token_data['refresh_time'] );

		/**
		 * Filters to determine if it is time to start refreshing the token.
		 *
		 * @since x.x.x
		 *
		 * @param bool $maybe_refresh - True if it is time to refresh the token.
		 */
		$maybe_refresh = apply_filters(
			PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_maybe_refresh_token',
			( $refresh_time + $expires_in - 2 * DAY_IN_SECONDS ) <= time()
		);

		if ( $maybe_refresh ) {
			try {
				$refreshed_token_data = self::refresh_token( $token_data );
				Pinterest_For_Woocommerce::save_token_data( $refreshed_token_data );
			} catch ( Exception $e ) {
				self::log( $e->getMessage(), WC_Log_Levels::ERROR );
				return false;
			}
		}
		return true;
	}

	/**
	 * Refreshes the token by sending a request to the Pinterest API.
	 *
	 * @param array $token_data The token data to be refreshed.
	 *
	 * @since x.x.x
	 *
	 * @throws Exception If the request fails.
	 *
	 * @return array|false The refreshed token data or false if the request failed.
	 */
	private static function refresh_token( $token_data ) {
		$endpoint = Pinterest_For_Woocommerce::get_connection_proxy_url() . 'renew/' . PINTEREST_FOR_WOOCOMMERCE_WOO_CONNECT_SERVICE;
		$options  = array(
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
			),
			'body'    => array(
				'refresh_token' => Crypto::decrypt( $token_data['refresh_token'] ),
				'url'           => get_site_url(),
			),
			'sslverify' => false,
		);

		// Translators: %s is the endpoint.
		$request_log = sprintf( __( 'Refresh token request to %s.', 'pinterest-for-woocommerce' ), $endpoint );
		self::log( $request_log );

		$response = wp_safe_remote_post( $endpoint, $options );

		if ( is_wp_error( $response ) ) {
			throw new Exception( $response->get_error_message() );
		}

		$body = trim( wp_remote_retrieve_body( $response ) );
		$body = json_decode( $body, true );

		$response['body'] = '**************';
		self::log( $response );

		if ( ! is_array( $body ) || ! isset( $body['access_token'], $body['expires_in'] ) ) {
			return false;
		}

		return $body;
	}

	/**
	 * Logs a message to the log file.
	 *
	 * @since x.x.x
	 *
	 * @param string $message The message to be logged.
	 * @param string $level The level of the message.
	 *
	 * @return void
	 */
	public static function log( $message, $level = WC_Log_Levels::DEBUG ) {
		Logger::log( $message, $level, 'pinterest-for-woocommerce-oauth-refresh' );
	}
}
