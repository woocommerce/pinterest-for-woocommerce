<?php
/**
 * Pinterest API
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     1.0.0
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Logger as Logger;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base API Methods
 */
class Base {

	const API_DOMAIN  = 'https://api.pinterest.com';
	const API_VERSION = 3;

	/**
	 * Holds the instance of the class.
	 *
	 * @var Base
	 */
	protected static $instance = null;


	/**
	 * The token as saved in the settings.
	 *
	 * @var array
	 */
	protected static $token = null;


	/**
	 * Initialize class
	 */
	public function __construct() {}


	/**
	 * Initialize and/or return the instance
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}


	/**
	 * API requests wrapper
	 *
	 * @since 1.0.0
	 *
	 * Request parameter:
	 * $endpoint
	 *
	 * @param string $endpoint the endpoint to perform the request on.
	 * @param string $method eg, POST, GET, PUT etc.
	 * @param array  $payload Payload to be sent on the request's body.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP exception.
	 */
	public static function make_request( $endpoint, $method = 'POST', $payload = array() ) {

		try {

			$request = array(
				'url'    => self::API_DOMAIN . '/v' . self::API_VERSION . '/' . $endpoint,
				'method' => $method,
				'args'   => $payload,
			);

			return self::handle_request( $request );
		} catch ( \Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}

	}


	/**
	 * Handle the request
	 *
	 * @since 1.0.0
	 *
	 * Request parameter:
	 * array['url']               string
	 * array['method']            string    Default: POST
	 * array['auth_header']       boolean   Defines if must send the token in the header. Default: true
	 * array['args']              array
	 * array['headers']           array
	 *          ['content-type']  string    Default: application/json
	 *
	 * @param array $request (See above).
	 *
	 * @return array
	 *
	 * @throws \Exception PHP exception.
	 */
	public static function handle_request( $request ) {

		$request = wp_parse_args(
			$request,
			array(
				'url'         => '',
				'method'      => 'POST',
				'auth_header' => true,
				'args'        => array(),
				'headers'     => array(
					'content-type' => 'application/json',
				),
			)
		);

		$body = '';

		try {

			self::get_token();

			if ( $request['auth_header'] ) {
				$request['headers']['Authorization'] = 'Bearer ' . self::$token['access_token'];
			}

			$request_args = array(
				'method'    => $request['method'],
				'headers'   => $request['headers'],
				'sslverify' => false,
				'body'      => $request['args'],
			);

			// Log request.
			Logger::log_request( $request['url'], $request_args, 'debug' );

			$response = wp_remote_request( $request['url'], $request_args );

			if ( is_wp_error( $response ) ) {
				$error_message = ( is_wp_error( $response ) ) ? $response->get_error_message() : $response['body'];

				throw new \Exception( $error_message, 1 );
			}

			// Log response.
			Logger::log_response( $response, 'debug' );

			$body = self::parse_response( $response );

		} catch ( \Exception $e ) {

			throw new \Exception( $e->getMessage(), $e->getCode() );
		}

		$response_code = absint( wp_remote_retrieve_response_code( $response ) );

		if ( 401 === $response_code ) {
			throw new \Exception( __( 'Reconnect to your Pinterest account', 'pinterest-for-woocommerce' ), 401 );
		}

		if ( ! in_array( absint( $response_code ), array( 200, 201, 204 ), true ) ) {

			$message = '';
			if ( ! empty( $body[0]->message ) ) {
				$message = $body[0]->message;
			}
			if ( ! empty( $body['error_description'] ) ) {
				$message = $body['error_description'];
			}

			/* Translators: Additional message */
			throw new \Exception( sprintf( __( 'Error Processing Request%s', 'pinterest-for-woocommerce' ), ( empty( $message ) ? '' : ': ' . $message ) ), $response_code );
		}

		return $body;
	}


	/**
	 * Gets and caches the Token from the plugin's settings.
	 *
	 * @return mixed
	 */
	public static function get_token() {
		if ( is_null( self::$token ) ) {
			self::$token = Pinterest_For_Woocommerce()::get_token();
		}

		return self::$token;
	}


	/**
	 * Return array with response body
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $response The response to parse.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP exception.
	 */
	protected static function parse_response( $response ) {

		if ( ! array_key_exists( 'body', (array) $response ) ) {
			throw new \Exception( __( 'Empty body', 'pinterest-for-woocommerce' ), 204 );
		}

		$body = (array) json_decode( $response['body'] );

		return $body;
	}


	/**
	 * Request the verification data from the API and return the response.
	 *
	 * @return mixed
	 */
	public static function domain_verification_data() {
		$response = self::make_request( 'domains/verification', 'GET' );
		return $response;
	}


	/**
	 * Trigger the (realtime) verification process using the API and return the response.
	 *
	 * @param boolean $allow_multiple Parameter passed to the API.
	 * @return mixed
	 */
	public static function trigger_verification( $allow_multiple = true ) {

		$domain      = wp_parse_url( site_url(), PHP_URL_HOST );
		$request_url = 'domains/' . $domain . '/verification/metatag/realtime/';

		if ( $allow_multiple ) {
			$request_url = add_query_arg( 'can_claim_multiple', 'true', $request_url );
		}

		$response = self::make_request( $request_url, 'POST' );
		return $response;
	}


	/**
	 * Request the account data from the API and return the response.
	 *
	 * @return mixed
	 */
	public static function get_account_info() {
		$response = self::make_request( 'users/me', 'GET' );
		return $response;
	}


	/**
	 * Request the account data from the API and return the response.
	 *
	 * @param string $merchant_id The ID of the merchant for the request.
	 *
	 * @return mixed
	 */
	public static function get_merchant( $merchant_id ) {
		$response = self::make_request( 'commerce/product_pin_merchants/' . $merchant_id . '/', 'GET' );
		return $response;
	}


	/**
	 * Creates a merchant for the authenticated user or returns the existing one.
	 *
	 * @param array $args The arguments to be passed to the API request.
	 *
	 * @return mixed
	 */
	public static function maybe_create_merchant( $args ) {

		$merchant_name = apply_filters( 'pinterest_for_woocommerce_default_merchant_name', esc_html__( 'Auto Created by Pinterest For WooCommerce', 'pinterest-for-woocommerce' ) );

		$args = wp_parse_args(
			$args,
			array(
				'display_name'                      => $merchant_name,
				'return_merchant_if_already_exists' => true,
			)
		);

		$response = self::make_request(
			add_query_arg( $args, 'commerce/product_pin_merchants/' ),
			'POST'
		);

		return $response;
	}


	/**
	 * Adds the merchant's feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param array  $args        The arguments to be passed to the API request.
	 *
	 * @return mixed
	 */
	public static function add_merchant_feed( $merchant_id, $args ) {

		$response = self::make_request(
			add_query_arg( $args, 'commerce/product_pin_merchants/' . $merchant_id . '/feed/' ),
			'POST'
		);

		return $response;
	}


	/**
	 * Updates the merchant's feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param string $feed_id     The ID of the feed to be updated.
	 * @param array  $args        The arguments to be passed to the API request.
	 *
	 * @return mixed
	 */
	public static function update_merchant_feed( $merchant_id, $feed_id, $args ) {

		$response = self::make_request(
			add_query_arg( $args, 'commerce/product_pin_merchants/' . $merchant_id . '/feed/' . $feed_id . '/' ),
			'PUT'
		);

		return $response;
	}


	/**
	 * Request the feed report data from the API and return the response.
	 *
	 * @param string $merchant_id The ID of the merchant for the request.
	 *
	 * @return mixed
	 */
	public static function get_feed_report( $merchant_id ) {
		$response = self::make_request( 'catalogs/datasource/feed_report/' . $merchant_id . '/', 'GET' );
		return $response;
	}

}
