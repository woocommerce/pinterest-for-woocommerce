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
	 * The error codes that are related to the feed itself,
	 * and not a specific product.
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

			if ( ! Pinterest\ProductSync::is_product_sync_enabled() ) {
				return array(
					'workflow' => array(
						array(
							'label'        => esc_html__( 'XML Feed', 'pinterest-for-woocommerce' ),
							'status'       => 'error',
							'status_label' => esc_html__( 'Feed is disabled.', 'pinterest-for-woocommerce' ),
							'extra_info'   => '',
						),
					),
					'overview' => array(
						'total'      => 0,
						'not_synced' => 0,
						'warnings'   => 0,
						'errors'     => 0,
					),
				);
			}

			$result = $this->add_local_feed_state( $result );
			$result = $this->add_feed_registration_state( $result );

			return $result;

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Error getting feed\'s state. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new \WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_state_error', $error_message, array( 'status' => $th->getCode() ) );
		}
	}


	/**
	 * Adds to the result variable an array with info about the status of the local
	 * feed generation process.
	 *
	 * @param array $result The result array to add values to.
	 *
	 * @return array
	 */
	private function add_local_feed_state( $result ) {

		$state      = Pinterest\ProductSync::feed_job_status();
		$extra_info = '';

		switch ( $state['status'] ) {
			case 'starting':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed generation is being initialized', 'pinterest-for-woocommerce' );
				break;

			case 'in_progress':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed generation in progress', 'pinterest-for-woocommerce' );
				$extra_info   = sprintf(
					/* Translators: %1$s Time string, %2$s text string indicating progress */
					esc_html__( 'Last activity: %1$s ago - %2$s', 'pinterest-for-woocommerce' ),
					human_time_diff( $state['last_activity'] ),
					$state['progress']
				);
				break;

			case 'generated':
				$status       = 'success';
				$status_label = esc_html__( 'Up to date', 'pinterest-for-woocommerce' );
				break;

			case 'scheduled_for_generation':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed generation will start shortly', 'pinterest-for-woocommerce' );
				break;

			case 'pending_config':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed pending configuration', 'pinterest-for-woocommerce' );
				break;

			default:
				$status       = 'error';
				$status_label = esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' );
				$extra_info   = sprintf(
					/* Translators: %1$s Time string, %2$s text string indicating progress */
					esc_html__( 'Last activity: %1$s ago - %2$s', 'pinterest-for-woocommerce' ),
					human_time_diff( $state['last_activity'] ),
					$state['progress']
				);
				break;
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'XML Feed', 'pinterest-for-woocommerce' ),
			'status'       => $status,
			'status_label' => $status_label,
			'extra_info'   => $extra_info,
		);

		return $result;
	}


	/**
	 * Adds to the result variable an array with info about the
	 * registration and configuration process of the XML feed to the Pinterest API.
	 *
	 * @param array $result The result array to add values to.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	private function add_feed_registration_state( $result ) {

		$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$extra_info  = '';

		try {

			$merchant = Base::get_merchant( $merchant_id );

			if ( 'success' !== $merchant['status'] ) {
				throw new \Exception( esc_html__( 'Could not get merchant info.', 'pinterest-for-woocommerce' ) );
			}

			if ( 'ACTIVE' !== $merchant['data']->product_pin_feed_profile->feed_status ) {
				throw new \Exception( esc_html__( 'Product Feed not active.', 'pinterest-for-woocommerce' ) );
			}

			$api_approved_status = $merchant['data']->product_pin_approval_status;

			switch ( $api_approved_status ) {
				case 'approved':
					$status       = 'success';
					$status_label = esc_html__( 'Product Feed configured for Ingestion on Pinterest', 'pinterest-for-woocommerce' );
					$extra_info   = sprintf(
						/* Translators: %1$s The URL of the product feed, %2$s Time string */
						esc_html__( 'Pinterest will fetch your product\'s feed URL (%1$s) every %2$s', 'pinterest-for-woocommerce' ),
						$merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location,
						human_time_diff( 0, ( $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_freq / 1000 ) )
					);

					break;
				case 'pending':
				case 'appeal_pending':
					$status       = 'pending';
					$status_label = esc_html__( 'Product Feed pending approval on Pinterest.', 'pinterest-for-woocommerce' );
					break;
				case 'declined':
					$status       = 'error';
					$status_label = esc_html__( 'Product Feed declined by Pinterest', 'pinterest-for-woocommerce' );
					break;

				default:
					$status       = 'warning';
					$status_label = esc_html__( 'Product Feed not yet configured on Pinterest.', 'pinterest-for-woocommerce' );
					break;
			}
		} catch ( \Throwable $th ) {
			$status       = 'error';
			$status_label = $th->getMessage();
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Remote Feed Setup', 'pinterest-for-woocommerce' ),
			'status'       => $status,
			'status_label' => $status_label,
			'extra_info'   => $extra_info,
		);

		if ( 'success' === $status ) {
			$result = $this->add_feed_sync_status( $result );
		} else {
			$result['overview'] = array(
				'total'      => 0,
				'not_synced' => 0,
				'warnings'   => 0,
				'errors'     => 0,
			);
		}

		return $result;
	}


	/**
	 * Adds to the result variable an array with info about the sync process / feed ingestion
	 * status as returned by Pinterest API.
	 *
	 * @param array $result The result array to add values to.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 */
	private function add_feed_sync_status( $result ) {

		$merchant_id = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$extra_info  = '';

		try {

			// Get feed ingestion status.
			$feed_report = Base::get_feed_report( $merchant_id );

			if ( 'success' !== $feed_report['status'] ) {
				throw new \Exception( esc_html__( 'Response error when trying to get feed report from Pinterest.', 'pinterest-for-woocommerce' ) );
			}

			if ( ! property_exists( $feed_report['data'], 'workflows' ) || ! is_array( $feed_report['data']->workflows ) || empty( $feed_report['data']->workflows ) ) {
				throw new \Exception( esc_html__( 'Response error. Feed report contains no feed workflow.', 'pinterest-for-woocommerce' ) );
			}

			// Get latest workflow.
			usort(
				$feed_report['data']->workflows,
				function ( $a, $b ) {
					return $b->created_at - $a->created_at;
				}
			);
			$workflow = reset( $feed_report['data']->workflows );

			switch ( $workflow->workflow_status ) {
				case 'COMPLETED':
				case 'COMPLETED_EARLY':
					$status       = 'success';
					$status_label = esc_html__( 'Automatically pulled by Pinterest', 'pinterest-for-woocommerce' );
					$extra_info   = sprintf(
						/* Translators: %1$s Time string, %2$s number of products */
						esc_html__( 'Last pulled: %1$s ago, containing %2$d products', 'pinterest-for-woocommerce' ),
						human_time_diff( ( $workflow->created_at / 1000 ) ),
						(int) $workflow->product_count
					);
					break;

				case 'PROCESSING':
					$status       = 'pending';
					$status_label = esc_html__( 'Processing', 'pinterest-for-woocommerce' );
					$extra_info   = sprintf(
						/* Translators: %1$s Time string, %2$s number of products */
						esc_html__( 'Last pulled: %1$s ago, containing %2$d products', 'pinterest-for-woocommerce' ),
						human_time_diff( ( $workflow->created_at / 1000 ) ),
						(int) $workflow->product_count
					);
					break;

				case 'UNDER_REVIEW':
					$status       = 'pending';
					$status_label = esc_html__( 'Feed is under review.', 'pinterest-for-woocommerce' );
					break;

				case 'FAILED':
					$status       = 'error';
					$status_label = esc_html__( 'Feed ingestion failed.', 'pinterest-for-woocommerce' );
					$extra_info   = sprintf(
						/* Translators: %1$s Time difference string */
						esc_html__( 'Last attempt: %1$s ago', 'pinterest-for-woocommerce' ),
						human_time_diff( ( $workflow->created_at / 1000 ) ),
						(int) $workflow->product_count
					);

					$extra_info .= $this->get_global_error_from_workflow( (array) $workflow );
					break;

				default:
					$status       = 'error';
					$status_label = esc_html__( 'Unknown status in workflow.', 'pinterest-for-woocommerce' );
					$extra_info   = sprintf(
						/* Translators: The status text returned by the API. */
						esc_html__( 'API Returned an unknown status: %1$s', 'pinterest-for-woocommerce' ),
						$workflow->workflow_status
					);

					$extra_info .= $this->get_global_error_from_workflow( (array) $workflow );
					break;
			}

			$result['overview'] = $this->get_totals_from_workflow( (array) $workflow );

		} catch ( \Throwable $th ) {
			$status       = 'error';
			$status_label = $th->getMessage();

			$result['overview'] = array(
				'total'      => 0,
				'not_synced' => 0,
				'warnings'   => 0,
				'errors'     => 0,
			);
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Remote Sync Status', 'pinterest-for-woocommerce' ),
			'status'       => $status,
			'status_label' => $status_label,
			'extra_info'   => $extra_info,
		);

		return $result;
	}

	/**
	 * Gets the overview totals from the given workflow array.
	 *
	 * @param array $workflow The workflow array.
	 *
	 * @return array
	 */
	private function get_totals_from_workflow( $workflow ) {

		$sums = array(
			'warnings' => 0,
			'errors'   => 0,
		);

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


	/**
	 * Parses the given workflow and returns a string which contains the
	 * first error message found to be related to the feed globally and not
	 * a specific product.
	 *
	 * @param array $workflow The workflow array.
	 *
	 * @return string
	 */
	private function get_global_error_from_workflow( $workflow ) {

		$error_code = null;

		foreach ( self::ERROR_CONTEXTS as $context ) {
			if ( ! empty( $workflow[ $context ] ) ) {

				foreach ( $workflow[ $context ] as $code => $count ) {
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
				return ' - ' . sprintf( esc_html__( 'Pinterest returned: %1$s', 'pinterest-for-woocommerce' ), $messages_map['data']->$error_code );
			}
		}

		return '';
	}
}
