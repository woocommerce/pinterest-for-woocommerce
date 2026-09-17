<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\FeedState;
use ReflectionMethod;
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
