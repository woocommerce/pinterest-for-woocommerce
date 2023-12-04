<?php
/**
 * Service class to handle & return Pinterest Feed Status
 *
 * @package     Automattic\WooCommerce\Pinterest
 * @version     1.3.0
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
	 * The error codes that are related to the feed itself, and not a specific product.
	 * Source: https://help.pinterest.com/en/business/article/data-source-ingestion
	 *
	 * @var array
	 */
	const GLOBAL_ERROR_CODES = array(
		'3',
		'100',
		'101',
		'102',
		'103',
		'129',
		'138',
		'139',
		'140',
		'143',
		'150',
		'152',
		'155',
	);

	const FEED_STATUS_NOT_REGISTERED = 'not_registered';

	const FEED_STATUS_ERROR_FETCHING_FEED = 'error_fetching_feed';

	const FEED_STATUS_INACTIVE = 'inactive';

	const FEED_STATUS_ACTIVE = 'active';

	const FEED_STATUS_DELETED = 'deleted';

	/**
	 * Get the feed registration status.
	 *
	 * @return string The feed registration state. Possible values:
	 *                - not_registered: Feed is not yet configured on Pinterest.
	 *                - error_fetching_feed: Could not get feed info from Pinterest.
	 *                - inactive: The feed is registered but inactive at Pinterest.
	 *                - active: The feed is registered and active at Pinterest.
	 *                - deleted: The feed is registered but marked as deleted at Pinterest.
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed_status(): string {
		$feed_id = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( empty( $feed_id ) ) {
			return static::FEED_STATUS_NOT_REGISTERED;
		}

		try {
			$feed = Feeds::get_feed( $feed_id );

			if ( empty( $feed ) ) {
				return static::FEED_STATUS_NOT_REGISTERED;
			}

			$is_deleted = Feeds::FEED_STATUS_DELETED === ( $feed['status'] ?? '' );
			$is_paused  = Feeds::FEED_STATUS_INACTIVE === ( $feed['status'] ?? '' );

			if ( $is_deleted ) {
				return static::FEED_STATUS_DELETED;
			}

			if ( $is_paused ) {
				return static::FEED_STATUS_INACTIVE;
			}

			return static::FEED_STATUS_ACTIVE;
		} catch ( PinterestApiException $e ) {
			return static::FEED_STATUS_ERROR_FETCHING_FEED;
		}
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
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		$feed_id       = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( empty( $ad_account_id ) || empty( $feed_id ) ) {
			throw new Exception( 'not_registered' );
		}

		try {
			try {
				$feed_results = Feeds::get_feed_recent_processing_results( $feed_id );
			} catch ( Exception $e ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( empty( $feed_results ) ) {
				throw new Exception( 'no_workflows' );
			}

			$status = strtolower( $feed_results['status'] ?? '' );
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
	 * Gets the overview totals from the given processing results array.
	 *
	 * @param   array $processing_results The processing results array.
	 * @return  array A multidimensional array of numbers indicating the following stats about the workflow:
	 *                  - total: The total number of products in the feed.
	 *                  - not_synced: The number of products not synced to Pinterest.
	 *                  - warnings: The number of warnings.
	 *                  - errors: The number of errors.
	 *
	 * @since x.x.x
	 */
	public static function get_processing_result_overview_stats(array $processing_results ): array {
		$sums = array(
			'errors'   => 0,
			'warnings' => 0,
		);

		$sums['errors'] += array_sum( $processing_results['ingestion_details']['errors'] );
		$sums['errors'] += array_sum( $processing_results['validation_details']['errors'] );

		$sums['warnings'] += array_sum( $processing_results['ingestion_details']['warnings'] );
		$sums['warnings'] += array_sum( $processing_results['validation_details']['warnings'] );

		$original = $processing_results['product_counts']['original'] ?? 0;
		$ingested = $processing_results['product_counts']['ingested'] ?? 0;

		return array(
			'total'      => $original,
			'not_synced' => $original - $ingested,
			'warnings'   => $sums['warnings'],
			'errors'     => $sums['errors'],
		);
	}

	/**
	 * Gets the global error code for the given processing results.
	 *
	 * @param array $processing_results Recent processing results array.
	 * @return string
	 *
	 * @since x.x.x
	 */
	public static function get_processing_results_global_error( array $processing_results ): string {
		$error_code = '';

		$codes_map = [];

		foreach ( $processing_results['validation_details']['errors'] as $error_code => $count ) {
			if ( in_array( $error_code, self::GLOBAL_ERROR_CODES, true ) ) {
				break;
			}
		}

		if ( $error_code ) {
			/* Translators: The error message as returned by the Pinterest API */
			return sprintf( esc_html__( 'Pinterest returned: %1$s', 'pinterest-for-woocommerce' ), $codes_map[ $error_code ] ?? '' );
		}

		return '';
	}

	/**
	 * Parses the given workflow and returns a string which contains the first error message found to be related to the feed globally and not a specific product.
	 *
	 * @param object $workflow The workflow object.
	 *
	 * @return string|null The error message or null if no global error was found.
	 */
	public static function get_global_error_from_workflow( object $workflow ): ?string {
		$error_code = null;
		$workflow   = (array) $workflow;
		foreach ( self::ERROR_CONTEXTS as $context ) {
			if ( ! empty( (array) $workflow[ $context ] ) ) {

				foreach ( (array) $workflow[ $context ] as $code => $count ) {
					if ( in_array( $code, self::GLOBAL_ERROR_CODES, true ) ) {
						$error_code = $code;
						break 2;
					}
				}
			}
		}

		if ( $error_code ) {
			$messages_map = Base::get_message_map();

			if ( 'success' === $messages_map['status'] && isset( $messages_map['data']->$error_code ) ) {
				/* Translators: The error message as returned by the Pinterest API */
				return sprintf( esc_html__( 'Pinterest returned: %1$s', 'pinterest-for-woocommerce' ), $messages_map['data']->$error_code );
			}
		}

		return null;
	}

}
