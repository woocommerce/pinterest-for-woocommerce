<?php
/**
 * Parse & return the Pinterest Feed issues
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0s
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest;
use Automattic\WooCommerce\Pinterest\FeedRegistration;

use Automattic\WooCommerce\Pinterest\FeedStatusService;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint returning the product-level issues of the XML feed.
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
	 * Main endpoint callback.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return array[]|WP_REST_Response
	 */
	public function get_feed_issues( WP_REST_Request $request ) {
		$ad_account_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );
		$feed_id       = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( ! Pinterest\ProductSync::is_product_sync_enabled() || ! $feed_id || ! $ad_account_id ) {
			return array( 'lines' => array() );
		}

		$results = Pinterest\Feeds::get_feed_recent_processing_results( $feed_id );
		if ( empty( $results ) ) {
			return array( 'lines' => array() );
		}

		$paged    = $request->has_param( 'paged' ) ? (int) $request->get_param( 'paged' ) : 1;
		$per_page = $request->has_param( 'per_page' ) ? (int) $request->get_param( 'per_page' ) : 25;

		// Retrieve up to 250 feed item issues to ensure we can sort them by status before applying pagination.
		$feed_item_details = Pinterest\Feeds::get_feed_processing_result_items_issues( $results['id'], 250 );

		$lines = array_reduce( $feed_item_details, array( __CLASS__, 'prepare_issue_lines' ), array() );
		array_multisort( $lines, SORT_ASC, array_column( $lines, 'status' ) );
		$current_page_lines = array_slice( $lines, ( $paged - 1 ) * $per_page, $per_page );
		$response           = new WP_REST_Response(
			array(
				'lines'      => $current_page_lines,
				'total_rows' => count( $lines ),
			)
		);

		$response->header( 'X-WP-Total', count( $lines ) );
		$response->header( 'X-WP-TotalPages', ceil( count( $lines ) / $per_page ) );

		return $response;
	}

	/**
	 * Add product specific data to each line.
	 *
	 * @param array $acc  The accumulator array.
	 * @param array $item The array containing each col value for the line.
	 * @return array
	 *
	 * @since 1.4.0
	 */
	private static function prepare_issue_lines( array $acc, array $item ): array {

		$product      = wc_get_product( $item['item_id'] ?? '' );
		$edit_link    = '';
		$product_name = esc_html__( 'Invalid product', 'pinterest-for-woocommerce' );

		if ( $product ) {
			$product_name = $product->get_name();
		}

		if ( $product && $product->get_parent_id() ) {
			$product_name .= ' ' . esc_html__( '(Variation)', 'pinterest-for-woocommerce' );
			$edit_link     = get_edit_post_link( $product->get_parent_id(), 'not_display' ); // get_edit_post_link() will return '&' instead of  '&amp;' for anything other than the 'display' context.
		}

		$edit_link = empty( $edit_link ) && $product ? get_edit_post_link( $product->get_id(), 'not_display' ) : $edit_link; // get_edit_post_link() will return '&' instead of  '&amp;' for anything other than the 'display' context.

		foreach ( $item['errors'] as $key => $error ) {
			$description = FeedStatusService::ERROR_MESSAGES[ $key ] ?? $key;
			$acc[]       = array(
				'status'            => 'error',
				'product_name'      => $product_name,
				'product_edit_link' => $edit_link,
				'issue_description' => "{$description}: {$error['attribute_name']} - {$error['provided_value']}",
			);
		}

		foreach ( $item['warnings'] as $key => $warning ) {
			$description = FeedStatusService::ERROR_MESSAGES[ $key ] ?? $key;
			$acc[]       = array(
				'status'            => 'warning',
				'product_name'      => $product_name,
				'product_edit_link' => $edit_link,
				'issue_description' => "{$description}: {$warning['attribute_name']} - {$warning['provided_value']}",
			);
		}

		return $acc;
	}
}
