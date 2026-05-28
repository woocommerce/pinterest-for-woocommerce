<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Utilities;

use Automattic\WooCommerce\Pinterest\Utilities\CrawlerDetector;
use WP_UnitTestCase;

/**
 * Unit tests for CrawlerDetector.
 */
class CrawlerDetectorTest extends WP_UnitTestCase {

	/**
	 * Original $_SERVER['HTTP_USER_AGENT'] value, restored after each test.
	 *
	 * @var string|null
	 */
	private $original_user_agent;

	/**
	 * Per-test setup. Snapshots the inbound User-Agent for restoration.
	 */
	public function setUp(): void {
		parent::setUp();
		// Snapshot raw value for verbatim restoration in tearDown.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$this->original_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
	}

	/**
	 * Per-test cleanup. Restores the User-Agent and removes any added filters.
	 */
	public function tearDown(): void {
		if ( null === $this->original_user_agent ) {
			unset( $_SERVER['HTTP_USER_AGENT'] );
		} else {
			$_SERVER['HTTP_USER_AGENT'] = $this->original_user_agent;
		}

		remove_all_filters( 'pinterest_for_woocommerce_is_crawler_request' );

		parent::tearDown();
	}

	/**
	 * Missing User-Agent header should be treated as a non-crawler.
	 */
	public function test_empty_user_agent_is_not_crawler() {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$this->assertFalse( CrawlerDetector::is_crawler_request() );
	}

	/**
	 * Empty-string User-Agent should be treated as a non-crawler.
	 */
	public function test_empty_string_user_agent_is_not_crawler() {
		$_SERVER['HTTP_USER_AGENT'] = '';
		$this->assertFalse( CrawlerDetector::is_crawler_request() );
	}

	/**
	 * @dataProvider crawler_user_agents
	 *
	 * @param string $user_agent The user agent string to test.
	 */
	public function test_crawler_user_agents_are_detected( $user_agent ) {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		$this->assertTrue(
			CrawlerDetector::is_crawler_request(),
			"Expected '{$user_agent}' to be detected as a crawler."
		);
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function crawler_user_agents() {
		return array(
			'Googlebot' => array( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ),
			'Bingbot'   => array( 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' ),
			'curl'      => array( 'curl/8.4.0' ),
			'wget'      => array( 'Wget/1.21.4' ),
			'spider'    => array( 'Mozilla/5.0 (compatible; YandexSpider/3.0)' ),
			'slurp'     => array( 'Mozilla/5.0 (compatible; Yahoo! Slurp; http://help.yahoo.com/help/us/ysearch/slurp)' ),
			'crawl'     => array( 'CCBot/2.0 (https://commoncrawl.org/faq/)' ),
			'feed'      => array( 'Feedly/1.0 (+http://www.feedly.com/fetcher.html)' ),
			'headless'  => array( 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/120.0.0.0 Safari/537.36' ),
			'phantom'   => array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X) AppleWebKit/538.1 (KHTML, like Gecko) PhantomJS/2.1.1 Safari/538.1' ),
		);
	}

	/**
	 * @dataProvider human_user_agents
	 *
	 * @param string $user_agent The user agent string to test.
	 */
	public function test_human_user_agents_are_not_detected( $user_agent ) {
		$_SERVER['HTTP_USER_AGENT'] = $user_agent;
		$this->assertFalse(
			CrawlerDetector::is_crawler_request(),
			"Did not expect '{$user_agent}' to be detected as a crawler."
		);
	}

	/**
	 * @return array<string, array<string>>
	 */
	public function human_user_agents() {
		return array(
			'Chrome desktop'  => array( 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' ),
			'Firefox desktop' => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0' ),
			'Safari iPhone'   => array( 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1' ),
			'Edge'            => array( 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0' ),
		);
	}

	/**
	 * The filter must be able to flip a crawler classification to non-crawler.
	 */
	public function test_filter_can_override_crawler_to_false() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

		add_filter( 'pinterest_for_woocommerce_is_crawler_request', '__return_false' );

		$this->assertFalse( CrawlerDetector::is_crawler_request() );
	}

	/**
	 * The filter must be able to flag a human UA as a crawler.
	 */
	public function test_filter_can_override_human_to_crawler() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0';

		add_filter( 'pinterest_for_woocommerce_is_crawler_request', '__return_true' );

		$this->assertTrue( CrawlerDetector::is_crawler_request() );
	}

	/**
	 * The filter receives both the detection result and the raw user agent.
	 */
	public function test_filter_receives_user_agent_and_classification() {
		$ua                         = 'Mozilla/5.0 (compatible; CustomScanner/1.0)';
		$_SERVER['HTTP_USER_AGENT'] = $ua;
		$captured                   = array();

		add_filter(
			'pinterest_for_woocommerce_is_crawler_request',
			function ( $is_crawler, $user_agent ) use ( &$captured ) {
				$captured = array(
					'is_crawler' => $is_crawler,
					'user_agent' => $user_agent,
				);
				return $is_crawler;
			},
			10,
			2
		);

		CrawlerDetector::is_crawler_request();

		$this->assertFalse( $captured['is_crawler'] );
		$this->assertSame( $ua, $captured['user_agent'] );
	}

	/**
	 * The filter must receive the RAW (unsanitized) User-Agent so consumers can
	 * match against the exact string the client sent. Using a UA with characters
	 * that sanitize_text_field would strip (HTML-tag-like sequence) proves the
	 * value passed to the filter is not pre-sanitized.
	 */
	public function test_filter_receives_raw_unsanitized_user_agent() {
		$raw_ua                     = 'Mozilla/5.0 <weird> Scanner/1.0';
		$_SERVER['HTTP_USER_AGENT'] = $raw_ua;
		$captured                   = null;

		add_filter(
			'pinterest_for_woocommerce_is_crawler_request',
			function ( $is_crawler, $user_agent ) use ( &$captured ) {
				$captured = $user_agent;
				return $is_crawler;
			},
			10,
			2
		);

		CrawlerDetector::is_crawler_request();

		// The raw value must reach the filter unchanged; sanitize_text_field
		// would have stripped the `<weird>` token.
		$this->assertSame( $raw_ua, $captured );
	}

	/**
	 * Filter changes must take effect immediately on the next call (no static
	 * caching that would hold the first result).
	 */
	public function test_filter_changes_take_effect_without_reset() {
		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120.0.0.0';

		$this->assertFalse( CrawlerDetector::is_crawler_request() );

		add_filter( 'pinterest_for_woocommerce_is_crawler_request', '__return_true' );
		$this->assertTrue( CrawlerDetector::is_crawler_request() );

		remove_all_filters( 'pinterest_for_woocommerce_is_crawler_request' );
		$this->assertFalse( CrawlerDetector::is_crawler_request() );
	}
}
