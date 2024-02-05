<?php
/**
 * Pinterest API V5 class
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API V5 Methods
 */
class APIV5 extends Base {

	const API_DOMAIN = 'https://api.pinterest.com/v5';

	/**
	 * Prepare request
	 *
	 * @param string $endpoint        the endpoint to perform the request on.
	 * @param string $method          eg, POST, GET, PUT etc.
	 * @param array  $payload         Payload to be sent on the request's body.
	 * @param string $api             The specific Endpoints subset.
	 *
	 * @return array
	 */
	public static function prepare_request( $endpoint, $method = 'POST', $payload = array(), $api = '' ) {

		return array(
			'url'         => static::API_DOMAIN . "/{$endpoint}",
			'method'      => $method,
			'args'        => ! empty( $payload ) ? wp_json_encode( $payload ) : array(),
			'headers'     => array(
				'Pinterest-Woocommerce-Version' => PINTEREST_FOR_WOOCOMMERCE_VERSION,
				'Content-Type'                  => 'application/json',
			),
			'data_format' => 'body',
		);
	}

	/**
	 * Returns basic user information.
	 *
	 * @since x.x.x
	 *
	 * @return mixed|array {
	 *      User account object.
	 *
	 *      @type string $account_type   Type of account. Enum: "PINNER" "BUSINESS".
	 *      @type string $profile_image
	 *      @type string $website_url
	 *      @type string $username
	 *      @type string $business_name
	 *      @type int $board_count      User account board count.
	 *      @type int $pin_count        User account pin count. This includes both created and saved pins.
	 *      @type int $follower_count   User account follower count.
	 *      @type int $following_count  User account following count.
	 *      @type int $monthly_views    User account monthly views.
	 * }
	 * @throws PinterestApiException Throws 403 and 500 exceptions.
	 */
	public static function get_account_info() {
		return self::make_request( 'user_account', 'GET' );
	}

	/**
	 * Pull ads supported countries information from the API.
	 *
	 * @since x.x.x
	 *
	 * @return array {
	 *      Ad Accounts countries
	 *
	 *      @type array[] $items {
	 *          @type string $code Country ID from ISO 3166-1 alpha-2.
	 *          @type string $currency Country currency.
	 *          @type int $index Country index
	 *          @type string $name Country name.
	 *      }
	 * }
	 * @throws PinterestApiException If API request ends up other than 2xx status.
	 */
	public static function get_list_of_ads_supported_countries(): array {
		$request_url = 'resources/ad_account_countries';
		return self::make_request( $request_url, 'GET', array(), '', 2 * DAY_IN_SECONDS );
	}

	/**
	 * Returns the list of the user's websites.
	 *
	 * @since x.x.x
	 *
	 * @return array {
	 *     User account websites.
	 *
	 *     @type array[]  $items {
	 *         @type string $website     Website with path or domain only
	 *         @type string $status      Status of the verification process
	 *         @type string $verified_at UTC timestamp when the verification happened - sometimes missing
	 *     }
	 *     @type string $bookmark
	 * }
	 * @throws PinterestApiException Throws 403 and 500 exceptions.
	 */
	public static function get_user_websites() {
		return self::make_request( 'user_account/websites', 'GET' );
	}

	/**
	 * Returns the list of linked businesses.
	 *
	 * @since x.x.x
	 *
	 * @return mixed|array[] {
	 *      Linked businesses list.
	 *
	 *      @type string $username
	 *      @type string $image_small_url
	 *      @type string $image_medium_url
	 *      @type string $image_large_url
	 *      @type string $image_xlarge_url
	 * }
	 * @throws PinterestApiException Throws 500 exception in case of unexpected error.
	 */
	public static function get_linked_businesses() {
		return self::make_request( 'user_account/businesses', 'GET' );
	}

	/**
	 * Get the advertiser object from the Pinterest API for the given User ID.
	 *
	 * @since x.x.x
	 *
	 * @param string $pinterest_user The Pinterest User ID.
	 *
	 * @return mixed
	 */
	public static function get_advertisers( $pinterest_user = null ) {
		return self::make_request( 'ad_accounts', 'GET' );
	}

	/**
	 * Get the advertiser's tracking tags.
	 *
	 * @param string $ad_account_id the advertiser_id to request the tags for.
	 *
	 * @return array {
	 *      Tag objects list.
	 *
	 *      @type array[] $items {
	 *         Tag object.
	 *
	 *          @type string    $ad_account_id          Ad account ID.
	 *          @type string    $code_snippet           Tag code snippet.
	 *          @type ?string   $enhanced_match_status  The enhanced match status of the tag.
	 *          @type string    $id                     Tag ID.
	 *          @type ?int      $last_fired_time_ms     Time for the last event fired.
	 *
	 *          @type string    $name                   Conversion tag name.
	 *          @type string    $status                 Entity status.
	 *          @type string    $version                Version number.
	 *          @type array     $configs {
	 *              Tag Enhanced Match configuration.
	 *
	 *              @type ?bool $aem_enabled        Whether Automatic Enhanced Match email is enabled.
	 *              @type ?int  $md_frequency       Metadata ingestion frequency.
	 *              @type ?bool $aem_fnln_enabled   Whether Automatic Enhanced Match first name and last name is enabled.
	 *              @type ?bool $aem_ph_enabled     Whether Automatic Enhanced Match phone is enabled.
	 *              @type ?bool $aem_ge_enabled     Whether Automatic Enhanced Match gender is enabled.
	 *              @type ?bool $aem_db_enabled     Whether Automatic Enhanced Match birthdate is enabled.
	 *              @type ?bool $aem_loc_enabled    Whether Automatic Enhanced Match location is enabled.
	 *          }
	 *      }
	 * }
	 *
	 * @throws PinterestApiException|Exception Throws 500 exception.
	 */
	public static function get_advertiser_tags( $ad_account_id ) {
		return self::make_request( "ad_accounts/{$ad_account_id}/conversion_tags", 'GET' );
	}

	/**
	 * Get the advertiser's tracking tag config and details.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/conversion_tags/get
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id         Ad account ID.
	 * @param string $conversion_tag_id     Conversion tag ID.
	 *
	 * @return mixed|array {
	 *      Tag object.
	 *
	 *      @type string    $ad_account_id          Ad account ID.
	 *      @type string    $code_snippet           Tag code snippet.
	 *      @type string    $enhanced_match_status  Enum: "UNKNOWN" "NOT_VALIDATED" "VALIDATING_IN_PROGRESS" "VALIDATION_COMPLETE" null
	 *                                              The enhanced match status of the tag
	 *      @type string    $id                     Tag ID.
	 *      @type int       $last_fired_time_ms     Time for the last event fired.
	 *      @type string    $name                   Conversion tag name.
	 *      @type string    $status                 Enum: "ACTIVE" "PAUSED" "ARCHIVED"
	 *                                              Entity status
	 *      @type string    $version                Version number.
	 *      @type array     $configs {
	 *          Tag Enhanced Match configuration.
	 *
	 *          @type bool    $aem_enabled         Whether Automatic Enhanced Match email is enabled.
	 *          @type int     $md_frequency        Metadata ingestion frequency.
	 *          @type bool    $aem_fnln_enabled    Whether Automatic Enhanced Match name is enabled.
	 *          @type bool    $aem_ph_enabled      Whether Automatic Enhanced Match phone is enabled.
	 *          @type bool    $aem_ge_enabled      Whether Automatic Enhanced Match gender is enabled.
	 *          @type bool    $aem_db_enabled      Whether Automatic Enhanced Match birthdate is enabled.
	 *          @type bool    $aem_loc_enabled     Whether Automatic Enhanced Match location is enabled.
	 *      }
	 * }
	 * @throws PinterestApiException Throws 500 exception in case of unexpected error.
	 */
	public static function get_advertiser_tag( $ad_account_id, $conversion_tag_id ) {
		return self::make_request( "ad_accounts/{$ad_account_id}/conversion_tags/{$conversion_tag_id}", 'GET' );
	}

	/**
	 * Create a tag for the given advertiser.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/conversion_tags/create
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id the advertiser_id to create a tag for.
	 *
	 * @return array {
	 *      Tag object.
	 *
	 *      @type string    $ad_account_id         Ad account ID.
	 *      @type string    $code_snippet          Tag code snippet.
	 *      @type ?string   $enhanced_match_status The enhanced match status of the tag.
	 *      @type string    $id                    Tag ID.
	 *      @type ?int      $last_fired_time_ms    Time for the last event fired.
	 *      @type string    $name                  Conversion tag name.
	 *      @type string    $status                Entity status.
	 *      @type string    $version               Version number.
	 *      @type array     $configs {
	 *          Tag configuration.
	 *
	 *          @type ?bool $aem_enabled        Whether Automatic Enhanced Match email is enabled.
	 *          @type ?int  $md_frequency       Metadata ingestion frequency.
	 *          @type ?bool $aem_fnln_enabled   Whether Automatic Enhanced Match name is enabled.
	 *          @type ?bool $aem_ph_enabled     Whether Automatic Enhanced Match phone is enabled.
	 *          @type ?bool $aem_ge_enabled     Whether Automatic Enhanced Match gender is enabled.
	 *          @type ?bool $aem_db_enabled     Whether Automatic Enhanced Match birthdate is enabled.
	 *          @type ?bool $aem_loc_enabled    Whether Automatic Enhanced Match location is enabled.
	 *      }
	 * }
	 * @throws PinterestApiException|Exception Throws 500 exception.
	 */
	public static function create_tag( $ad_account_id ) {
		$tag_name = self::get_tag_name();
		return self::make_request(
			"ad_accounts/{$ad_account_id}/conversion_tags",
			'POST',
			array(
				'name'             => $tag_name,
				'aem_enabled'      => true,
				'md_frequency'     => 1,
				'aem_fnln_enabled' => true,
				'aem_ph_enabled'   => true,
				'aem_ge_enabled'   => true,
				'aem_db_enabled'   => true,
				'ae_loc_enabled'   => true,
			)
		);
	}

	/**
	 * Returns Pinterest user verification code for website verification.
	 *
	 * @since x.x.x
	 *
	 * @return array {
	 *      Data needed to verify a website.
	 *
	 *      @type string $verification_code Code to check against the user claiming the website.
	 *      @type string $dns_txt_record    DNS TXT record to check against for the website to be claimed.
	 *      @type string $metatag           META tag the verification process searches for the website to be claimed.
	 *      @type string $filename          File expected to find on the website being claimed.
	 *      @type string $file_content      A full html file to upload to the website in order for it to be claimed.
	 * }
	 * @throws PinterestApiException If the request fails with 403 or 500 status.
	 */
	public static function domain_verification_data(): array {
		return self::make_request( 'user_account/websites/verification', 'GET' );
	}

	/**
	 * Sends domain verification request to Pinterest.
	 *
	 * @since x.x.x
	 *
	 * @param string $domain Domain to verify.
	 * @return array {
	 *      Data returned by Pinterest after the verification request.
	 *
	 *      @type string $website       Website with path or domain only.
	 *      @type string $status        Status of the verification process.
	 *      @type string $verified_at   UTC timestamp when the verification happened - sometimes missing.
	 * }
	 * @throws PinterestApiException If the request fails with 2xx status.
	 */
	public static function domain_metatag_verification_request( string $domain ): array {
		return self::make_request(
			'user_account/websites',
			'POST',
			array(
				'website'             => $domain,
				'verification_method' => 'METATAG',
			)
		);
	}

	/**
	 * Sends create feed request to Pinterest API.
	 *
	 * @since x.x.x
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/feeds/create
	 *
	 * @param array  $data {
	 *      Feed data.
	 *
	 *      @type string $name                 A human-friendly name associated to a given feed. This value is currently nullable due to historical reasons. It is expected to become non-nullable in the future.
	 *      @type string $format               The file format of a feed: TSV, CSV, XML.
	 *      @type string $location             The URL where a feed is available for download. This URL is what Pinterest will use to download a feed for processing.
	 *      @type string $catalog_type         Type of the catalog entity: RETAIL, HOTEL.
	 *      @type string $default_currency     Currency Codes from ISO 4217.
	 *      @type string $default_locale       The locale used within a feed for product descriptions.
	 *      @type string $default_country      Country ID from ISO 3166-1 alpha-2.
	 *      @type string $default_availability Default availability for products in a feed.
	 *      @type array[] $credentials {
	 *          Use this if your feed file requires username and password.
	 *
	 *          @type string $username  The required password for downloading a feed.
	 *          @type string $password  The required username for downloading a feed.
	 *      }
	 *      @type array[] $preferred_processing_schedule {
	 *          Optional daily processing schedule. Use this to configure the preferred time for processing a feed (otherwise random).
	 *
	 *          @type string $time      A time in format HH:MM with leading 0 (zero).
	 *          @type string $timezone  The timezone considered for the processing schedule time.
	 *      }
	 * }
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array
	 *
	 * @throws PinterestApiException If the request fail with other than 201 status.
	 */
	public static function create_feed( array $data, string $ad_account_id ): array {
		return self::make_request(
			"catalogs/feeds?ad_account_id={$ad_account_id}",
			'POST',
			$data
		);
	}

	/**
	 * Sends delete feed request to Pinterest API.
	 *
	 * @param string $feed_id       Feed ID.
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array
	 * @throws PinterestApiException If the request fails with other than 204 status.
	 */
	public static function delete_feed( string $feed_id, string $ad_account_id ) {
		return self::make_request(
			"catalogs/feeds/{$feed_id}?ad_account_id={$ad_account_id}",
			'DELETE'
		);
	}

	/**
	 * Sends update feed request to Pinterest API.
	 *
	 * @since x.x.x
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/feeds/update
	 *
	 * @param string $feed_id       Feed ID.
	 * @param array  $data          Feed data.
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array
	 * @throws PinterestApiException If the request fails with other than 200 status.
	 */
	public static function update_feed( string $feed_id, array $data, string $ad_account_id ): array {
		return self::make_request(
			"catalogs/feeds/{$feed_id}?ad_account_id={$ad_account_id}",
			'PATCH',
			$data
		);
	}

	/**
	 * Get merchant's feeds.
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array {
	 *      List of feeds.
	 *
	 *      @type array[] $items {              Feeds.
	 *          @type string $id                Feed ID.
	 *          @type string $name              A human-friendly name associated to a given feed. This value is currently nullable due to historical reasons. It is expected to become non-nullable in the future.
	 *          @type string $status            ACTIVE, INACTIVE. Status for catalogs entities. Present in catalogs_feed values. When a feed is deleted, the response will inform DELETED as status.
	 *          @type string $format            The file format of a feed: TSV, CSV, XML.
	 *          @type string $location          The URL where a feed is available for download. This URL is what Pinterest will use to download a feed for processing.
	 *          @type string $created_at
	 *          @type string $updated_at
	 *          @type string $catalog_type      Type of the catalog entity: RETAIL, HOTEL.
	 *          @type array[] $credentials {
	 *              Use this if your feed file requires username and password.
	 *
	 *              @type string $username  The required password for downloading a feed.
	 *              @type string $password  The required username for downloading a feed.
	 *          }
	 *          @type array[] $preferred_processing_schedule {
	 *              Optional daily processing schedule. Use this to configure the preferred time for processing a feed (otherwise random).
	 *
	 *              @type string $time      A time in format HH:MM with leading 0 (zero).
	 *              @type string $timezone  The timezone considered for the processing schedule time.
	 *          }
	 *          @type string $default_currency      Currency Codes from ISO 4217.
	 *          @type string $default_locale        The locale used within a feed for product descriptions.
	 *          @type string $default_country       Country ID from ISO 3166-1 alpha-2.
	 *          @type string $default_availability  Default availability for products in a feed.
	 *      }
	 *      @type string $bookmark              Cursor used to fetch the next page of items
	 * }
	 * @throws PinterestApiException If the request fails with 2xx status.
	 */
	public static function get_feeds( string $ad_account_id ): array {
		return self::make_request(
			"catalogs/feeds?ad_account_id={$ad_account_id}",
			'GET',
			array(),
			'',
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Invalidate the ad account's feeds cache.
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Ad Account ID.
	 *
	 * @return bool True if the cache was invalidated, false otherwise.
	 */
	public static function invalidate_feeds_cache( string $ad_account_id ): bool {
		return self::invalidate_cached_response(
			"catalogs/feeds?ad_account_id={$ad_account_id}",
			'GET',
			array(),
			''
		);
	}

	/**
	 * Enable a feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 * @param string $feed_id       The ID of the feed to be enabled.
	 *
	 * @return mixed
	 * @throws PinterestApiException If API request ends up other than 2xx status.
	 */
	public static function enable_feed( string $ad_account_id, string $feed_id ): array {
		return static::update_feed_status( $feed_id, Feeds::FEED_STATUS_ACTIVE, $ad_account_id );
	}

	/**
	 * Disable a feed.
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 * @param string $feed_id       The ID of the feed to be disabled.
	 *
	 * @return mixed
	 * @throws PinterestApiException If API request ends up other than 2xx status.
	 */
	public static function disable_feed( string $ad_account_id, string $feed_id ): array {
		return static::update_feed_status( $feed_id, Feeds::FEED_STATUS_INACTIVE, $ad_account_id );
	}

	/**
	 * Update a feed status.
	 *
	 * @since x.x.x
	 *
	 * @param string $feed_id       The ID of the feed to be updated.
	 * @param string $status        The status to be set.
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array
	 * @throws PinterestApiException If API request ends up other than 2xx status.
	 */
	private static function update_feed_status( string $feed_id, string $status, string $ad_account_id ): array {
		return self::make_request(
			"catalogs/feeds/{$feed_id}?ad_account_id={$ad_account_id}",
			'PATCH',
			array(
				'status' => $status,
			),
		);
	}

	/**
	 * Get the latest workflow for the given feed.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/feed_processing_results/list
	 *
	 * @param string $feed_id       Feed ID.
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 * @return array {
	 *      Feed Processing Results.
	 *
	 *      @type array[] $items {
	 *          Feed processing results.
	 *
	 *          @type string $id        Feed Processing Results ID.
	 *          @type string $status    Feed Processing status. "COMPLETED", "COMPLETED_EARLY", "DISAPPROVED", etc.
	 *          @type array $product_counts {
	 *              Feed product counts.
	 *
	 *              @type int $original Original product count.
	 *              @type int $ingested Ingested product count.
	 *          }
	 *          @type array $ingestion_details {
	 *              Processing details.
	 *
	 *              @type array $errors {}      Errors.
	 *              @type array $info {}        Info
	 *              @type array $warnings {}    Warnings.
	 *          }
	 *          @type array $validation_details {
	 *              Validation details.
	 *
	 *              @type array $errors {}      Errors.
	 *              @type array $warnings {}    Warnings.
	 *          }
	 *          @type string $created_at Feed Processing Results creation time.
	 *          @type string $updated_at Feed Processing Results update time.
	 *      }
	 *      @type string $bookmark      Cursor used to fetch the next page of items
	 * }
	 *
	 * @throws PinterestApiException If the request fails with other than 2xx status.
	 * @since x.x.x
	 */
	public static function get_feed_processing_results( $feed_id, $ad_account_id ): array {
		return self::make_request(
			"catalogs/feeds/{$feed_id}/processing_results?ad_account_id={$ad_account_id}&page_size=1",
			'GET'
		);
	}

	/**
	 * List item issues for a given processing result.
	 *
	 * @param string $feed_processing_result_id  Feed Processing Results ID.
	 * @param int    $limit                      Number of items to return.
	 * @return array {
	 *      Items and their corresponding issues.
	 *
	 *      @type array[] $items {
	 *          @type int       $item_number
	 *          @type string    $item_id
	 *          @type array     $errors {
	 *              An array of errors where keys are error codes e.g. (DUPLICATE_PRODUCTS, AVAILABILITY_INVALID, etc.).
	 *
	 *              @type ?string $attribute_name The name of the attribute that caused the error.
	 *              @type ?string $provided_value The value of the attribute that caused the error.
	 *          }
	 *          @type array     $warnings {
	 *              An array of warnings where keys are warning codes e.g. (SHIPPING_INVALID, TAX_INVALID, etc.).
	 *
	 *              @type ?string $attribute_name The name of the attribute that caused the warning.
	 *              @type ?string $provided_value The value of the attribute that caused the warning.
	 *          }
	 *      }
	 * }
	 *
	 * @throws PinterestApiException If the request fails with other than 2xx status.
	 * @since x.x.x
	 */
	public static function get_feed_processing_result_items_issues( string $feed_processing_result_id, $limit = 25 ): array {
		return self::make_request(
			"catalogs/processing_results/{$feed_processing_result_id}/item_issues?page_size={$limit}",
			'GET'
		);
	}

	/**
	 * Attempts to redeem the offer code for the given advertiser.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/ads_credit/redeem
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 * @param string $offer_code    Offer code (hash).
	 *
	 * @return array {
	 *     Credits redeem status.
	 *
	 *     @type bool    $success       Whether the offer code was successfully redeemed or not.
	 *     @type ?int    $errorCode     Error code type if error occurs.
	 *     @type ?string $errorMessage  Reason for failure.
	 * }
	 * @throws PinterestApiException When unable to redeem the offer code or any unexpected error occur.
	 */
	public static function redeem_ads_offer_code( string $ad_account_id, string $offer_code ): array {
		return self::make_request(
			"ad_accounts/{$ad_account_id}/ads_credit/redeem",
			'POST',
			array(
				'offerCodeHash' => $offer_code,
			)
		);
	}

	/**
	 * Get active billing profiles in the advertiser account.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/billing_profiles/get
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array {
	 *     Billing profiles in the advertiser account.
	 *
	 *     @type array[] $items {
	 *         @type string $id                     Billing ID.
	 *         @type string $card_type              Type of the card ("UNKNOWN" "VISA" "MASTERCARD" "AMERICAN_EXPRESS"
	 *                                              "DISCOVER" "ELO").
	 *         @type string $status                 Status of the billing. ("UNSPECIFIED" "VALID" "INVALID" "PENDING"
	 *                                              "DELETED" "SECONDARY" "PENDING_SECONDARY").
	 *         @type string $advertiser_id          Advertiser ID of the billing.
	 *         @type string $payment_method_brand   Brand of the payment method. ("UNKNOWN" "VISA" "MASTERCARD"
	 *                                              "AMERICAN_EXPRESS" "DISCOVER" "SOFORT" "DINERS_CLUB" "ELO"
	 *                                              "CARTE_BANCAIRE").
	 *     }
	 *     @type string $bookmark Cursor used to fetch the next page of items.
	 * }
	 * @throws PinterestApiException If the request fails with other than 2xx status.
	 */
	public static function get_active_billing_profiles( string $ad_account_id ): array {
		return self::make_request(
			"ad_accounts/{$ad_account_id}/billing_profiles?is_active=true",
			'GET'
		);
	}

	/**
	 * Returns the list of discounts applied to the account.
	 *
	 * @link https://developers.pinterest.com/docs/api/v5/#operation/ads_credits_discounts/get
	 *
	 * @since x.x.x
	 *
	 * @param string $ad_account_id Pinterest Ad Account ID.
	 *
	 * @return array {
	 *     The list of discounts applied to the account.
	 *
	 *     @type array[] $items {
	 *         @type bool   $active                             True if the offer code is currently active.
	 *         @type string $advertiser_id                      Advertiser ID the offer was applied to.
	 *         @type int    $discountInMicroCurrency            The discount applied in the offer’s currency value.
	 *         @type string $discountCurrency                   Currency value for the discount.
	 *         @type string $title                              Human-readable title of the offer code.
	 *         @type int    $remainingDiscountInMicroCurrency   The credits left to spend.
	 *     }
	 *     @type string $bookmark Cursor used to fetch the next page of items.
	 * }
	 * @throws PinterestApiException If the request fails with other than 2xx status.
	 */
	public static function get_ads_credit_discounts( string $ad_account_id ): array {
		return self::make_request(
			"ad_accounts/{$ad_account_id}/ads_credit/discounts?page_size=25",
			'GET'
		);
	}
}
