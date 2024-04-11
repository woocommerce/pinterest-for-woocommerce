<?php
/**
 * Pinterest for WooCommerce API Exception
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pinterest API Exception
 *
 * Class PinterestApiException
 */
class PinterestApiException extends \Exception {

	/**
	 * Merchant not found during the API call.
	 *
	 * API response message:
	 * "Sorry! We couldn't find that merchant. Please ensure you have access and a valid merchant id."
	 *
	 * @var int MERCHANT_NOT_FOUND Error code for merchant not found API error.
	 */
	public const MERCHANT_NOT_FOUND = 650;


	/**
	 * The Ads Credit offer was already redeemed.
	 *
	 * API response message:
	 * "The offer has already been redeemed by this advertiser"
	 *
	 * @var int OFFER_ALREADY_REDEEMED Error code for offer already redeemed API error.
	 */
	public const OFFER_ALREADY_REDEEMED = 2324;


	/**
	 * No valid billing setup.
	 *
	 * API response message:
	 * "Billing setup is required for getting offer codes for an Advertiser"
	 */
	public const NO_VALID_BILLING_SETUP = 4374;


	/**
	 * Offer already redeemed by another advertiser.
	 *
	 * API response message:
	 * "User has this offer on another advertiser"
	 */
	public const OFFER_ALREADY_REDEEMED_BY_ANOTHER_ADVERTISER = 2318;

	/**
	 * Holds the specific Pinterest error code, which is useful in addition to the response code.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private $pinterest_code = null;

	/**
	 * Pinterest_API_Exception constructor.
	 *
	 * @param string|array $error The error message or an array containing the error message + additional data.
	 * @param int          $response_code The response code of the API call.
	 */
	public function __construct( $error, $response_code ) {
		$message              = $error['message'] ?? $error;
		$this->pinterest_code = $error['response_body']['code'] ?? null;

		parent::__construct( $message ?? $error, $response_code );
	}

	/**
	 * Returns the Pinterest error code for the current API response.
	 *
	 * @return int
	 */
	public function get_pinterest_code() {
		return $this->pinterest_code;
	}
}
