<?php
/**
 * Tests for the Ratings Notice REST endpoint.
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Api;

use Automattic\WooCommerce\Pinterest\API\RatingsNotice as RatingsNoticeApi;
use Automattic\WooCommerce\Pinterest\RatingsNotice\Eligibility;
use Automattic\WooCommerce\Pinterest\RatingsNotice\State;
use WP_REST_Request;
use WP_Test_REST_TestCase;

/**
 * Integration-style tests for the REST routes and state transitions.
 */
class RatingsNoticeTest extends WP_Test_REST_TestCase {

	/**
	 * Reset persistent state and the eligibility cache before each case.
	 */
	public function setUp(): void {
		parent::setUp();
		State::reset();
		RatingsNoticeApi::flush_cache();
		remove_all_filters( 'pinterest_for_woocommerce_rating_notice_eligibility' );
	}

	/**
	 * Seed eligibility via the public filter and clear any stale cache.
	 *
	 * @param bool $eligible Desired eligibility outcome.
	 */
	private function seed_eligibility( bool $eligible ): void {
		RatingsNoticeApi::flush_cache();
		add_filter(
			'pinterest_for_woocommerce_rating_notice_eligibility',
			static function () use ( $eligible ) {
				return array(
					'eligible' => $eligible,
					'reason'   => $eligible ? Eligibility::REASON_OK : Eligibility::REASON_SETUP_INCOMPLETE,
				);
			}
		);
	}

	/**
	 * REST routes are registered.
	 */
	public function test_rating_notice_route_registered() {
		$routes = rest_get_server()->get_routes();
		$this->assertArrayHasKey( '/pinterest/v1/rating_notice', $routes );
	}

	/**
	 * Unauthenticated and unauthorized callers are rejected.
	 */
	public function test_endpoint_rejects_access() {
		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/rating_notice' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 401, $response->get_status() );

		$user = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user );

		$request  = new WP_REST_Request( 'POST', '/pinterest/v1/rating_notice' );
		$response = rest_get_server()->dispatch( $request );
		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * A snooze POST transitions state to snoozed and reports suppression.
	 */
	public function test_post_snooze_transitions_state() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$this->seed_eligibility( true );
		State::save(
			array(
				'first_eligible_at' => time() - ( 10 * DAY_IN_SECONDS ),
				'ask_count'         => 1,
			)
		);

		$request = new WP_REST_Request( 'POST', '/pinterest/v1/rating_notice' );
		$request->set_param( 'action', RatingsNoticeApi::ACTION_SNOOZE );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( State::STATE_SNOOZED, State::get()['state'] );

		$data = $response->get_data();
		$this->assertFalse( $data['should_show'] );
		$this->assertSame( 'snoozed', $data['reason'] );
	}

	/**
	 * A dismiss_forever POST is terminal.
	 */
	public function test_post_dismiss_forever_is_terminal() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$this->seed_eligibility( true );

		$request = new WP_REST_Request( 'POST', '/pinterest/v1/rating_notice' );
		$request->set_param( 'action', RatingsNoticeApi::ACTION_DISMISS_FOREVER );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$this->assertSame( State::STATE_DISMISSED_FOREVER, State::get()['state'] );
	}

	/**
	 * A clicked POST records the click and flips the next channel.
	 */
	public function test_post_clicked_flips_channel() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$this->seed_eligibility( true );

		$request = new WP_REST_Request( 'POST', '/pinterest/v1/rating_notice' );
		$request->set_param( 'action', RatingsNoticeApi::ACTION_CLICKED );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );

		$state = State::get();
		$this->assertSame( State::STATE_RATED, $state['state'] );
		$this->assertSame( State::CHANNEL_WC, $state['next_channel'] );
	}

	/**
	 * Unknown actions are rejected with a 400.
	 */
	public function test_post_unknown_action_returns_400() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$request = new WP_REST_Request( 'POST', '/pinterest/v1/rating_notice' );
		$request->set_param( 'action', 'nope' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 400, $response->get_status() );
	}

	/**
	 * A successful GET returns should_show=true and increments ask_count.
	 */
	public function test_get_shows_notice_and_increments_ask_count() {
		$user = $this->factory->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user );

		$this->seed_eligibility( true );
		State::save( array( 'first_eligible_at' => time() - ( 10 * DAY_IN_SECONDS ) ) );

		$request  = new WP_REST_Request( 'GET', '/pinterest/v1/rating_notice' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['should_show'] );
		$this->assertSame( 1, (int) State::get()['ask_count'] );
	}

	/**
	 * The refresh handler is attached to the daily heartbeat.
	 */
	public function test_refresh_cache_is_hooked_to_daily_heartbeat() {
		$this->assertNotFalse(
			has_action(
				\Automattic\WooCommerce\Pinterest\Heartbeat::DAILY,
				array( RatingsNoticeApi::class, 'refresh_cache' )
			)
		);
	}

	/**
	 * Refreshing the cache stamps first_eligible_at on a positive compute.
	 */
	public function test_refresh_cache_stamps_first_eligible_when_eligible() {
		$this->seed_eligibility( true );

		$this->assertSame( 0, (int) State::get()['first_eligible_at'] );

		RatingsNoticeApi::refresh_cache();

		$this->assertGreaterThan( 0, (int) State::get()['first_eligible_at'] );
	}
}
