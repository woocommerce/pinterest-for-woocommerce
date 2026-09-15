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
	 * Seconds after rendering during which the PHP event ID is considered fresh.
	 */
	const FRESH_WINDOW = 300;

	/**
	 * Prints the PageVisit Tag call and, when needed, the CAPI beacon.
	 *
	 * The PHP event ID is reused in the browser only when the server already
	 * sent the CAPI event and the HTML is less than FRESH_WINDOW seconds old, so
	 * the Tag and CAPI events for an uncached render share one ID. Older
	 * full-page cache hits and crawler-rendered HTML generate a fresh ID and
	 * send it through the beacon. Two bounded failure modes follow from the
	 * time gate: a visitor whose clock is off by more than FRESH_WINDOW sends
	 * one extra unmatched CAPI event, and visitors served the cached HTML within
	 * FRESH_WINDOW of its creation share the PHP ID for the Tag and send no beacon.
	 *
	 * @since 1.5.1
	 *
	 * @param Data $data        PageVisit event data.
	 * @param bool $server_sent Whether the Conversions tracker sent the event during rendering.
	 * @param bool $tag_active  Whether the Pinterest Tag is active on the page.
	 *
	 * @return void
	 */
	public static function print_script( Data $data, bool $server_sent, bool $tag_active ) {
		$capi_enabled = (bool) Pinterest_For_Woocommerce()::get_setting( 'track_conversions_capi' );
		if ( ! $capi_enabled && ! $tag_active ) {
			return;
		}

		$tag_code = '';
		if ( $tag_active ) {
			$event_data = Tag::get_page_visit_data( $data );
			unset( $event_data['event_id'] );
			$tag_code = sprintf(
				'if(window.pintrk){var eventData=%1$s;eventData.event_id=eventId;pintrk("track","%2$s",eventData);}',
				wp_json_encode( (object) $event_data, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				Tracking::EVENT_PAGE_VISIT
			);
		}

		$beacon_code = '';
		if ( $capi_enabled ) {
			$product_fields = '';
			if ( $data instanceof Product ) {
				$product_fields = sprintf(
					'requestData.append("product_id","%1$d");requestData.append("product_token","%2$s");',
					$data->get_id(),
					static::get_product_token( (int) $data->get_id() )
				);
			}
			$beacon_code = sprintf(
				'if(!fresh){var requestData=new FormData();requestData.append("action",%1$s);requestData.append("event_id",eventId);requestData.append("event_source_url",window.location.href);%3$svar beaconSent=navigator.sendBeacon&&navigator.sendBeacon(%2$s,requestData);if(!beaconSent&&window.fetch){window.fetch(%2$s,{method:"POST",body:requestData,credentials:"same-origin",keepalive:true});}}',
				wp_json_encode( static::AJAX_ACTION ),
				wp_json_encode( admin_url( 'admin-ajax.php' ), JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
				$product_fields
			);
		}

		$script = sprintf(
			'(function(){try{var serverId=%1$s,renderedAt=%2$d,serverSent=%3$d;var fresh=serverSent&&Math.abs(Date.now()/1000-renderedAt)<%4$d;var eventId=fresh?serverId:"page_"+(window.crypto&&window.crypto.randomUUID?window.crypto.randomUUID():Date.now().toString(36)+"_"+Math.random().toString(36).slice(2));%5$s%6$s}catch(e){}}());',
			wp_json_encode( $data->get_event_id() ),
			time(),
			(int) $server_sent,
			static::FRESH_WINDOW,
			$tag_code,
			$beacon_code
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON encoded values inside a script tag.
		echo '<script>' . $script . '</script>';
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

		if ( ! is_string( $event_id_raw ) || ! is_string( $source_url_raw ) ) {
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

		$data    = static::get_event_data( $event_id, static::get_product_id( $source_url ) );
		$user    = new User( \WC_Geolocation::get_ip_address(), wc_get_user_agent() );
		$tracker = new Conversions( $user, $source_url );

		try {
			$tracker->track_event( Tracking::EVENT_PAGE_VISIT, $data );
		} catch ( Throwable $e ) {
			// Conversions::track_event() records the failure for support visibility.
			return;
		}
	}

	/**
	 * Builds the token that lets the beacon vouch for a server-rendered product ID.
	 *
	 * @since 1.5.1
	 *
	 * @param int $product_id Product ID rendered on the page.
	 *
	 * @return string
	 */
	private static function get_product_token( int $product_id ) {
		return hash_hmac( 'sha256', 'pfw_page_visit|' . $product_id, wp_salt() );
	}

	/**
	 * Resolves the product ID for a beacon.
	 *
	 * The posted product ID is accepted only with a valid token; otherwise the
	 * source URL is resolved, which fails on many permalink setups and yields 0.
	 *
	 * @since 1.5.1
	 *
	 * @param string $source_url Validated event source URL.
	 *
	 * @return int Product ID, or 0 when unknown.
	 */
	private static function get_product_id( string $source_url ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		$product_id = is_scalar( $_POST['product_id'] ?? null ) ? absint( $_POST['product_id'] ) : 0;
		$token      = $_POST['product_token'] ?? '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput

		if ( $product_id && is_string( $token ) ) {
			if ( hash_equals( static::get_product_token( $product_id ), $token ) ) {
				return $product_id;
			}
		}

		return url_to_postid( $source_url );
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
