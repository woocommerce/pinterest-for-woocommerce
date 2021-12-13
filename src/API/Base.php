<?php
/**
 * Pinterest API
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     1.0.0
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest as Pinterest;
use Automattic\WooCommerce\Pinterest\Logger as Logger;
use Automattic\WooCommerce\Pinterest\PinterestApiException as ApiException;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base API Methods
 */
class Base {

	const API_DOMAIN      = 'https://api.pinterest.com';
	const API_VERSION     = 3;
	const API_ADS_VERSION = 4;

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
	 * @param string  $endpoint        the endpoint to perform the request on.
	 * @param string  $method          eg, POST, GET, PUT etc.
	 * @param array   $payload         Payload to be sent on the request's body.
	 * @param string  $api             The specific Endpoints subset.
	 * @param int     $cache_expiry    When set, enables caching on the request and the value is used as the cache's TTL (in seconds).
	 * @param boolean $is_json_payload Specify if the request body is JSON formatted.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP exception.
	 */
	public static function make_request( $endpoint, $method = 'POST', $payload = array(), $api = '', $cache_expiry = false, $is_json_payload = false ) {

		if ( ! empty( $cache_expiry ) ) {
			$cache_key = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_request_' . md5( $endpoint . $method . wp_json_encode( $payload ) . $api );
			$cache     = get_transient( $cache_key );

			if ( $cache ) {
				return $cache;
			}
		}

		try {
			$api         = empty( $api ) ? '' : trailingslashit( $api );
			$api_version = 'ads/' === $api ? self::API_ADS_VERSION : self::API_VERSION;

			$request = array(
				'url'    => self::API_DOMAIN . '/' . $api . 'v' . $api_version . '/' . $endpoint,
				'method' => $method,
				'json'   => $is_json_payload,
				'args'   => $payload,
			);

			$response = self::handle_request( $request );

			if ( ! empty( $cache_expiry ) ) {
				set_transient( $cache_key, $response, $cache_expiry );
			}

			return $response;
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
	 * @throws ApiException PHP exception.
	 */
	public static function handle_request( $request ) {

		$request = wp_parse_args(
			$request,
			array(
				'url'         => '',
				'method'      => 'POST',
				'auth_header' => true,
				'args'        => array(),
			)
		);

		$body = '';

		try {

			self::get_token();

			if ( $request['auth_header'] ) {
				$request['headers']['Authorization'] = 'Bearer ' . self::$token['access_token'];
			}

			if ( $request['json'] ) {
				$request['headers']['Content-Type'] = 'application/json';
			}

			$request_args = array(
				'method'    => $request['method'],
				'headers'   => $request['headers'],
				'sslverify' => false,
				'body'      => $request['args'],
				'timeout'   => 15,
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
			if ( ! empty( $body['message'] ) ) {
				$message = $body['message'];
			}
			if ( ! empty( $body['error_description'] ) ) {
				$message = $body['error_description'];
			}

			/* Translators: Additional message */
			throw new ApiException(
				array(
					'message'       => $message,
					'response_body' => $body,
				),
				$response_code
			);
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

		return (array) json_decode( $response['body'] );
	}


	/**
	 * Disconnect the merchant from the Pinterest platform.
	 *
	 * @return mixed
	 */
	public static function disconnect_merchant() {
		return self::make_request( 'catalogs/partner/disconnect', 'POST' );
	}

	/**
	 * Request the verification data from the API and return the response.
	 *
	 * @return mixed
	 */
	public static function domain_verification_data() {
		return self::make_request( 'domains/verification', 'GET' );
	}


	/**
	 * Trigger the (realtime) verification process using the API and return the response.
	 *
	 * @param boolean $allow_multiple Parameter passed to the API.
	 * @return mixed
	 */
	public static function trigger_verification( $allow_multiple = true ) {

		$domain      = wp_parse_url( home_url(), PHP_URL_HOST );
		$request_url = 'domains/' . $domain . '/verification/metatag/realtime/';

		if ( $allow_multiple ) {
			$request_url = add_query_arg( 'can_claim_multiple', 'true', $request_url );
		}

		return self::make_request( $request_url, 'POST' );
	}


	/**
	 * Request the account data from the API and return the response.
	 *
	 * @return mixed
	 */
	public static function get_account_info() {
		return self::make_request( 'users/me', 'GET' );
	}


	/**
	 * Get the linked business accounts from the API.
	 *
	 * @return mixed
	 */
	public static function get_linked_businesses() {
		return self::make_request( 'users/me/businesses', 'GET' );
	}


	/**
	 * Create an advertiser given the accepted TOS terms ID.
	 *
	 * @param string $tos_id The ID of the accepted TOS terms.
	 *
	 * @return mixed
	 */
	public static function create_advertiser( $tos_id ) {

		$advertiser_name = apply_filters( 'pinterest_for_woocommerce_default_advertiser_name', esc_html__( 'Auto-created by Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) );

		return self::make_request(
			'advertisers/',
			'POST',
			array(
				'tos_id' => $tos_id,
				'name'   => $advertiser_name,
			),
			'ads'
		);
	}


	/**
	 * Connect the advertiser with the platform.
	 *
	 * @param string $advertiser_id The advertiser ID.
	 * @param string $tag_id        The tag ID.
	 *
	 * @return mixed
	 */
	public static function connect_advertiser( $advertiser_id, $tag_id ) {
		return self::make_request(
			'advertisers/' . $advertiser_id . '/connect/',
			'POST',
			wp_json_encode(
				array(
					'tag_id' => $tag_id,
				)
			),
			'ads',
			false,
			true
		);
	}


	/**
	 * Disconnect advertiser from the platform.
	 *
	 * @param string $advertiser_id The advertiser ID.
	 * @param string $tag_id        The tag ID.
	 *
	 * @return mixed
	 */
	public static function disonnect_advertiser( $advertiser_id, $tag_id ) {
		return self::make_request(
			'advertisers/' . $advertiser_id . '/disconnect/',
			'POST',
			wp_json_encode(
				array(
					'tag_id' => $tag_id,
				)
			),
			'ads',
			false,
			true
		);
	}


	/**
	 * Get the advertiser object from the Pinterest API for the given User ID.
	 *
	 * @param string $pinterest_user the user to request the Advertiser for.
	 *
	 * @return mixed
	 */
	public static function get_advertisers( $pinterest_user = null ) {
		$pinterest_user = ! is_null( $pinterest_user ) ? $pinterest_user : Pinterest_For_Woocommerce()::get_account_id();
		return self::make_request( 'advertisers/?owner_user_id=' . $pinterest_user, 'GET', array(), 'ads' );
	}


	/**
	 * Get the advertiser's tracking tags.
	 *
	 * @param string $advertiser_id the advertiser_id to request the tags for.
	 *
	 * @return mixed
	 */
	public static function get_advertiser_tags( $advertiser_id ) {
		return self::make_request( 'advertisers/' . $advertiser_id . '/conversion_tags/', 'GET', array(), 'ads' );
	}


	/**
	 * Create a tag for the given advertiser.
	 *
	 * @param string $advertiser_id the advertiser_id to create a tag for.
	 *
	 * @return mixed
	 */
	public static function create_tag( $advertiser_id ) {

		$tag_name = apply_filters( 'pinterest_for_woocommerce_default_tag_name', esc_html__( 'Auto-created by Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) );

		return self::make_request(
			"advertisers/{$advertiser_id}/conversion_tags",
			'POST',
			array(
				'name' => $tag_name,
			),
			'ads'
		);
	}


	/**
	 * Update the tags configuration.
	 *
	 * @param string $tag_id The tag_id for which we want to update the configuration.
	 * @param array  $config The configuration to set.
	 *
	 * @return mixed
	 */
	public static function update_tag_config( $tag_id, $config = array() ) {

		if ( empty( $config ) ) {
			return false;
		}

		return self::make_request( 'tags/' . $tag_id . '/configs/', 'PUT', $config, 'ads' );
	}


	/**
	 * Request the account data from the API and return the response.
	 *
	 * @param string $merchant_id The ID of the merchant for the request.
	 *
	 * @return mixed
	 */
	public static function get_merchant( $merchant_id ) {
		return self::make_request( 'commerce/product_pin_merchants/' . $merchant_id . '/', 'GET' );
	}


	/**
	 * Creates a merchant for the authenticated user or updates the existing one.
	 *
	 * @return mixed
	 */
	public static function update_or_create_merchant() {

		$local_feed = Pinterest\ProductFeedStatus::get_local_feed();

		$merchant_name = apply_filters( 'pinterest_for_woocommerce_default_merchant_name', esc_html__( 'Auto-created by Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) );

		$args = array(
			'merchant_domains' => get_home_url(),
			'feed_location'    => $local_feed['feed_url'],
			'feed_format'      => 'XML',
			'country'          => Pinterest_For_Woocommerce()::get_base_country() ?? 'US',
			'locale'           => str_replace( '_', '-', determine_locale() ),
			'currency'         => get_woocommerce_currency(),
			'merchant_name'    => $merchant_name,
		);

		return self::make_request(
			'catalogs/partner/connect/',
			'POST',
			$args,
		);
	}


	/**
	 * Get a specific merchant feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function get_merchant_feed( $merchant_id, $feed_id ) {
		try {

			$feeds = self::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new \Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new \Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				if ( $feed_id === $feed_profile->id ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new \Exception( esc_html__( 'No feed found with the requested ID.', 'pinterest-for-woocommerce' ) );

		} catch ( \Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}
	}


	/**
	 * Get merchant's feed based on feed location
	 *
	 * @param string $merchant_id   The merchant ID.
	 * @param string $feed_location The feed full location.
	 *
	 * @return mixed
	 *
	 * @throws \Exception PHP Exception.
	 */
	public static function get_merchant_feed_by_location( $merchant_id, $feed_location ) {
		try {

			$feeds = self::get_merchant_feeds( $merchant_id );

			if ( 'success' !== $feeds['status'] ) {
				throw new \Exception( esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! is_array( $feeds['data'] ) ) {
				throw new \Exception( esc_html__( 'Wrong feed info.', 'pinterest-for-woocommerce' ) );
			}

			foreach ( $feeds['data'] as $feed_profile ) {

				if ( $feed_location === $feed_profile->location_config->full_feed_fetch_location ) {
					return $feed_profile;
				}
			}

			// No feed found.
			throw new \Exception( esc_html__( 'No feed found with the requested location.', 'pinterest-for-woocommerce' ) );

		} catch ( \Exception $e ) {

			Logger::log( $e->getMessage(), 'error' );

			throw $e;
		}
	}


	/**
	 * Get a merchant's feeds.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 *
	 * @return mixed
	 */
	public static function get_merchant_feeds( $merchant_id ) {
		return self::make_request(
			"catalogs/{$merchant_id}/feed_profiles/",
			'GET',
			array(),
			'',
			MINUTE_IN_SECONDS
		);
	}


	/**
	 * Get a specific merchant's feed using the given arguments.
	 *
	 * @param string $merchant_id The merchant ID the feed belongs to.
	 * @param string $feed_id     The ID of the feed.
	 *
	 * @return mixed
	 */
	public static function get_merchant_feed_report( $merchant_id, $feed_id ) {
		return self::make_request(
			"catalogs/datasource/feed_report/{$merchant_id}/",
			'GET',
			array(
				'feed_profile' => $feed_id,
			),
			'',
			MINUTE_IN_SECONDS
		);
	}


	/**
	 * Request the managed map representing all of the error, recommendation, and status messages for catalogs.
	 *
	 * @return mixed
	 */
	public static function get_message_map() {
		return self::make_request( 'catalogs/message_map', 'GET', array(), '', DAY_IN_SECONDS );
	}
}
