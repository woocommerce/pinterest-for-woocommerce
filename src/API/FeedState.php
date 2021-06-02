<?php
/**
 * Handle & return Pinterest Feed State
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest as Pinterest;

use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint returning the current state of the XML Feed.
 */
class FeedState extends VendorAPI {

	/**
	 * Initialize class
	 */
	public function __construct() {

		$this->base              = 'feed_state';
		$this->endpoint_callback = 'get_feed_state';
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
	 * @throws \Exception PHP Exception.
	 */
	public function get_feed_state() {

		try {

			$result = array();

			$result['local_state'] = Pinterest\ProductSync::feed_job_status();

			$merchant_id = Pinterest_For_Woocommerce()::get_setting( 'merchant_id' );

			$merchant = Base::get_merchant( $merchant_id );

			if ( 'success' !== $merchant['status'] ) {
				throw new \Exception( esc_html__( 'Response error', 'pinterest-for-woocommerce' ), 400 );
			}

			if ( 'ACTIVE' === $merchant['data']->product_pin_feed_profile->feed_status &&
				'approved' === $merchant['data']->product_pin_approval_status
			) {
				$result['status']  = 'OK';
				$result['message'] = esc_html__( 'Product Feed configured for Ingestion on Pinterest', 'pinterest-for-woocommerce' );
			} elseif ( 'ACTIVE' === $merchant['data']->product_pin_feed_profile->feed_status &&
				'approved' !== $merchant['data']->product_pin_approval_status
			) {
				$result['status']  = 'PENDING_APPROVAL';
				$result['message'] = esc_html__( 'Product Feed pending approval on Pinterest.', 'pinterest-for-woocommerce' );
			} else {
				$result['status']  = 'NOT_CONFIGURED';
				$result['message'] = esc_html__( 'Product Feed not yet configured on Pinterest.', 'pinterest-for-woocommerce' );
			}

			if ( isset( $merchant['data']->product_pin_feed_profile ) &&
				property_exists( $merchant['data']->product_pin_feed_profile, 'id' ) &&
				property_exists( $merchant['data']->product_pin_feed_profile, 'display_name' ) &&
				property_exists( $merchant['data']->product_pin_feed_profile, 'location_config' )
			) {
				$result['feed_id']   = $merchant['data']->product_pin_feed_profile->id;
				$result['feed_name'] = $merchant['data']->product_pin_feed_profile->display_name;
				$result['feed_url']  = $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location;
			}

			// If not registered.
			if ( 'OK' !== $result['status'] ) {
				return $result;
			}

			// Check validation?

			// Get feed ingestion status.
			$feed_report = Base::get_feed_report( $merchant_id );

			if ( 'success' !== $feed_report['status'] ) {
				throw new \Exception( esc_html__( 'Response error when trying to get feed report from Pinterest.', 'pinterest-for-woocommerce' ), 400 );

			}

			if ( ! property_exists( $feed_report['data'], 'workflows' ) || ! is_array( $feed_report['data']->workflows ) || empty( $feed_report['data']->workflows ) ) {
				throw new \Exception( esc_html__( 'Response error. Feed report contains no feed workflow.', 'pinterest-for-woocommerce' ), 400 );
			}

			// Get latest workflow.
			usort(
				$feed_report['data']->workflows,
				function ( $a, $b ) {
					return $b->created_at - $a->created_at;
				}
			);

			$workflow = reset( $feed_report['data']->workflows );

			$result['workflow'] = array(
				'created_at'                => $workflow->created_at,
				'workflow_status'           => $workflow->workflow_status,
				'product_count'             => (int) $workflow->product_count,
				'original_product_count'    => (int) $workflow->original_product_count,
				's3_validation_url'         => $workflow->s3_validation_url,
				'validation_stats_warnings' => (array) $workflow->validation_stats_warnings,
				'validation_stats_errors'   => (array) $workflow->validation_stats_errors,
				'ingestion_stats_warnings'  => (array) $workflow->ingestion_stats_warnings,
				'ingestion_stats_errors'    => (array) $workflow->ingestion_stats_errors,
			);

			$result['overview'] = $this->get_totals_from_workflow( $result['workflow'] );

			return $result;

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Error getting feed\'s state. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_state_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}


	/**
	 * Gets the overview totals from the given workflow array.
	 *
	 * @param array $workflow The workflow array.
	 *
	 * @return array
	 */
	private function get_totals_from_workflow( $workflow ) {

		$warnings = 0;
		$errors   = 0;

		if ( ! empty( $workflow['validation_stats_warnings'] ) ) {
			$warnings += array_sum( $workflow['validation_stats_warnings'] );
		}

		if ( ! empty( $workflow['ingestion_stats_warnings'] ) ) {
			$warnings += array_sum( $workflow['ingestion_stats_warnings'] );
		}

		if ( ! empty( $workflow['validation_stats_errors'] ) ) {
			$errors += array_sum( $workflow['validation_stats_errors'] );
		}

		if ( ! empty( $workflow['ingestion_stats_errors'] ) ) {
			$errors += array_sum( $workflow['ingestion_stats_errors'] );
		}

		return array(
			'total'      => $workflow['original_product_count'],
			'not_synced' => $workflow['original_product_count'] - $workflow['product_count'],
			'warnings'   => $warnings,
			'errors'     => $errors,
		);

	}
}
