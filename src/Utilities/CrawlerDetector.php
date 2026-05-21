<?php
/**
 * Crawler / bot detection helper.
 *
 * @package Automattic\WooCommerce\Pinterest
 */

namespace Automattic\WooCommerce\Pinterest\Utilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects whether the current HTTP request appears to come from a crawler/bot.
 *
 * WordPress core does not ship a public crawler-detection helper, so we use a
 * conservative User-Agent match against the most common crawler/bot tokens.
 *
 * Tracking-domain code (Conversions API in particular) uses this to avoid
 * dispatching server-side events for requests that will never fire the
 * browser-side Pinterest Tag, which would otherwise inflate CAPI counts
 * relative to Tag counts.
 *
 * @since x.x.x
 */
class CrawlerDetector {

	/**
	 * Per-request memoized result. Null = not yet computed.
	 *
	 * The detection runs against `$_SERVER['HTTP_USER_AGENT']`, which is
	 * immutable for a given PHP request, so the result is safe to cache for
	 * the lifetime of the request. Multiple tracking hooks call this on the
	 * same request (page_visit, view_category, search and any future caller),
	 * so the cache avoids re-running the regex and re-applying the filter.
	 *
	 * @var bool|null
	 */
	private static $cached_result = null;

	/**
	 * Returns true when the current request looks like a crawler/bot.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function is_crawler_request() {
		if ( null !== self::$cached_result ) {
			return self::$cached_result;
		}

		$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';

		$is_crawler = '' !== $user_agent
			&& (bool) preg_match( '/bot|crawl|spider|slurp|curl|wget|feed|phantom|headless/i', $user_agent );

		/**
		 * Filters whether the current request is treated as a crawler.
		 *
		 * When true, server-side tracking events (Conversions API) are not
		 * dispatched for the request. Browser-side rendering (Pinterest Tag
		 * JS) is intentionally NOT suppressed, so full-page caches that omit
		 * `Vary: User-Agent` do not serve bot-rendered HTML (missing Tag JS)
		 * to real users.
		 *
		 * @since x.x.x
		 *
		 * @param bool   $is_crawler Whether the request looks like a crawler.
		 * @param string $user_agent The raw User-Agent header value.
		 */
		self::$cached_result = (bool) apply_filters(
			'pinterest_for_woocommerce_is_crawler_request',
			$is_crawler,
			$user_agent
		);

		return self::$cached_result;
	}

	/**
	 * Resets the memoized result. Intended for use in tests only.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function reset_cache() {
		self::$cached_result = null;
	}
}
