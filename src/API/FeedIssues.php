<?php
/**
 * Parse & return the Pinterest Feed issues
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0s
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint returning the product-level issues of the XML Feed.
 */
class FeedIssues extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'feed_issues';
		$this->endpoint_callback = 'get_feed_issues';
		$this->methods           = WP_REST_Server::READABLE;

		$this->register_routes();
	}


	/**
	 * Authenticate request
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return boolean
	 */
	public function permissions_check( WP_REST_Request $request ) {
		return current_user_can( 'manage_options' );
	}


	/**
	 * Get the advertisers assigned to the authorized Pinterest account.
	 *
	 * @return array
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_feed_issues( WP_REST_Request $request ) {

		try {

			$issues_file_url = $request->has_param( 'feed_issues_url' ) ? $request->get_param( 'feed_issues_url' ) : self::get_feed_issues_data_file();

			if ( empty( $issues_file_url ) ) {
				return array( 'lines' => array() );
			}

			// Get file.
			$issues_file = self::get_remote_file( $issues_file_url );

			if ( empty( $issues_file ) ) {
				throw new \Exception( esc_html__( 'Error downloading Feed Issues file from Pinterest.', 'pinterest-for-woocommerce' ), 400 );
			}

			$lines = self::parse_lines( $issues_file, 0, 10 ); // TODO: pagination?

			if ( ! empty( $lines ) ) {
				$lines = array_map( array( __CLASS__, 'add_product_data' ), $lines );
			}

			return array( 'lines' => $lines );

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Could not get current feed\'s issues. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_advertisers_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}


	/**
	 * Add product specific data to each line.
	 *
	 * @param array $line The array contaning each col value for the line.
	 *
	 * @return array
	 */
	private static function add_product_data( $line ) {

		$product = wc_get_product( $line['ItemId'] );

		$line['product_name']      = $product->get_name();
		$line['product_edit_link'] = get_edit_post_link( $product->get_id() );

		return $line;
	}



	/**
	 * Reads the file given in $issues_file, parses and returns the content of lines
	 * from $start_line to $end_line as array items.
	 *
	 * @param string  $issues_file The file path to read from.
	 * @param int     $start_line  The first line to return.
	 * @param int     $end_line    The last line to return.
	 * @param boolean $has_keys    Whether or not the 1st line of the file holds the header keys.
	 *
	 * @return array
	 */
	private static function parse_lines( $issues_file, $start_line, $end_line, $has_keys = true ) {

		$lines      = array();
		$keys       = '';
		$delim      = "\t";
		$start_line = $has_keys ? $start_line + 1 : $start_line;
		$end_line   = $has_keys ? $end_line + 1 : $end_line;

		try {
			$spl = new \SplFileObject( $issues_file );

			if ( $has_keys ) {
				$spl->seek( 0 );
				$keys = $spl->current();
			}

			for ( $i = $start_line; $i <= $end_line; $i++ ) {
				$spl->seek( $i );
				$lines[] = $spl->current();
			}
		} catch ( \Throwable $th ) {

			// Fallback method.
			global $wp_filesystem;

			require_once ABSPATH . '/wp-admin/includes/file.php';

			if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
				$creds = request_filesystem_credentials( site_url() );
				WP_Filesystem( $creds );
			}

			$all_lines = $wp_filesystem->get_contents_array( $issues_file );

			if ( $has_keys ) {
				$keys = $all_lines[0];
			}

			$lines = array_slice( $all_lines, $start_line, ( $end_line - $start_line ) );
		}

		if ( ! empty( $keys ) ) {
			$keys = array_map( 'trim', explode( $delim, $keys ) );
		}

		foreach ( $lines as &$line ) {
			$line = array_combine( $keys, array_map( 'trim', explode( $delim, $line ) ) );
		}

		return $lines;
	}


	/**
	 * Get the file from $url and save it to a temporary location.
	 * Return the path of the temporary file.
	 *
	 * @param string $url The URL to fetch the file from.
	 *
	 * @return string|boolean
	 */
	private static function get_remote_file( $url ) {

		// TODO: cache based on etag?

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$target_file = wp_tempnam();

		$response = wp_remote_get(
			$url,
			array(
				'stream'   => true,
				'filename' => $target_file,
				'timeout'  => 300,
			)
		);

		// TODO: cleanup / delete file?

		return $response && ! is_wp_error( $response ) ? $target_file : false;

	}

	/**
	 * Get the URL of feed Issues file for the latest Workflow of the
	 * active feed, for the Merchant saved in the settings.
	 *
	 * @return string
	 *
	 * @throws \Exception PHP Exception.
	 */
	private static function get_feed_issues_data_file() {

		$merchant_id = Pinterest_For_Woocommerce()::get_setting( 'merchant_id' );
		$feed_report = Base::get_feed_report( $merchant_id );

		if ( 'success' !== $feed_report['status'] ) {
			throw new \Exception( esc_html__( 'Could not get feed report from Pinterest.', 'pinterest-for-woocommerce' ), 400 );
		}

		if ( ! property_exists( $feed_report['data'], 'workflows' ) || ! is_array( $feed_report['data']->workflows ) || empty( $feed_report['data']->workflows ) ) {
			return false;
		}

		// Get latest workflow.
		usort(
			$feed_report['data']->workflows,
			function ( $a, $b ) {
				return $b->created_at - $a->created_at;
			}
		);

		$workflow = reset( $feed_report['data']->workflows );

		return $workflow->s3_validation_url;
	}
}
