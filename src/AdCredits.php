<?php
/**
 * Pinterest for WooCommerce Ads Credits.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.10
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\APIV5;
use Automattic\WooCommerce\Pinterest\API\Base;
use Exception;
use Pinterest_For_Woocommerce;
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

	const ADS_CREDIT_FUTURE_DISCOUNT = 16;
	const ADS_CREDIT_MARKETING_OFFER = 5;

	/**
	 * Initialize Ad Credits actions and Action Scheduler hooks.
	 *
	 * @since 1.2.5
	 */
	public static function schedule_event() {
		add_action( Heartbeat::HOURLY, array( static::class, 'handle_redeem_credit' ), 20 );
	}

	/**
	 * Check if the advertiser has set the billing data.
	 *
	 * @since 1.2.5
	 *
	 * @return mixed
	 */
	public static function handle_redeem_credit() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			// Advertiser not connected redeem operation makes no sense.
			return true;
		}

		Pinterest_For_Woocommerce()::add_available_credits_info_to_account_data();

		if ( ! Pinterest_For_Woocommerce()::get_billing_setup_info_from_account_data() ) {
			// Do not redeem credits if the billing is not setup.
			return true;
		}

		if ( Pinterest_For_Woocommerce()::check_if_coupon_was_redeemed() ) {
			// Redeem credits only once.
			return true;
		}

		if ( ! self::check_if_ads_campaign_is_active() ) {
			return true;
		}

		Pinterest_For_Woocommerce()::add_redeem_credits_info_to_account_data();

		return true;
	}


	/**
	 * Redeem Ad Credits.
	 *
	 * @since 1.2.5
	 *
	 * @param string  $offer_code Coupon string.
	 * @param integer $error_code Reference parameter for error number.
	 * @param string  $error_message Reference parameter for error message.
	 *
	 * @return bool Weather the coupon was successfully redeemed or not.
	 */
	public static function redeem_credits( $offer_code, &$error_code = null, &$error_message = null ) {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			// Advertiser not connected, we can't check if credits were redeemed.
			return false;
		}

		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		try {
			$offer_code_credits_data = APIV5::redeem_ads_offer_code( $ad_account_id, $offer_code );
			if ( ! $offer_code_credits_data['success'] ) {
				$error_code    = $offer_code_credits_data['errorCode'];
				$error_message = $offer_code_credits_data['errorMessage'];
				Logger::log( "{$error_code}: {$error_message}", 'error' );
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
	 * @since 1.2.5
	 *
	 * @return bool Wether campaign is active or not.
	 */
	public static function check_if_ads_campaign_is_active() {

		$is_campaign_active = get_transient( self::ADS_CREDIT_CAMPAIGN_TRANSIENT );

		// If transient is available then it means that we have already checked.
		if ( false !== $is_campaign_active ) {
			return wc_string_to_bool( $is_campaign_active );
		}

		$request_error = false;
		try {
			// Check if all conditions are met.
			if (
				Pinterest_For_Woocommerce_Ads_Supported_Countries::is_ads_supported_country() &&
				AdCreditsCoupons::has_valid_coupon_for_merchant() &&
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

		return $is_campaign_active;
	}

	/**
	 * Check if campaign is enabled in the recommendations API from woo.com.
	 *
	 * @since 1.2.5
	 *
	 * @throws Exception API fetch error.
	 *
	 * @return bool Wether the campaign is active or not.
	 */
	private static function get_is_campaign_active_from_recommendations() {
		$request         = wp_remote_get( 'https://woo.com/wp-json/wccom/marketing-tab/1.2/recommendations.json' );
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

	/**
	 * Fetch data from the discount endpoint and get the necessary fields.
	 *
	 * @since 1.2.5
	 *
	 * @return mixed False when no info is available, discounts object when discounts are available.
	 */
	public static function process_available_discounts() {
		if ( ! Pinterest_For_Woocommerce::is_connected() ) {
			// Advertiser not connected, we can't check if credits were redeemed.
			return false;
		}

		$advertiser_id = Pinterest_For_Woocommerce()::get_setting( 'tracking_advertiser' );

		if ( false === $advertiser_id ) {
			// No advertiser id stored. But we are connected. This is an abnormal state that should not happen.
			Logger::log( __( 'Advertiser connected but the connection id is missing.', 'pinterest-for-woocommerce' ) );
			return false;
		}

		$result = APIV5::get_ads_credit_discounts( $advertiser_id );

		$discounts = array_reduce(
			array_filter(
				$result['items'] ?? array(),
				function ( $item ) {
					return $item['active'];
				}
			),
			function ( $carry, $item ) {
				$is_future_discount = ( $item['discount_restrictions']['spend_threshold'] ?? 0 ) > 0;
				$carry[ $is_future_discount ? 'future_discount' : 'marketing_offer' ][] = $item;
				return $carry;
			},
			array()
		);

		$coupon = AdCreditsCoupons::get_coupon_for_merchant();

		$found_discounts = array();
		foreach ( $discounts[ 'future_discount' ] as $discount ) {
			$offer_code = $discount['discount_restrictions']['marketing_offer_code'] ?? '';
			if ( $offer_code === $coupon ) {
				$found_discounts['future_discount'] = true;
			}
		}

		$remaining_discount_value = 0;
		// Sum all of the available coupons values.
		foreach ( $discounts[ 'marketing_offer' ] as $discount ) {
			$information               = (array) $discount;
			$remaining_discount_value += (float) $information['remaining_discount_in_micro_currency'] / 1000000;
		}

		$found_discounts['marketing_offer'] = array(
			'remaining_discount' => wc_price( $remaining_discount_value ),
		);

		return $found_discounts;
	}

}
