<?php
/**
 * Pinterest API V5 class
 *
 * @class       Pinterest_For_Woocommerce_API
 * @version     x.x.x
 * @package     Pinterest_For_WordPress/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\API;

use Automattic\WooCommerce\Pinterest\PinterestApiException;
use Automattic\WooCommerce\Pinterest\PinterestApiException as ApiException;
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
	 * @throws ApiException Throws 403 and 500 exceptions.
	 */
	public static function get_account_info() {
		return self::make_request( 'user_account', 'GET' );
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
	 * @throws ApiException Throws 403 and 500 exceptions.
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
	 * @throws ApiException Throws 500 exception in case of unexpected error.
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
	 * @throws ApiException|Exception Throws 500 exception.
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
	 * @throws ApiException Throws 500 exception in case of unexpected error.
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
	 * @throws ApiException|Exception Throws 500 exception.
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
	 * @throws PinterestApiException If the request fails with 500 status.
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
}
