<?php
/**
 * Handle & return Pinterest Feed State
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest as Pinterest;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\FeedStatusService;
use Automattic\WooCommerce\Pinterest\LocalFeedConfigs;
use Automattic\WooCommerce\Pinterest\ProductSync;
use Automattic\WooCommerce\Pinterest\RichPins;
use Automattic\WooCommerce\Pinterest\Tracking;
use WP_Error;
use \WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint returning the current state of the XML feed.
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

		$this->hooks();
	}

	/**
	 * Add feed state hooks.
	 */
	private function hooks() {
		add_filter( 'pinterest_for_woocommerce_feed_state', array( $this, 'add_local_feed_status' ) );
		add_filter( 'pinterest_for_woocommerce_feed_state', array( $this, 'add_feed_status' ) );
		add_filter( 'pinterest_for_woocommerce_feed_state', array( $this, 'add_third_party_tags_warning' ) );
		add_filter( 'pinterest_for_woocommerce_feed_state', array( $this, 'add_rich_pins_conflict_warning' ) );
	}

	/**
	 * Get the status of the current feed, for:
	 * - the local feed configuration.
	 * - the remote feed registration.
	 * - the feed ingestion process.
	 *
	 * The form of the returned data is as follows:
	 *
	 * [
	 *   workflow => [
	 *     [
	 *     'label'        => 'The label of this workflow',
	 *     'status'       => 'success|warning|error|etc',
	 *     'status_label' => 'The result for this workflow'
	 *     'extra_info'   => 'Extra info for this workflow'
	 *     ],
	 *     ...
	 *   ],
	 *   'overview' => [
	 *      'total'      => 0, // Total number of products in the feed.
	 *      'not_synced' => 0, // Number of products not synced because of errors.
	 *      'warnings'   => 0, // Number of warnings.
	 *      'errors'     => 0, // Number of errors.
	 *   ]
	 * ]
	 *
	 * @return array|WP_Error
	 *
	 * @throws \Exception PHP Exception.
	 */
	public function get_feed_state() {
		try {
			if ( ! ProductSync::is_product_sync_enabled() ) {
				return array(
					'workflow' => array(
						array(
							'label'        => esc_html__( 'XML feed', 'pinterest-for-woocommerce' ),
							'status'       => 'error',
							'status_label' => esc_html__( 'Product sync is disabled.', 'pinterest-for-woocommerce' ),
							'extra_info'   => wp_kses_post( ProductSync::get_feed_status_extra_info() ),
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

			/**
			 * Returns feed state.
			 * phpcs:disable WooCommerce.Commenting.CommentHooks.MissingSinceComment
			 */
			return apply_filters( 'pinterest_for_woocommerce_feed_state', array() );

		} catch ( \Throwable $th ) {

			/* Translators: The error description as returned from the API */
			$error_message = sprintf( esc_html__( 'Error getting feed\'s state. [%s]', 'pinterest-for-woocommerce' ), $th->getMessage() );

			return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_state_error', $error_message, array( 'status' => $th->getCode() ) );
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
	public function add_local_feed_status( array $result ): array {
		$state      = Pinterest\ProductFeedStatus::get();
		$extra_info = '';

		switch ( $state['status'] ) {

			case 'in_progress':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed generation in progress.', 'pinterest-for-woocommerce' );
				$extra_info   = sprintf(
					esc_html(
						/* translators: 1: Time string, 2: number of products, 3: opening anchor tag, 4: closing anchor tag */
						_n(
							'Last activity: %1$s ago - Wrote %2$s product to %3$sfeed file%4$s.',
							'Last activity: %1$s ago - Wrote %2$s products to %3$sfeed file%4$s.',
							$state['product_count'],
							'pinterest-for-woocommerce'
						)
					),
					human_time_diff( $state['last_activity'] ),
					$state['product_count'],
					sprintf( '<a href="%s" target="_blank">', esc_url( $this->get_feed_url() ) ),
					'</a>',
				);
				break;

			case 'generated':
				$status       = 'success';
				$status_label = esc_html__( 'Up to date', 'pinterest-for-woocommerce' );
				$extra_info   = sprintf(
					esc_html(
						/* translators: 1: Time string, 2: total number of products, 3: opening anchor tag, 4: closing anchor tag */
						_n(
							'Successfully generated %1$s ago - Wrote %2$s product to %3$sfeed file%4$s',
							'Successfully generated %1$s ago - Wrote %2$s products to %3$sfeed file%4$s',
							$state['product_count'],
							'pinterest-for-woocommerce'
						)
					),
					human_time_diff( $state['last_activity'] ),
					$state['product_count'],
					sprintf( '<a href="%s" target="_blank">', esc_url( $this->get_feed_url() ) ),
					'</a>',
				);
				break;

			case 'scheduled_for_generation':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed generation will start shortly.', 'pinterest-for-woocommerce' );
				break;

			case 'pending_config':
				$status       = 'pending';
				$status_label = esc_html__( 'Feed configuration will start shortly.', 'pinterest-for-woocommerce' );
				break;

			default:
				$status       = 'error';
				$status_label = esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' );
				$extra_info   = sprintf(
					/* Translators: %1$s Time string, %2$s error message */
					esc_html__( 'Last activity: %1$s ago - %2$s', 'pinterest-for-woocommerce' ),
					human_time_diff( $state['last_activity'] ),
					$state['error_message']
				);
				break;
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'XML feed', 'pinterest-for-woocommerce' ),
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
	public function add_feed_status( array $result ): array {
		// Defaults.
		$extra_info   = '';
		$status       = 'error';
		$status_label = esc_html__(
			'Product feed not yet configured on Pinterest.',
			'pinterest-for-woocommerce'
		);

		$status = FeedStatusService::get_feed_registration_status();
		if ( FeedStatusService::FEED_STATUS_NOT_REGISTERED === $status ) {
			$status       = 'pending';
			$status_label = esc_html__(
				'Product feed not yet configured on Pinterest.',
				'pinterest-for-woocommerce'
			);
		}

		if ( FeedStatusService::FEED_STATUS_ERROR_FETCHING_FEED === $status ) {
			$status       = 'error';
			$status_label = esc_html__( 'Could not get feed info.', 'pinterest-for-woocommerce' );
		}

		if ( FeedStatusService::FEED_STATUS_DISAPPROVED === $status ) {
			$status       = 'error';
			$status_label = esc_html__( 'Product feed declined by Pinterest', 'pinterest-for-woocommerce' );
		}

		$succeeded = array(
			FeedStatusService::FEED_STATUS_COMPLETED,
			FeedStatusService::FEED_STATUS_COMPLETED_EARLY,
		);
		if ( in_array( $status, $succeeded, true ) ) {
			$status       = 'success';
			$status_label = esc_html__( 'Product feed configured for ingestion on Pinterest', 'pinterest-for-woocommerce' );
		}

		$pending = array(
			FeedStatusService::FEED_STATUS_QUEUED_FOR_PROCESSING,
			FeedStatusService::FEED_STATUS_PROCESSING,
		);
		if ( in_array( $status, $pending, true ) ) {
			$status       = 'success';
			$status_label = esc_html__( 'Pinterest is processing the feed.', 'pinterest-for-woocommerce' );
		}

		$approval = array(
			FeedStatusService::FEED_STATUS_UNDER_APPEAL,
			FeedStatusService::FEED_STATUS_UNDER_REVIEW,
		);
		if ( in_array( $status, $approval, true ) ) {
			$status       = 'pending';
			$status_label = esc_html__( 'Product feed pending approval on Pinterest.', 'pinterest-for-woocommerce' );
		}

		if ( FeedStatusService::FEED_STATUS_FAILED === $status ) {
			$status       = 'error';
			$status_label = esc_html__( 'Product feed failed.', 'pinterest-for-woocommerce' );
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Remote feed setup', 'pinterest-for-woocommerce' ),
			'status'       => $status,
			'status_label' => $status_label,
			'extra_info'   => $extra_info,
		);

		if ( 'success' === $status ) {
			$result = $this->add_recent_feed_processing_status( $result );
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
	 * Adds to the result variable an array with info about the
	 * third party plugins that may conflict with the tracking feature.
	 *
	 * @param array $result The result array to add values to.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 * @since 1.2.3
	 */
	public function add_third_party_tags_warning( array $result ): array {
		$warning_message = Tracking\Tag::get_third_party_tags_warning_message();

		if ( empty( $warning_message ) ) {
			return $result;
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Pinterest tag', 'pinterest-for-woocommerce' ),
			'status'       => 'warning',
			'status_label' => esc_html__( 'Potential conflicting plugins', 'pinterest-for-woocommerce' ),
			'extra_info'   => $warning_message,
		);

		return $result;
	}


	/**
	 * Adds to the result variable an array with info about the
	 * third party plugins that may conflict with the Rich Pins feature.
	 *
	 * @param array $result The result array to add values to.
	 *
	 * @return array
	 *
	 * @throws \Exception PHP Exception.
	 * @since 1.2.7
	 */
	public function add_rich_pins_conflict_warning( array $result ): array {
		$warning_message = RichPins::get_third_party_conflict_warning_message();

		if ( empty( $warning_message ) ) {
			return $result;
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Pinterest Rich Pins', 'pinterest-for-woocommerce' ),
			'status'       => 'warning',
			'status_label' => esc_html__( 'Potential conflicting plugins', 'pinterest-for-woocommerce' ),
			'extra_info'   => $warning_message,
		);

		return $result;
	}

	/**
	 * Adds to the result variable an array with info about the sync process / feed ingestion status as returned
	 * by Pinterest API.
	 *
	 * @param array $result The result array to add values to.
	 * @return array
	 *
	 * @since x.x.x
	 */
	private function add_recent_feed_processing_status( array $result ): array {
		$extra_info = '';

		$feed_id = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( empty( $feed_id ) ) {
			$status       = 'error';
			$status_label = esc_html__(
				'Feed is not registered with Pinterest.',
				'pinterest-for-woocommerce'
			);
		} else {
			$recent_feed_processing_results = Feeds::get_feed_recent_processing_results( $feed_id );
			if ( empty( $recent_feed_processing_results ) ) {
				$status       = 'error';
				$status_label = esc_html__(
					'Feed report from Pinterest contains no information.',
					'pinterest-for-woocommerce'
				);
			} else {
				$processing_status = $recent_feed_processing_results['status'] ?? '';
				$status            = static::map_status_into_status( $processing_status );
				$status_label      = static::map_status_into_label( $processing_status );
				$extra_info        = static::map_status_into_extra_info( $recent_feed_processing_results );
			}
			$result['overview'] = Pinterest\FeedStatusService::get_processing_result_overview_stats( $recent_feed_processing_results );
		}

		$result['workflow'][] = array(
			'label'        => esc_html__( 'Remote sync status', 'pinterest-for-woocommerce' ),
			'status'       => $status,
			'status_label' => $status_label,
			'extra_info'   => $extra_info,
		);

		return $result;
	}

	/**
	 * Maps the status returned by the API into a status for the UI.
	 *
	 * @param string $status The feed's processing result status returned by the API.
	 * @return string
	 *
	 * @since x.x.x
	 */
	private static function map_status_into_status( string $status ): string {
		switch ( $status ) {
			case 'COMPLETED':
			case 'COMPLETED_EARLY':
				return 'success';
			case 'DISAPPROVED':
			case 'PROCESSING':
			case 'QUEUED_FOR_PROCESSING':
			case 'UNDER_APPEAL':
			case 'UNDER_REVIEW':
				return 'pending';
			case 'FAILED':
			default:
				return 'error';
		}
	}

	/**
	 * Maps the status returned by the API into a label for the UI.
	 *
	 * @param string $status The feed's processing result status returned by the API.
	 * @return string
	 *
	 * @since x.x.x
	 */
	private static function map_status_into_label( string $status ): string {
		switch ( $status ) {
			case 'COMPLETED':
			case 'COMPLETED_EARLY':
				return esc_html__( 'Automatically pulled by Pinterest', 'pinterest-for-woocommerce' );
			case 'DISAPPROVED':
				return esc_html__( 'Feed is disapproved by Pinterest.', 'pinterest-for-woocommerce' );
			case 'FAILED':
				return esc_html__( 'Feed ingestion failed.', 'pinterest-for-woocommerce' );
			case 'PROCESSING':
				return esc_html__( 'Processing', 'pinterest-for-woocommerce' );
			case 'QUEUED_FOR_PROCESSING':
				return esc_html__( 'The feed is queued for processing.', 'pinterest-for-woocommerce' );
			case 'UNDER_APPEAL':
				return esc_html__( 'Feed is under appeal.', 'pinterest-for-woocommerce' );
			case 'UNDER_REVIEW':
				return esc_html__( 'Feed is under review.', 'pinterest-for-woocommerce' );
			default:
				return esc_html__( 'Unknown processing result status.', 'pinterest-for-woocommerce' );
		}
	}

	/**
	 * Maps the status returned by the API into extra info for the UI.
	 *
	 * @param array $processing_results The feed processing results array.
	 * @return string
	 *
	 * @since x.x.x
	 */
	private static function map_status_into_extra_info( array $processing_results ): string {
		$status                  = $processing_results['status'] ?? '';
		$original_products_count = (int) $processing_results['product_counts']['original'] ?? 0;
		$processing_date         = date_create_from_format(
			'Y-m-d\TH:i:s',
			$processing_results['created_at'] ?? gmdate( 'Y-m-d\TH:i:s' )
		);

		switch ( $status ) {
			case 'COMPLETED':
			case 'COMPLETED_EARLY':
			case 'PROCESSING':
				return sprintf(
					/* Translators: %1$s Time difference string, %2$s number of products */
					esc_html__( 'Last pulled: %1$s ago, containing %2$d products', 'pinterest-for-woocommerce' ),
					human_time_diff( $processing_date->getTimestamp() ),
					$original_products_count
				);
			case 'FAILED':
				$info = sprintf(
					/* Translators: %1$s Time difference string */
					esc_html__( 'Last attempt: %1$s ago', 'pinterest-for-woocommerce' ),
					human_time_diff( $processing_date->getTimestamp() )
				);
				$global_error = Pinterest\FeedStatusService::get_processing_results_global_error( $processing_results );
				return $info . ( $global_error ? ' - ' . $global_error : '' );
			case 'DISAPPROVED':
			case 'QUEUED_FOR_PROCESSING':
			case 'UNDER_APPEAL':
			case 'UNDER_REVIEW':
				return '';
			default:
				$info = sprintf(
					/* Translators: The status text returned by the API. */
					esc_html__( 'Pinterest returned an unknown feed status: %1$s', 'pinterest-for-woocommerce' ),
					$status ?? '<empty string>'
				);
				$global_error = Pinterest\FeedStatusService::get_processing_results_global_error( $processing_results );
				return $info . ( $global_error ? ' - ' . $global_error : '' );
		}
	}

	/**
	 * Helper function used for fetching local feed config file.
	 *
	 * This is temporary as we will need to operate on multiple feed files in the future.
	 *
	 * @return string
	 */
	private function get_feed_url() {
		$configs = LocalFeedConfigs::get_instance()->get_configurations();
		$config  = reset( $configs );
		return $config['feed_url'];
	}
}
