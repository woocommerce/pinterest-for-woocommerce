<?php
/**
 * Service class to handle & return Pinterest Feed Status
 *
 * @package     Automattic\WooCommerce\Pinterest
 * @version     x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling methods to return Pinterest Feed Status.
 */
class FeedStatusService {

	/**
	 * The error contexts to search for in the workflow responses.
	 *
	 * @var array
	 */
	const ERROR_CONTEXTS = array(
		'validation_stats_warnings',
		'ingestion_stats_warnings',
		'validation_stats_errors',
		'ingestion_stats_errors',
	);

	/**
	 * Get the feed registration status.
	 *
	 * @return string The feed registration state. Possible values:
	 *                - not_registered: Feed is not yet configured on Pinterest.
	 *                - error_fetching_merchant: Could not get merchant info.
	 *                - error_fetching_feed: Could not get feed info.
	 *                - inactive_feed: The feed is registered but inactive.
	 *                - approved: The feed is registered and approved.
	 *                - pending: Product feed pending approval on Pinterest.
	 *                - appeal_pending: Product feed pending approval on Pinterest.
	 *                - declined: The feed is registered but declined by Pinterest.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed_registration_status(): string {
		$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$feed_id     = FeedRegistration::get_locally_stored_registered_feed_id();

		try {
			if ( empty( $merchant_id ) || empty( $feed_id ) ) {
				throw new Exception( 'not_registered' );
			}

			$merchant = Base::get_merchant( $merchant_id );
			if ( 'success' !== $merchant['status'] ) {
				throw new Exception( 'error_fetching_merchant' );
			}

			try {
				$feed = Feeds::get_merchant_feed( $merchant_id, $feed_id );
			} catch ( Exception $e ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( ! $feed ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( 'ACTIVE' !== $feed->feed_status ) {
				throw new Exception( 'inactive_feed' );
			}

			$status = strtolower( $merchant['data']->product_pin_approval_status );
			if ( ! in_array( $status, array( 'approved', 'pending', 'appeal_pending', 'declined' ), true ) ) {
				throw new Exception( 'not_registered' );
			}
		} catch ( Exception $e ) {
			$status = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Get the sync process / feed ingestion status via Pinterest API.
	 *
	 * @return string The feed ingestion state. Possible values:
	 *                - not_registered: Feed is not registered with Pinterest.
	 *                - error_fetching_feed: Error when trying to get feed report from Pinterest.
	 *                - no_workflows: Feed report contains no feed workflow.
	 *                - completed: Feed automatically pulled by Pinterest / Feed ingestion completed.
	 *                - completed_early: Feed automatically pulled by Pinterest / Feed ingestion completed early.
	 *                - processing: Feed ingestion is processing.
	 *                - under_review: Feed is under review.
	 *                - queued_for_processing: Feed is queued for processing.
	 *                - failed: Feed ingestion failed.
	 *                - unknown: Unknown feed ingestion status in workflow (i.e. API returned an unknown status).
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed_sync_status(): string {
		$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$feed_id     = FeedRegistration::get_locally_stored_registered_feed_id();

		try {
			if ( empty( $merchant_id ) || empty( $feed_id ) ) {
				throw new Exception( 'not_registered' );
			}

			try {
				$workflow = Feeds::get_feed_latest_workflow( (string) $merchant_id, (string) $feed_id );
			} catch ( Exception $e ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( ! $workflow ) {
				throw new Exception( 'no_workflows' );
			}

			$status = strtolower( $workflow->workflow_status );
			if ( ! in_array(
				$status,
				array(
					'completed',
					'completed_early',
					'processing',
					'under_review',
					'queued_for_processing',
					'failed',
				),
				true
			) ) {
				throw new Exception( 'unknown' );
			}
		} catch ( Exception $e ) {
			$status = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Gets the overview totals from the given workflow array.
	 *
	 * @param object $workflow The workflow object.
	 *
	 * @return array A multidimensional array of numbers indicating the following stats about the workflow:
	 *               - total: The total number of products in the feed.
	 *               - not_synced: The number of products not synced to Pinterest.
	 *               - warnings: The number of warnings.
	 *               - errors: The number of errors.
	 */
	public static function get_workflow_overview_stats( object $workflow ): array {
		$sums     = array(
			'warnings' => 0,
			'errors'   => 0,
		);
		$workflow = (array) $workflow;
		foreach ( self::ERROR_CONTEXTS as $context ) {
			if ( ! empty( (array) $workflow[ $context ] ) ) {
				$what           = strpos( $context, 'errors' ) ? 'errors' : 'warnings';
				$sums[ $what ] += array_sum( (array) $workflow[ $context ] );
			}
		}

		return array(
			'total'      => $workflow['original_product_count'],
			'not_synced' => $workflow['original_product_count'] - $workflow['product_count'],
			'warnings'   => $sums['warnings'],
			'errors'     => $sums['errors'],
		);
	}

}
