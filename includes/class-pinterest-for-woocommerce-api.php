<?php
/**
 * Pinterest API
 *
 * @class       Pinterest4WooCommerce_API
 * @version     1.0.0
 * @package     Pinterest_For_WordPress/Classes/
 * @category    Class
 * @author      WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pinterest_For_Woocommerce_API {

	protected static $instance      = null;
	protected static $log_file_name = PINTEREST4WOOCOMMERCE_LOG_PREFIX;
	protected static $log_prefix    = '';
	protected static $token;

	public function __construct() {
		self::set_token();
	}

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function set_token() {
		self::$token = Pinterest4WooCommerce()::get_token();
	}

	/**
	 * API requests wrapper
	 * If the response code is 401 and refresh_token exists,
	 * will try to refresh the token and make the request again.
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
	 * @param array $request (See above)
	 *
	 * @return array
	 */
	public static function make_request( $request ) {

		// 1º Request
		try {
			return self::handle_request( $request );
		} catch ( \Exception $e ) {

			$title        = __( 'Couldn\'t perform request', 'pinterest-for-woocommerce' );
			$post_message = __( 'Please try reconnecting with Pinterest.', 'pinterest-for-woocommerce' );

			self::log( 'error', sprintf( '%1$s, %2$s', $title, $post_message ) );

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
	 * @param array $request (See above)
	 *
	 * @return array
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

			if ( $request['auth_header'] ) {
				$request['headers']['Authorization'] = 'Bearer ' . self::$token['access_token'];
			}

			$request_args = array(
				'method'    => $request['method'],
				'headers'   => $request['headers'],
				'sslverify' => false,
				'body'      => $request['args'],
			);

			self::log(
				'request',
				wp_json_encode(
					array(
						'url'  => $request['url'],
						'args' => $request['args'],
					)
				)
			);

			$response = wp_remote_request( $request['url'], $request_args );

			if ( is_wp_error( $response ) ) {
				self::log( 'response', sprintf( 'Error %s', $response->get_error_message() ) );
				throw new \Exception( $response->get_error_message(), 1 );
			}

			self::log( 'response', wp_json_encode( $response ) );

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

			// translators: Additional message
			throw new \Exception( sprintf( __( 'Error Processing Request%s', 'pinterest-for-woocommerce' ), ( empty( $message ) ? '' : ': ' . $message ) ), $response_code );
		}

		return $body;
	}


	public static function log_token( $token ) {

		// Log response without exposing the sensitive data
		$obody                  = $token;
		$obody['access_token']  = empty( $obody['access_token'] ) ? '' : '--HIDDEN(' . strlen( $obody['access_token'] ) . ')--';
		$obody['refresh_token'] = empty( $obody['refresh_token'] ) ? '' : '--HIDDEN(' . strlen( $obody['refresh_token'] ) . ')--';
		$obody['signature']     = empty( $obody['refresh_token'] ) ? '' : '--HIDDEN(' . strlen( $obody['signature'] ) . ')--';
		self::log( 'response', wp_json_encode( $obody ) );
	}


	/**
	 * Log data
	 *
	 * @since 1.0.0
	 *
	 * @param string $prefix
	 *
	 * @param array $data
	 */
	public static function log( $prefix, $data ) {

		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		wc_get_logger()->add(
			self::$log_file_name,
			self::$log_prefix . ( empty( self::$log_prefix ) || empty( $prefix ) ? '' : ' - ' ) . strtoupper( $prefix ) . ( empty( self::$log_prefix ) && empty( $prefix ) ? '' : ' => ' ) . $data
		);
	}


	/**
	 * Return array with response body
	 *
	 * @since 1.0.0
	 *
	 * @param mixed
	 *
	 * @return array
	 */
	protected static function parse_response( $response ) {

		if ( ! array_key_exists( 'body', (array) $response ) ) {
			throw new \Exception( __( 'Empty body', 'pinterest-for-woocommerce' ), 204 );
		}

		$body = (array) json_decode( $response['body'] );

		return $body;
	}
}

// phpcs:ignore WordPress.NamingConventions.ValidFunctionName
function Pinterest4WooCommerceAPI() {
	return Pinterest_For_Woocommerce_API::instance();
}

// Global for backwards compatibility.
$GLOBALS['pinterest_for_woocommerce_api'] = Pinterest4WooCommerceAPI();
