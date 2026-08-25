<?php
/**
 * Cache-safe PageVisit tracking.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.0.0
 */

namespace Automattic\WooCommerce\Pinterest\Tracking;

use Automattic\WooCommerce\Pinterest\Tracking;
use Automattic\WooCommerce\Pinterest\Tracking\Data\None;
use Automattic\WooCommerce\Pinterest\Tracking\Data\Product;
use Automattic\WooCommerce\Pinterest\Tracking\Data\User;
use Automattic\WooCommerce\Pinterest\Utilities\CrawlerDetector;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates browser PageVisit events and handles their CAPI beacons.
 */
class PageVisit {

	/**
	 * Public AJAX action used by the PageVisit beacon.
	 */
	const AJAX_ACTION = 'pinterest_for_woocommerce_page_visit';

	/**
	 * Registers the public PageVisit beacon handlers.
	 *
	 * The endpoint intentionally has no nonce. A nonce rendered into cacheable
	 * HTML expires while the cached page remains live, and a logged-out nonce is
	 * shared by every visitor anyway. The endpoint performs no privileged action,
	 * accepts only a constrained PageVisit payload, and checks tracking settings
	 * and consent before forwarding the event.
	 *
	 * @return void
	 */
	public static function init_hooks() {
		add_action( 'wp_ajax_' . static::AJAX_ACTION, array( static::class, 'handle_request' ) );
		add_action( 'wp_ajax_nopriv_' . static::AJAX_ACTION, array( static::class, 'handle_request' ) );
	}

	/**
	 * Builds a cache-safe Pinterest Tag call and optional CAPI beacon.
	 *
	 * @param array $data Prepared Pinterest Tag PageVisit data.
	 *
	 * @return string JavaScript event code.
	 */
	public static function get_tag_event_code( array $data ) {
		unset( $data['event_id'] );

		$event_data = wp_json_encode( (object) $data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES );
		$event_data = $event_data ? $event_data : '{}';
		$capi_code  = '';

		if ( Pinterest_For_Woocommerce()::get_setting( 'track_conversions_capi' ) ) {
			$product_id = absint( $data['product_id'] ?? 0 );
			$capi_code  = sprintf(
				'var requestData=new FormData();requestData.append("action",%1$s);requestData.append("event_id",eventId);requestData.append("event_source_url",window.location.href);requestData.append("product_id",%2$d);var beaconSent=navigator.sendBeacon&&navigator.sendBeacon(%3$s,requestData);if(!beaconSent&&window.fetch){window.fetch(%3$s,{method:"POST",body:requestData,credentials:"same-origin",keepalive:true});}',
				wp_json_encode( static::AJAX_ACTION ),
				$product_id,
				wp_json_encode( admin_url( 'admin-ajax.php' ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES )
			);
		}

		return sprintf(
			'(function(){var eventId="page_"+(window.crypto&&window.crypto.randomUUID?window.crypto.randomUUID():Date.now().toString(36)+"_"+Math.random().toString(36).slice(2));var eventData=%1$s;eventData.event_id=eventId;pintrk("track","%2$s",eventData);%3$s}());',
			$event_data,
			Tracking::EVENT_PAGE_VISIT,
			$capi_code
		);
	}

	/**
	 * Handles a browser-generated PageVisit CAPI beacon.
	 *
	 * @return void
	 */
	public static function handle_request() {
		if ( ! Pinterest_For_Woocommerce()::get_setting( 'track_conversions' ) || ! Pinterest_For_Woocommerce()::get_setting( 'track_conversions_capi' ) ) {
			return;
		}

		/**
		 * Filters whether to disable tracking based on user consent.
		 *
		 * @since 1.4.21
		 *
		 * @param bool $disable_tracking Whether to disable tracking due to user consent.
		 */
		$is_tracking_disabled_user_consent = apply_filters( 'woocommerce_pinterest_disable_tracking_user_consent', false );

		/**
		 * Filters whether to disable tracking.
		 *
		 * @since 1.4.0
		 *
		 * @param bool $disable_tracking Whether to disable tracking based on consent conditions.
		 */
		if ( apply_filters( 'woocommerce_pinterest_disable_tracking', $is_tracking_disabled_user_consent ) ) {
			return;
		}

		if ( CrawlerDetector::is_crawler_request() ) {
			return;
		}

		// This is a public analytics beacon and intentionally is not nonce-gated.
		$event_id_raw   = $_POST['event_id'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$source_url_raw = $_POST['event_source_url'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$product_id_raw = $_POST['product_id'] ?? '0'; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		if ( ! is_string( $event_id_raw ) || ! is_string( $source_url_raw ) || ! is_string( $product_id_raw ) ) {
			return;
		}

		$event_id = sanitize_text_field( wp_unslash( $event_id_raw ) );
		if ( ! preg_match( '/^page_[A-Za-z0-9_-]{10,100}$/', $event_id ) ) {
			return;
		}

		$source_url = esc_url_raw( wp_unslash( $source_url_raw ) );
		$source_url = static::validate_source_url( $source_url );
		if ( ! $source_url ) {
			return;
		}

		$product_id = absint( wp_unslash( $product_id_raw ) );
		$data       = static::get_event_data( $event_id, $product_id );
		$user       = new User( \WC_Geolocation::get_ip_address(), wc_get_user_agent() );
		$tracker    = new Conversions( $user, $source_url );

		try {
			$tracker->track_event( Tracking::EVENT_PAGE_VISIT, $data );
		} catch ( Throwable $e ) {
			// Conversions::track_event() records the failure for support visibility.
			return;
		}
	}

	/**
	 * Validates that an event source URL belongs to this site.
	 *
	 * @param string $source_url Untrusted event source URL.
	 *
	 * @return string Valid source URL, or an empty string.
	 */
	private static function validate_source_url( string $source_url ) {
		$source_url  = esc_url_raw( $source_url, array( 'http', 'https' ) );
		$source_host = wp_parse_url( $source_url, PHP_URL_HOST );
		$home_host   = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $source_host || ! $home_host || strtolower( $source_host ) !== strtolower( $home_host ) ) {
			return '';
		}

		return $source_url;
	}

	/**
	 * Builds PageVisit data from the constrained beacon payload.
	 *
	 * @param string $event_id  Browser-generated event ID.
	 * @param int    $product_id Optional product ID.
	 *
	 * @return Product|None PageVisit event data.
	 */
	private static function get_event_data( string $event_id, int $product_id ) {
		$product = $product_id ? wc_get_product( $product_id ) : false;
		if ( ! $product instanceof \WC_Product ) {
			return new None( $event_id );
		}

		return new Product(
			$event_id,
			$product->get_id(),
			$product->get_name(),
			wc_get_product_category_list( $product->get_id() ),
			'brand',
			wc_get_price_to_display( $product ),
			get_woocommerce_currency(),
			1
		);
	}
}
