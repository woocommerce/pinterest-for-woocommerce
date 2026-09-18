<?php
/**
 * Pinterest for WooCommerce Logger
 *
 * @version     1.0.0
 * @package     Pinterest_For_WooCommerce/API
 */

namespace Automattic\WooCommerce\Pinterest;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class responsible for logging stuff
 */
class Logger {

	/**
	 * The single instance of the class.
	 *
	 * @var \WC_Logger
	 * @since 1.0.0
	 */
	public static $logger;

	/**
	 * The log_file_name.
	 *
	 * @var string
	 */
	protected static $log_file_name = \PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX;

	/**
	 * Always log errors, Logging of debug messages can be disabled via a filter.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level   The level/context of the message.
	 * @param string $feature Used to direct logs to a separate file.
	 * @param bool   $force   Used to bypass system settings and force the logs.
	 *
	 * @return void
	 */
	public static function log( $message, $level = 'debug', $feature = null, $force = false ): void {

		$allow_logging = true;
		if ( 'debug' === $level ) {
			$allow_logging = Pinterest_For_WooCommerce()::get_setting( 'enable_debug_logging' );
		}

		if ( $force ) {
			$allow_logging = true;
		}

		if ( empty( $allow_logging ) || ! function_exists( 'wc_get_logger' ) ) {
			return;
		}

		if ( ! self::$logger ) {
			self::$logger = wc_get_logger();
		}

		$handler = array( 'source' => self::$log_file_name . ( is_null( $feature ) ? '' : '-' . $feature ) );

		self::$logger->log( $level, $message, $handler );
	}

	/**
	 * Helper for Logging API requests.
	 *
	 * @param string   $url   The URL of the request.
	 * @param string[] $args  The Arguments of the request.
	 * @param string   $level The default level/context of the message to be logged.
	 *
	 * @return void
	 */
	public static function log_request( $url, $args, $level = 'debug' ) {
		$method = $args['method'] ?? 'POST';
		// Query strings, headers and bodies can contain credentials or customer data.
		self::log( "{$method} Request: " . self::get_url_path( $url ) . "\n", $level );
	}

	/**
	 * Returns the path of a URL for logging, as query strings can contain credentials or customer data.
	 *
	 * @param string $url The URL.
	 *
	 * @return string
	 */
	public static function get_url_path( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );
		return is_string( $path ) && '' !== $path ? $path : '(unknown path)';
	}

	/**
	 *  Helper for Logging API responses.
	 *
	 * @param array|WP_Error $response The body of the response.
	 * @param string         $level    The default level/context of the message to be logged.
	 *
	 * @return void
	 */
	public static function log_response( $response, $level = 'debug' ) {
		if ( is_wp_error( $response ) ) {
			$level = 'error';
			$data  = $response->get_error_code() . ': ' . $response->get_error_message();
		} else {
			$data = 'Status: ' . wp_remote_retrieve_response_code( $response ) . ' ' . wp_remote_retrieve_response_message( $response );
		}

		self::log( 'Response: ' . "\n\n" . $data . "\n", $level );
	}
}

