<?php
/**
 * Crawler / bot detection helper.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Utilities;

use Automattic\Jetpack\Device_Detection\User_Agent_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether the current HTTP request appears to come from a crawler/bot.
 *
 * Primary detection is delegated to Jetpack's
 * `Automattic\Jetpack\Device_Detection\User_Agent_Info::is_bot_user_agent()`,
 * which ships an actively-maintained list of search-engine bots, AI crawlers,
 * social-network preview fetchers and other indexers. A small supplementary
 * regex catches programmatic / headless HTTP clients (curl, wget, Feedly,
 * PhantomJS, HeadlessChrome) that will never execute the browser-side
 * Pinterest Tag JS and are therefore still relevant to CAPI inflation, but
 * which Jetpack does not flag as bots.
 *
 * Tracking-domain code (Conversions API in particular) uses this to avoid
 * dispatching server-side events for requests that will never fire the
 * browser-side Pinterest Tag, which would otherwise inflate CAPI counts
 * relative to Tag counts.
 *
 * @since 1.4.27
 */
class CrawlerDetector {

	/**
	 * Supplementary User-Agent regex for programmatic / headless clients that
	 * Jetpack's bot list intentionally does not cover. These clients will not
	 * execute the browser-side Pinterest Tag JS, so any CAPI dispatch from
	 * them is by definition CAPI-only and inflates the CAPI vs Tag ratio.
	 *
	 * @var string
	 */
	const PROGRAMMATIC_CLIENT_REGEX = '/curl|wget|feed|phantom|headless/i';

	/**
	 * Returns true when the current request looks like a crawler/bot.
	 *
	 * Detection is intentionally NOT memoized: the underlying checks and the
	 * filter call are cheap, and re-evaluating per call keeps the
	 * `pinterest_for_woocommerce_is_crawler_request` filter responsive to
	 * runtime changes (added/removed hooks, test fixtures) without requiring
	 * callers to remember to reset static state.
	 *
	 * @since 1.4.27
	 *
	 * @return bool
	 */
	public static function is_crawler_request(): bool {
		// Unslashed raw value for the filter, so consumers see exactly what the
		// client sent. The sanitized copy below is only used internally for the
		// detection checks.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$raw_user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		$user_agent     = '' === $raw_user_agent ? '' : sanitize_text_field( $raw_user_agent );

		$is_crawler = '' !== $user_agent
			&& (
				User_Agent_Info::is_bot_user_agent( $user_agent )
				|| 1 === preg_match( self::PROGRAMMATIC_CLIENT_REGEX, $user_agent )
			);

		/**
		 * Filters whether the current request is treated as a crawler.
		 *
		 * When true, server-side tracking events (Conversions API) are not
		 * dispatched for the request. Browser-side rendering (Pinterest Tag
		 * JS) is intentionally NOT suppressed, so full-page caches that omit
		 * `Vary: User-Agent` do not serve bot-rendered HTML (missing Tag JS)
		 * to real users.
		 *
		 * @since 1.4.27
		 *
		 * @param bool   $is_crawler Whether the request looks like a crawler.
		 * @param string $user_agent The raw (unslashed, unsanitized) User-Agent header value.
		 */
		return (bool) apply_filters(
			'pinterest_for_woocommerce_is_crawler_request',
			$is_crawler,
			$raw_user_agent
		);
	}
}
