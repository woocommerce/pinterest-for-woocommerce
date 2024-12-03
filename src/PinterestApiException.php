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
	 * Merchant has been disapproved.
	 * Some feeds, when deleting, may fail to delete due to the merchant has been disapproved.
	 */
	public const MERCHANT_DISAPPROVED = 2625;

	/**
	 * Merchant is under review.
	 * Some feeds, when deleting, may fail to delete due to the merchant is under review.
	 */
	public const MERCHANT_UNDER_REVIEW = 2626;

	/**
	 * Feed has active promotions.
	 * Some feeds, when deleting, may fail to delete due to active promotions on the feed items.
	 */
	public const CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS = 4162;

	/**
	 * Holds the specific Pinterest error code, which is useful in addition to the response code.
	 *
	 * @var int
	 * @since 1.0.0
	 */
	private int $pinterest_code = 0;

	/**
	 * Pinterest_API_Exception constructor.
	 *
	 * @param string|array $error The error message or an array containing the error message + additional data.
	 * @param int|array    $response_code The error code of the API response body or the whole response body as array.
	 */
	public function __construct( $error, $response_code ) {
		$message              = $error['message'] ?? $error;
		$this->pinterest_code = (int) ( $error['response_body']['code'] ?? $response_code ) ?? 0;

		parent::__construct( $message, $response_code );
	}

	/**
	 * Returns the Pinterest error code for the current API response.
	 *
	 * @return int
	 */
	public function get_pinterest_code(): int {
		return $this->pinterest_code;
	}
}
