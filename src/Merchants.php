<?php
/**
 * Pinterest for WooCommerce Merchants related helper methods
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling registration & generation of the XML product feed.
 */
class Merchants {

	/**
	 * Returns the merchant object for the current user.
	 * If a merchant already exists, either saved to the database, or is
	 * returned by the Advertisers endpoint, it will be used, otherwise an
	 * attempt to create a new one is made.
	 *
	 * @return array
	 *
	 * @throws \Throwable PHP Exception.
	 * @throws \Exception PHP Exception.
	 */
	public static function get_merchant() {

		$merchant          = false;
		$merchant_id       = Pinterest_For_Woocommerce()::get_data( 'merchant_id' );
		$saved_merchant_id = $merchant_id;

		if ( empty( $merchant_id ) ) {
			// Get merchant from advertiser object.

			try {
				$merchant_id = self::get_merchant_id_from_advertiser();
			} catch ( \Throwable $th ) {

				if ( 404 !== $th->getCode() ) {
					throw $th;
				}

				$merchant = false;
			}
		}

		if ( ! empty( $merchant_id ) ) {

			try {
				$merchant = API\Base::get_merchant( $merchant_id );
				if ( $saved_merchant_id !== $merchant_id ) {
					Pinterest_For_Woocommerce()::save_data( 'merchant_id', $merchant['data']->id );
				}
			} catch ( \Throwable $th ) {
				$merchant = false;
			}
		}

		if ( ! $merchant || ( 'success' !== $merchant['status'] && 650 === $merchant['code'] ) ) {  // https://developers.pinterest.com/docs/redoc/#tag/API-Response-Codes Merchant not found 650.
			// Try creating one.
			$merchant = API\Base::maybe_create_merchant();
			if ( 'success' === $merchant['status'] ) {
				Pinterest_For_Woocommerce()::save_data( 'merchant_id', $merchant['data']->id );
			}
		}

		if ( ! $merchant || 'success' !== $merchant['status'] ) {
			throw new \Exception( __( 'Response error when trying create a merchant or get the existing one.', 'pinterest-for-woocommerce' ), 400 );
		}

		return $merchant;
	}


	/**
	 * Gets the merchant ID of the authenticated user from the data returned on the Advertisers endpoint.
	 *
	 * @return string
	 *
	 * @throws \Exception PHP exception.
	 */
	private static function get_merchant_id_from_advertiser() {
		$advertisers = API\Base::get_advertisers();

		if ( 'success' !== $advertisers['status'] ) {
			throw new \Exception( __( 'Response error when trying to get advertisers.', 'pinterest-for-woocommerce' ), 400 );
		}

		$advertiser = reset( $advertisers['data'] ); // All advertisers assigned to a user share the same merchant_id.

		if ( empty( $advertiser->merchant_id ) ) {
			throw new \Exception( __( 'No merchant returned in the advertiser\'s response.', 'pinterest-for-woocommerce' ), 404 );
		}

		return $advertiser->merchant_id;
	}

}
