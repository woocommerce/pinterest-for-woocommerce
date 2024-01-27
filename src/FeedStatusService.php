<?php
/**
 * Service class to handle & return Pinterest Feed Status
 *
 * @package     Automattic\WooCommerce\Pinterest
 * @version     1.3.0
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\API\Base;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class handling methods to return Pinterest Feed Status.
 */
class FeedStatusService {

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
	 * The error codes that are related to the feed itself, and not a specific product.
	 * Source: https://help.pinterest.com/en/business/article/data-source-ingestion
	 *
	 * @var array
	 */
	private const GLOBAL_ERROR_CODES = array(
		// Validation errors.
		'FETCH_INACTIVE_FEED_ERROR', // => 3,
		'FETCH_ERROR',               // => 100,
		'ENCODING_ERROR',            // => 101,
		'DELIMITER_ERROR',           // => 102,
		'REQUIRED_COLUMNS_MISSING',  // => 103,
		'INTERNAL_SERVICE_ERROR',    // => 128,
		'NO_VERIFIED_DOMAIN',        // => 140,
		'MALFORMED_XML',             // => 143,
		'FEED_TOO_SMALL',            // => 152,

		// Ingestion errors.
		'LARGE_PRODUCT_COUNT_DECREASE',
		'ACCOUNT_FLAGGED',
	);

	public const ERROR_MESSAGES = array(
		// Validation errors.
		'FETCH_ERROR'                       => 'Pinterest couldn\'t download your feed.',
		'FETCH_INACTIVE_FEED_ERROR'         => 'Your feed wasn\'t ingested because it hasn\'t changed in the previous 90 days.',
		'ENCODING_ERROR'                    => 'Your feed includes data with an unsupported encoding format.',
		'DELIMITER_ERROR'                   => 'Your feed includes data with formatting errors.',
		'REQUIRED_COLUMNS_MISSING'          => 'Your feed is missing some required column headers.',
		'DUPLICATE_PRODUCTS'                => 'Some products are duplicated.',
		'IMAGE_LINK_INVALID'                => 'Some image links are formatted incorrectly.',
		'ITEMID_MISSING'                    => 'Some items are missing an item id in their product metadata, those items will not be published.',
		'TITLE_MISSING'                     => 'Some items are missing a title in their product metadata, those items will not be published.',
		'DESCRIPTION_MISSING'               => 'Some items are missing a description in their product metadata, those items will not be published.',
		'PRODUCT_LINK_MISSING'              => 'Some items are missing a link URL in their product metadata, those items will not be published.',
		'IMAGE_LINK_MISSING'                => 'Some items are missing an image link URL in their product metadata, those items will not be published.',
		'AVAILABILITY_INVALID'              => 'Some items are missing an availability value in their product metadata, those items will not be published.',
		'PRODUCT_PRICE_INVALID'             => 'Some items have price formatting errors in their product metadata, those items will not be published.',
		'LINK_FORMAT_INVALID'               => 'Some link values are formatted incorrectly.',
		'PARSE_LINE_ERROR'                  => 'Your feed contains formatting errors for some items.',
		'ADWORDS_FORMAT_INVALID'            => 'Some adwords links contain too many characters.',
		'INTERNAL_SERVICE_ERROR'            => 'We experienced a technical difficulty and were unable to ingest your feed. The next ingestion will happen in 24 hours.',
		'NO_VERIFIED_DOMAIN'                => 'Your merchant domain needs to be claimed.',
		'ADULT_INVALID'                     => 'Some items have invalid adult values.',
		'IMAGE_LINK_LENGTH_TOO_LONG'        => 'Some items have image_link URLs that contain too many characters, so those items will not be published.',
		'INVALID_DOMAIN'                    => 'Some of your product link values don\'t match the verified domain associated with this account.',
		'FEED_LENGTH_TOO_LONG'              => 'Your feed contains too many items, some items will not be published.',
		'LINK_LENGTH_TOO_LONG'              => 'Some product links contain too many characters, those items will not be published.',
		'MALFORMED_XML'                     => 'Your feed couldn\'t be validated because the xml file is formatted incorrectly.',
		'PRICE_MISSING'                     => 'Some products are missing a price, those items will not be published.',
		'FEED_TOO_SMALL'                    => 'Your feed couldn\'t be validated because the file doesn\'t contain the minimum number of lines required.',
		'MAX_ITEMS_PER_ITEM_GROUP_EXCEEDED' => 'Some items exceed the maximum number of items per item group, those items will not be published.',
		'ITEM_MAIN_IMAGE_DOWNLOAD_FAILURE'  => 'Some items\' main images can\'t be found.',
		'PINJOIN_CONTENT_UNSAFE'            => 'Some items were not published because they don\'t meet Pinterest\'s Merchant Guidelines.',
		'BLOCKLISTED_IMAGE_SIGNATURE'       => 'Some items were not published because they don\'t meet Pinterest\'s Merchant Guidelines.',
		'LIST_PRICE_INVALID'                => 'Some items have list price formatting errors in their product metadata, those items will not be published.',
		'PRICE_CANNOT_BE_DETERMINED'        => 'Some items were not published because price cannot be determined. The price, list price, and sale price are all different, so those items will not be published.',

		// Validation warnings.
		'AD_LINK_FORMAT_WARNING'                 => 'Some items have ad links that are formatted incorrectly.',
		'AD_LINK_SAME_AS_LINK'                   => 'Some items have ad link URLs that are duplicates of the link URLs for those items.',
		'TITLE_LENGTH_TOO_LONG'                  => 'The title for some items were truncated because they contain too many characters.',
		'DESCRIPTION_LENGTH_TOO_LONG'            => 'The description for some items were truncated because they contain too many characters.',
		'GENDER_INVALID'                         => 'Some items have gender values that are formatted incorrectly, which may limit visibility in recommendations, search results and shopping experiences.',
		'AGE_GROUP_INVALID'                      => 'Some items have age group values that are formatted incorrectly, which may limit visibility in recommendations, search results and shopping experiences.',
		'SIZE_TYPE_INVALID'                      => 'Some items have size type values that are formatted incorrectly, which may limit visibility in recommendations, search results and shopping experiences.',
		'SIZE_SYSTEM_INVALID'                    => 'Some items have size system values which are not one of the supported size systems.',
		'LINK_FORMAT_WARNING'                    => 'Some items have an invalid product link which contains invalid UTM tracking paramaters.',
		'SALES_PRICE_INVALID'                    => 'Some items have sale price values that are higher than the original price of the item.',
		'PRODUCT_CATEGORY_DEPTH_WARNING'         => 'Some items only have 1 or 2 levels of google_product_category values, which may limit visibility in recommendations, search results and shopping experiences.',
		'ADWORDS_FORMAT_WARNING'                 => 'Some items have adwords_redirect links that are formatted incorrectly.',
		'ADWORDS_SAME_AS_LINK'                   => 'Some items have adwords_redirect URLs that are duplicates of the link URLs for those items.',
		'DUPLICATE_HEADERS'                      => 'Your feed contains duplicate headers.',
		'FETCH_SAME_SIGNATURE'                   => 'Ingestion completed early because there are no changes to your feed since the last successful update.',
		'ADDITIONAL_IMAGE_LINK_LENGTH_TOO_LONG'  => 'Some items have additional_image_link URLs that contain too many characters, so those items will not be published.',
		'ADDITIONAL_IMAGE_LINK_WARNING'          => 'Some items have additional_image_link URLs that are formatted incorrectly and will not be published with your items.',
		'IMAGE_LINK_WARNING'                     => 'Some items have image_link URLs that are formatted incorrectly and will not be published with those items.',
		'SHIPPING_INVALID'                       => 'Some items have shipping values that are formatted incorrectly.',
		'TAX_INVALID'                            => 'Some items have tax values that are formatted incorrectly.',
		'SHIPPING_WEIGHT_INVALID'                => 'Some items have invalid shipping_weight values.',
		'EXPIRATION_DATE_INVALID'                => 'Some items have expiration_date values that are formatted incorrectly, those items will be published without an expiration date.',
		'AVAILABILITY_DATE_INVALID'              => 'Some items have availability_date values that are formatted incorrectly, those items will be published without an availability date.',
		'SALE_DATE_INVALID'                      => 'Some items have sale_price_effective_date values that are formatted incorrectly, those items will be published without a sale date.',
		'WEIGHT_UNIT_INVALID'                    => 'Some items have weight_unit values that are formatted incorrectly, those items will be published without a weight unit.',
		'IS_BUNDLE_INVALID'                      => 'Some items have is_bundle values that are formatted incorrectly, those items will be published without being bundled with other products.',
		'UPDATED_TIME_INVALID'                   => 'Some items have updated_time values that are formatted incorrectly, those items will be published without an updated time.',
		'CUSTOM_LABEL_LENGTH_TOO_LONG'           => 'Some items have custom_label values that are too long, those items will be published without that custom label.',
		'PRODUCT_TYPE_LENGTH_TOO_LONG'           => 'Some items have product_type values that are too long, those items will be published without that product type.',
		'TOO_MANY_ADDITIONAL_IMAGE_LINKS'        => 'Some items have additional_image_link values that exceed the limit for additional images, those items will be published without some of your images.',
		'MULTIPACK_INVALID'                      => 'Some items have invalid multipack values.',
		'INDEXED_PRODUCT_COUNT_LARGE_DELTA'      => 'The product count has increased or decreased significantly compared to the last successful ingestion.',
		'ITEM_ADDITIONAL_IMAGE_DOWNLOAD_FAILURE' => 'Some items include additional_image_links that can\'t be found.',
		'OPTIONAL_PRODUCT_CATEGORY_MISSING'      => 'Some items are missing a google_product_category.',
		'OPTIONAL_PRODUCT_CATEGORY_INVALID'      => 'Some items include google_product_category values that are not formatted correctly according to the GPC taxonomy.',
		'OPTIONAL_CONDITION_MISSING'             => 'Some items are missing a condition value, which may limit visibility in recommendations, search results and shopping experiences.',
		'OPTIONAL_CONDITION_INVALID'             => 'Some items include condition values that are formatted incorrectly, which may limit visibility in recommendations, search results and shopping experiences.',
		'IOS_DEEP_LINK_INVALID'                  => 'Some items include invalid ios_deep_link values.',
		'ANDROID_DEEP_LINK_INVALID'              => 'Some items include invalid android_deep_link.',
		'UTM_SOURCE_AUTO_CORRECTED'              => 'Some items include utm_source values that are formatted incorrectly and have been automatically corrected.',
		'COUNTRY_DOES_NOT_MAP_TO_CURRENCY'       => 'Some items include a currency that doesn\'t match the usual currency for the location where that product is sold or shipped.',
		'MIN_AD_PRICE_INVALID'                   => 'Some items include min_ad_price values that are formatted incorrectly.',
		'GTIN_INVALID'                           => 'Some items include incorrectly formatted GTINs.',
		'INCONSISTENT_CURRENCY_VALUES'           => 'Some items include inconsistent currencies in price fields.',
		'SALES_PRICE_TOO_LOW'                    => 'Some items include sales price that is much lower than the list price.',
		'SHIPPING_WIDTH_INVALID'                 => 'Some items include incorrectly formatted shipping_width.',
		'SHIPPING_HEIGHT_INVALID'                => 'Some items include incorrectly formatted shipping_height.',
		'SALES_PRICE_TOO_HIGH'                   => 'Some items include a sales price that is higher than the list price. The sales price has been defaulted to the list price.',
		'MPN_INVALID'                            => 'Some items include incorrectly formatted MPNs.',

		// Ingestion errors.
		'LINE_LEVEL_INTERNAL_ERROR'    => 'We experienced a technical difficulty and were unable to ingest this some items. The next ingestion will happen in 24 hours.',
		'LARGE_PRODUCT_COUNT_DECREASE' => 'The product count has decreased by more than 99% compared to the last successful ingestion.',
		'ACCOUNT_FLAGGED'              => 'We detected an issue with your account and are not currently ingesting your items. Please review our policies at policy.pinterest.com/community-guidelines#section-spam or contact us at help.pinterest.com/contact for more information.',
		'IMAGE_LEVEL_INTERNAL_ERROR'   => 'We experienced a technical difficulty and were unable to download some images. The next download attempt will happen in 24 hours.',
		'IMAGE_FILE_NOT_ACCESSIBLE'    => 'Image files are unreadable. Please upload new files to continue.',
		'IMAGE_MALFORMED_URL'          => 'Image files are unreadable. Please check your link and upload new files to continue.',
		'IMAGE_FILE_NOT_FOUND'         => 'Image files are unreadable. Please upload new files to continue.',
		'IMAGE_INVALID_FILE'           => 'Image files are unreadable. Please upload new files to continue.',

		// Ingestion warnings.
		'ADDITIONAL_IMAGE_LEVEL_INTERNAL_ERROR' => 'We experienced a technical difficulty and were unable to download some additional images. The next download attempt will happen in 24 hours.',
		'ADDITIONAL_IMAGE_FILE_NOT_ACCESSIBLE'  => 'Additional image files are unreadable. Please upload new files to continue.',
		'ADDITIONAL_IMAGE_MALFORMED_URL'        => 'Additional image files are unreadable. Please check your link and upload new files to continue.',
		'ADDITIONAL_IMAGE_FILE_NOT_FOUND'       => 'Additional image files are unreadable. Please upload new files to continue.',
		'ADDITIONAL_IMAGE_INVALID_FILE'         => 'Additional image files are unreadable. Please upload new files to continue.',
		'HOTEL_PRICE_HEADER_IS_PRESENT'         => 'Price is not a supported column. Use base_price and sale_price instead.',

		// Ingestion info.
		'IN_STOCK'     => 'The number of ingested products that are in stock.',
		'OUT_OF_STOCK' => 'The number of ingested products that are in out of stock.',
		'PREORDER'     => 'The number of ingested products that are in preorder.',
	);

	const FEED_STATUS_NOT_REGISTERED = 'not_registered';

	const FEED_STATUS_ERROR_FETCHING_FEED = 'error_fetching_feed';

	const FEED_STATUS_COMPLETED = 'completed';

	const FEED_STATUS_COMPLETED_EARLY = 'completed_early';

	const FEED_STATUS_DISAPPROVED = 'disapproved';

	const FEED_STATUS_FAILED = 'failed';

	const FEED_STATUS_PROCESSING = 'processing';

	const FEED_STATUS_QUEUED_FOR_PROCESSING = 'queued_for_processing';

	const FEED_STATUS_UNDER_APPEAL = 'under_appeal';

	const FEED_STATUS_UNDER_REVIEW = 'under_review';

	/**
	 * Get the feed registration status.
	 *
	 * @return string The feed registration state. Possible values:
	 *                - not_registered: Feed is not yet configured on Pinterest.
	 *                - error_fetching_feed: Could not get feed info from Pinterest.
	 *                - inactive: The feed is registered but inactive at Pinterest.
	 *                - active: The feed is registered and active at Pinterest.
	 *                - deleted: The feed is registered but marked as deleted at Pinterest.
	 */
	public static function get_feed_registration_status(): string {
		$feed_id = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( empty( $feed_id ) ) {
			return static::FEED_STATUS_NOT_REGISTERED;
		}

		$feed = Feeds::get_feed_recent_processing_results( $feed_id );

		if ( empty( $feed ) ) {
			return static::FEED_STATUS_NOT_REGISTERED;
		}

		return strtolower( $feed['status'] ) ?? static::FEED_STATUS_NOT_REGISTERED;
	}

	/**
	 * Get the sync process / feed ingestion status via Pinterest API.
	 *
	 * @return string The feed ingestion state. Possible values:
	 *                - not_registered: Feed is not registered with Pinterest.
	 *                - error_fetching_feed: Error when trying to get feed report from Pinterest.
	 *                - no_workflows: Feed report contains no feed workflow.
	 *                - completed: Feed automatically pulled by Pinterest / Feed ingestion completed.
	 *                - completed_early: Feed automatically pulled by Pinterest / Feed ingestion completed early.
	 *                - processing: Feed ingestion is processing.
	 *                - under_review: Feed is under review.
	 *                - queued_for_processing: Feed is queued for processing.
	 *                - failed: Feed ingestion failed.
	 *                - unknown: Unknown feed ingestion status in workflow (i.e. API returned an unknown status).
	 *
	 * @throws Exception PHP Exception.
	 */
	public static function get_feed_sync_status(): string {
		$ad_account_id = Pinterest_For_WooCommerce()::get_setting( 'tracking_advertiser' );
		$feed_id       = FeedRegistration::get_locally_stored_registered_feed_id();

		if ( empty( $ad_account_id ) || empty( $feed_id ) ) {
			throw new Exception( 'not_registered' );
		}

		try {
			try {
				$feed_results = Feeds::get_feed_recent_processing_results( $feed_id );
			} catch ( Exception $e ) {
				throw new Exception( 'error_fetching_feed' );
			}
			if ( empty( $feed_results ) ) {
				throw new Exception( 'no_workflows' );
			}

			$status = strtolower( $feed_results['status'] ?? '' );
			if ( ! in_array(
				$status,
				array(
					'completed',
					'completed_early',
					'processing',
					'under_review',
					'queued_for_processing',
					'failed',
				),
				true
			) ) {
				throw new Exception( 'unknown' );
			}
		} catch ( Exception $e ) {
			$status = $e->getMessage();
		}

		return $status;
	}

	/**
	 * Gets the overview totals from the given processing results array.
	 *
	 * @param   array $processing_results The processing results array.
	 * @return  array A multidimensional array of numbers indicating the following stats about the workflow:
	 *                  - total: The total number of products in the feed.
	 *                  - not_synced: The number of products not synced to Pinterest.
	 *                  - warnings: The number of warnings.
	 *                  - errors: The number of errors.
	 *
	 * @since x.x.x
	 */
	public static function get_processing_result_overview_stats( array $processing_results ): array {
		$sums = array(
			'errors'   => 0,
			'warnings' => 0,
		);

		$sums['errors'] += array_sum( $processing_results['ingestion_details']['errors'] );
		$sums['errors'] += array_sum( $processing_results['validation_details']['errors'] );

		$sums['warnings'] += array_sum( $processing_results['ingestion_details']['warnings'] );
		$sums['warnings'] += array_sum( $processing_results['validation_details']['warnings'] );

		$original = $processing_results['product_counts']['original'] ?? 0;
		$ingested = $processing_results['product_counts']['ingested'] ?? 0;

		return array(
			'total'      => $original,
			'not_synced' => $original - $ingested,
			'warnings'   => $sums['warnings'],
			'errors'     => $sums['errors'],
		);
	}

	/**
	 * Gets the global error code for the given processing results.
	 *
	 * @param array $processing_results Recent processing results array.
	 * @return string
	 *
	 * @since x.x.x
	 */
	public static function get_processing_results_global_error( array $processing_results ): string {
		$error_code = '';

		foreach ( $processing_results['validation_details']['errors'] as $error_code => $count ) {
			if ( in_array( $error_code, self::GLOBAL_ERROR_CODES, true ) ) {
				/* Translators: The error message as returned by the Pinterest API */
				return sprintf( esc_html__( 'Pinterest says: %1$s', 'pinterest-for-woocommerce' ), static::ERROR_MESSAGES[ $error_code ] ?? '' );
			}
		}

		foreach ( $processing_results['ingestion_details']['errors'] as $error_code => $count ) {
			if ( in_array( $error_code, self::GLOBAL_ERROR_CODES, true ) ) {
				/* Translators: The error message as returned by the Pinterest API */
				return sprintf( esc_html__( 'Pinterest says: %1$s', 'pinterest-for-woocommerce' ), static::ERROR_MESSAGES[ $error_code ] ?? '' );
			}
		}

		return '';
	}
}
