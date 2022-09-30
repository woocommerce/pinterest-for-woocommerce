<?php
/**
 * Pinterest for WooCommerce Ads Credits.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Exception;
use Pinterest_For_Woocommerce_Ads_Supported_Countries;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling ad credits.
 */
class AdCredits {

	const ADS_CREDIT_CAMPAIGN_TRANSIENT = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-ads-credit-campaign-transient';
	const ADS_CREDIT_CAMPAIGN_OPTION    = 'ads_campaign_is_active';
	/**
	 * Hardcoded offer code as an initial approach.
	 * TODO: Add the rest of offer codes or perhaps moving the logic to a separate class, where we can get codes by country, etc.
	 */
	const OFFER_CODE = 'TESTING_WOO_FUTURE';


	/**
	 * Initialize Ad Credits actions and Action Scheduler hooks.
	 *
	 * @since x.x.x
	 */
	public static function schedule_event() {
		add_action( Heartbeat::DAILY, array( __CLASS__, 'handle_redeem_credit' ), 20 );
	}

	/**
	 * Check if the advertiser has set the billing data.
	 *
	 * @since x.x.x
	 *
	 * @return mixed
	 */
	public function handle_redeem_credit() {

		if ( ! Pinterest_For_Woocommerce()::get_billing_setup_info_from_account_data() ) {
			// Do not redeem credits if the billing is not setup.
			return true;
		}

		if ( Pinterest_For_Woocommerce()::get_redeem_credits_info_from_account_data() ) {
			// Redeem credits only once.
			return true;
		}

		Pinterest_For_Woocommerce()::add_redeem_credits_info_to_account_data();

		return true;
	}


	/**
	 * Redeem Ad Credits.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function redeem_credits() {

		if ( ! Pinterest_For_Woocommerce()::get_data( 'is_advertiser_connected' ) ) {
			// Advertiser not connected, we can't check if credits were redeemed.
			return false;
		}

		$advertiser_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );

		if ( false === $advertiser_id ) {
			// No advertiser id stored. But we are connected. This is an abnormal state that should not happen.
			Logger::log( __( 'Advertiser connected but the connection id is missing.', 'pinterest-for-woocommerce' ) );
			return false;
		}

		$offer_code = self::OFFER_CODE;

		try {
			$result = Base::validate_ads_offer_code( $advertiser_id, $offer_code );
			if ( 'success' !== $result['status'] ) {
				return false;
			}

			$redeem_credits_data = (array) $result['data'];

			if ( ! isset( $redeem_credits_data[ $offer_code ] ) ) {
				// No data for the requested offer code.
				Logger::log( __( 'There is no available data for the requested offer code.', 'pinterest-for-woocommerce' ) );
				return false;
			}

			$offer_code_credits_data = $redeem_credits_data[ $offer_code ];

			if ( ! $offer_code_credits_data->success && 2322 !== $offer_code_credits_data->error_code ) {
				Logger::log( $offer_code_credits_data->failure_reason, 'error' );
				return false;
			}

			return true;

		} catch ( Throwable $th ) {

			Logger::log( $th->getMessage(), 'error' );
			return false;

		}
	}

	/**
	 * Check if the ads campaign is active. In order for that to happen the
	 * following conditions need to be met:
	 * 1. Merchant needs to be in ads supported country.
	 * 2. Merchant needs to have coupon available for his chosen currency.
	 * 3. Ads Campaign needs to be globally active.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function check_if_ads_campaign_is_active() {

		$is_campaign_active = get_transient( self::ADS_CREDIT_CAMPAIGN_TRANSIENT );

		// If transient is available then it means that we have already checked.
		if ( false !== $is_campaign_active ) {
			return;
		}

		$request_error = false;
		try {
			// Check if all conditions are met.
			if (
				Pinterest_For_Woocommerce_Ads_Supported_Countries::is_ads_supported_country() &&
				self::verify_if_coupon_exists_for_merchant() &&
				self::get_is_campaign_active_from_recommendations()
			) {
				$is_campaign_active = true;
			}
		} catch ( Exception $ex ) {
			$request_error = true;
		}

		Pinterest_For_Woocommerce()->save_setting( self::ADS_CREDIT_CAMPAIGN_OPTION, $is_campaign_active );

		/*
		 * Try again in fifteen minutes in case we had problems fetching
		 * the campaign status from the server, wait full day otherwise.
		 */
		set_transient(
			self::ADS_CREDIT_CAMPAIGN_TRANSIENT,
			wc_bool_to_string( $is_campaign_active ),
			$request_error ? 15 * MINUTE_IN_SECONDS : DAY_IN_SECONDS
		);
	}

	/**
	 * Verify if there is a valid coupon for user currency.
	 *
	 * @since x.x.x
	 *
	 * @return bool Wether there is a coupon for merchant available.
	 */
	private static function verify_if_coupon_exists_for_merchant() {
		return ! ( false === AdCreditsCoupons::has_valid_coupon_for_merchant() );
	}

	/**
	 * Check if campaign is enabled in the recommendations API from woocommerce.com.
	 *
	 * @since x.x.x
	 *
	 * @throws Exception API fetch error.
	 *
	 * @return bool Wether the campaign is active or not.
	 */
	private static function get_is_campaign_active_from_recommendations() {

		$request         = wp_remote_get( 'https://woocommerce.com/wp-json/wccom/marketing-tab/1.2/recommendations.json' );
		$recommendations = array();

		if ( is_wp_error( $request ) ) {
			throw new Exception(
				sprintf(
					/* translators: API error message */
					__( 'Could not fetch ads campaign status due to: %s', 'pinterest-for-woocommerce' ),
					$request->get_error_message()
				)
			);
		}

		if ( ! is_wp_error( $request ) && 200 === $request['response']['code'] ) {
			$recommendations = json_decode( $request['body'], true );
		}

		// Find Pinterest plugin entry and check for promotions key.
		foreach ( $recommendations as $recommendation ) {
			if ( 'pinterest-for-woocommerce' === $recommendation['product'] ) {
				return array_key_exists( 'show_extension_promotions', $recommendation ) ? $recommendation['show_extension_promotions'] : false;
			}
		}

		return false;
	}

}
