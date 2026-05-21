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
	 * Returns true when the current request looks like a crawler/bot.
	 *
	 * Detection is intentionally NOT memoized: the regex and the filter call
	 * are both cheap, and re-evaluating per call keeps the
	 * `pinterest_for_woocommerce_is_crawler_request` filter responsive to
	 * runtime changes (added/removed hooks, test fixtures) without requiring
	 * callers to remember to reset static state.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function is_crawler_request() {
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
		return (bool) apply_filters(
			'pinterest_for_woocommerce_is_crawler_request',
			$is_crawler,
			$user_agent
		);
	}
}
