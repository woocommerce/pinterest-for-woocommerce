<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\FeedState;
use ReflectionMethod;
use ReflectionClass;
use Pinterest_For_Woocommerce;
use WP_UnitTestCase;

/**
 * Feed diagnostics preserve text without interpreting status markup.
 */
class FeedStateTest extends WP_UnitTestCase {

	/**
	 * @dataProvider processing_statuses
	 * @param string $status Remote status.
	 * @param string $expected Expected diagnostic fragment.
	 */
	public function test_processing_status_diagnostics( $status, $expected ) {
		$method = new ReflectionMethod( FeedState::class, 'map_status_into_extra_info' );
		$method->setAccessible( true );
		$info = $method->invoke(
			null,
			array(
				'status'             => $status,
				'created_at'         => gmdate( 'Y-m-d\TH:i:s', time() - HOUR_IN_SECONDS ),
				'product_counts'     => array( 'original' => 3 ),
				'validation_details' => array( 'errors' => array() ),
				'ingestion_details'  => array( 'errors' => array() ),
			)
		);
		if ( '' === $expected ) {
			$this->assertSame( '', $info );
		} else {
			$this->assertStringContainsString( $expected, $info );
		}
		$this->assertStringNotContainsString( '<', $info );
	}

	/**
	 * Sanitize every workflow producer while retaining safe formatting and links.
	 */
	public function test_workflow_html_uses_wordpress_allowlist() {
		$settings = Pinterest_For_Woocommerce::get_settings();
		Pinterest_For_Woocommerce::save_setting( 'account_data', array( 'verified_user_websites' => array( wp_parse_url( home_url(), PHP_URL_HOST ) ) ) );
		Pinterest_For_Woocommerce::save_setting( 'product_sync_enabled', true );
		$controller = ( new ReflectionClass( FeedState::class ) )->newInstanceWithoutConstructor();
		$hook       = 'pinterest_for_woocommerce_feed_state';
		$original   = isset( $GLOBALS['wp_filter'][ $hook ] ) ? clone $GLOBALS['wp_filter'][ $hook ] : null;
		if ( has_filter( $hook ) ) {
			remove_all_filters( $hook );
		}
		$rows    = array(
			array( 'extra_info' => '<strong>Summary</strong><br><em>Details</em> <code>NEW_STATE</code>' ),
			array( 'extra_info' => '<a href="https://example.test/feed.xml" target="_blank" rel="noopener">feed file</a>' ),
			array( 'extra_info' => '<neo-diagnostic>Plain diagnostic</neo-diagnostic>' ),
			array( 'label' => 'No extra information' ),
			array( 'extra_info' => array( '<em>Aggregate diagnostic</em>' ) ),
			array( 'extra_info' => 3 ),
		);
		$fixture = static function () use ( $rows ) {
			return array(
				'workflow' => $rows,
				'overview' => array( 'total' => 3 ),
			);
		};
		add_filter( $hook, $fixture );
		try {
			$result = $controller->get_feed_state();
			$this->assertSame( $rows[0], $result['workflow'][0] );
			$this->assertSame( $rows[1], $result['workflow'][1] );
			$this->assertSame( 'Plain diagnostic', $result['workflow'][2]['extra_info'] );
			$this->assertSame( $rows[3], $result['workflow'][3] );
			$this->assertSame( '', $result['workflow'][4]['extra_info'] );
			$this->assertSame( '3', $result['workflow'][5]['extra_info'] );
			$this->assertSame( array( 'total' => 3 ), $result['overview'] );
		} finally {
			remove_filter( $hook, $fixture );
			if ( null !== $original ) {
				$GLOBALS['wp_filter'][ $hook ] = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restore every caller-owned hook and its priority after the fixture.
			}
			Pinterest_For_Woocommerce::save_settings( $settings );
		}
	}

	/**
	 * Known states and inert unknown status text.
	 *
	 * @return array
	 */
	public function processing_statuses() {
		return array(
			'complete'      => array( 'COMPLETED', 'containing 3 products' ),
			'early'         => array( 'COMPLETED_EARLY', 'containing 3 products' ),
			'processing'    => array( 'PROCESSING', 'Overview numbers may be inaccurate' ),
			'failed'        => array( 'FAILED', 'ago' ),
			'queued'        => array( 'QUEUED_FOR_PROCESSING', '' ),
			'unknown text'  => array( 'NEW_STATE', 'NEW_STATE' ),
			'unknown label' => array( '<em>unrecognized</em> & pending', '&lt;em&gt;unrecognized&lt;/em&gt; &amp; pending' ),
		);
	}
}
