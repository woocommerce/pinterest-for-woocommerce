<?php
/**
 * API Options
 *
 * @package     Pinterest_For_Woocommerce/API
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\API;

use \WP_Error;
use \WP_REST_Server;
use \WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint handling Options.
 */
class UserInteraction extends VendorAPI {

	const USER_INTERACTION    = 'user_interaction';
	const ADS_MODAL_DISMISSED = 'ads_modal_dismissed';

	/**
	 * Initialize class
	 */
	public function __construct() {
		$this->base                        = self::USER_INTERACTION;
		$this->supports_multiple_endpoints = true;
		$this->endpoint_callbacks_map      = array(
			'get_user_interaction' => WP_REST_Server::READABLE,
			'set_user_interaction' => WP_REST_Server::CREATABLE,
		);

		$this->register_routes();
	}


	/**
	 * Handle get settings.
	 *
	 * @return array
	 */
	public function get_user_interaction() {
		return array(
			self::ADS_MODAL_DISMISSED => (bool) get_transient( PINTEREST_FOR_WOOCOMMERCE_TRANSIENT_NAME . '_' . self::ADS_MODAL_DISMISSED ),
		);
	}


	/**
	 * Handle set settings.
	 *
	 * @param WP_REST_Request $request The request.
	 *
	 * @return array|WP_Error
	 */
	public function set_user_interaction( WP_REST_Request $request ) {

		if ( $request->has_param( self::ADS_MODAL_DISMISSED ) ) {
			$ads_modal_dismissed = $request->has_param( self::ADS_MODAL_DISMISSED );

			/*
			 * ADS_MODAL_DISMISSED dismissed transient counts how many times the modal has been dismissed.
			 * Each new dismiss time equals 24h times dismiss count.
			 * 4th dismiss dismisses the modal indefinitely.
			 */
			$old_count = get_transient( PINTEREST_FOR_WOOCOMMERCE_TRANSIENT_NAME . '_' . self::ADS_MODAL_DISMISSED );
			$new_count = $old_count ? $old_count++ : 1;
			if ( 4 === $new_count ) {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_TRANSIENT_NAME . '_' . self::ADS_MODAL_DISMISSED, $new_count );
			} else {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_TRANSIENT_NAME . '_' . self::ADS_MODAL_DISMISSED, $new_count, $new_count * DAY_IN_SECONDS );
			}

			// Confirm dismissal.
			return array(
				self::ADS_MODAL_DISMISSED => true,
			);
		}

		return new WP_Error( \PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_' . self::USER_INTERACTION, esc_html__( 'Unrecognized interaction parameter', 'pinterest-for-woocommerce' ), array( 'status' => 400 ) );
	}
}
