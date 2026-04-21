<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\RatingsNotice;

use Automattic\WooCommerce\Pinterest\RatingsNotice\State;

/**
 * Unit tests for the ratings notice state machine.
 *
 * @covers \Automattic\WooCommerce\Pinterest\RatingsNotice\State
 */
class StateTest extends \WP_UnitTestCase {

	/**
	 * Reset persisted state between tests.
	 */
	public function setUp(): void {
		parent::setUp();
		State::reset();
	}

	/**
	 * Default state is unseen with ask_count 0 and WP.org as next channel.
	 */
	public function test_defaults_are_applied() {
		$state = State::get();

		$this->assertSame( State::STATE_UNSEEN, $state['state'] );
		$this->assertSame( 0, (int) $state['ask_count'] );
		$this->assertSame( State::CHANNEL_WPORG, $state['next_channel'] );
	}

	/**
	 * mark_eligible stamps first_eligible_at only once.
	 */
	public function test_mark_eligible_stamps_first_eligible_once() {
		State::mark_eligible();
		$first = State::get()['first_eligible_at'];
		$this->assertGreaterThan( 0, $first );

		// Second tick should not move the timestamp backward or forward.
		State::mark_eligible();
		$this->assertSame( $first, State::get()['first_eligible_at'] );
	}

	/**
	 * record_shown increments the ask counter.
	 */
	public function test_record_shown_increments_ask_count() {
		State::record_shown();
		State::record_shown();
		$this->assertSame( 2, (int) State::get()['ask_count'] );
	}

	/**
	 * Decide withholds the notice before the 7-day eligibility hold elapses.
	 */
	public function test_decide_withholds_during_hold_window() {
		State::save(
			array(
				'first_eligible_at' => time() - ( 2 * DAY_IN_SECONDS ),
			)
		);

		$decision = State::decide( true );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'awaiting_hold', $decision['reason'] );
	}

	/**
	 * Decide surfaces the notice after the hold window elapses.
	 */
	public function test_decide_shows_after_hold_window() {
		State::save(
			array(
				'first_eligible_at' => time() - ( 10 * DAY_IN_SECONDS ),
			)
		);

		$decision = State::decide( true );

		$this->assertTrue( $decision['should_show'] );
		$this->assertSame( State::CHANNEL_WPORG, $decision['channel'] );
	}

	/**
	 * Decide withholds inside the 90-day snooze cooldown.
	 */
	public function test_decide_withholds_while_snoozed() {
		State::save(
			array(
				'state'             => State::STATE_SNOOZED,
				'state_changed_at'  => time() - ( 10 * DAY_IN_SECONDS ),
				'first_eligible_at' => time() - ( 30 * DAY_IN_SECONDS ),
				'ask_count'         => 1,
			)
		);

		$decision = State::decide( true );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'snoozed', $decision['reason'] );
	}

	/**
	 * Decide re-surfaces after the snooze window elapses.
	 */
	public function test_decide_resumes_after_snooze_elapses() {
		State::save(
			array(
				'state'             => State::STATE_SNOOZED,
				'state_changed_at'  => time() - ( 95 * DAY_IN_SECONDS ),
				'first_eligible_at' => time() - ( 200 * DAY_IN_SECONDS ),
				'ask_count'         => 1,
			)
		);

		$decision = State::decide( true );

		$this->assertTrue( $decision['should_show'] );
	}

	/**
	 * Hitting the ask cap silences the notice.
	 */
	public function test_decide_blocks_after_ask_cap() {
		State::save(
			array(
				'first_eligible_at' => time() - ( 100 * DAY_IN_SECONDS ),
				'ask_count'         => State::MAX_ASKS,
			)
		);

		$decision = State::decide( true );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'ask_cap', $decision['reason'] );
	}

	/**
	 * Dismiss-forever is terminal regardless of eligibility.
	 */
	public function test_decide_is_terminal_after_dismiss_forever() {
		State::dismiss_forever();

		$decision = State::decide( true );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'dismissed_forever', $decision['reason'] );
	}

	/**
	 * Snooze promotes to dismissed_forever once the ask cap is already reached.
	 */
	public function test_snooze_at_ask_cap_promotes_to_dismissed_forever() {
		State::save( array( 'ask_count' => State::MAX_ASKS ) );
		State::snooze();

		$this->assertSame( State::STATE_DISMISSED_FOREVER, State::get()['state'] );
	}

	/**
	 * Clicking flips the channel for the next ask.
	 */
	public function test_mark_clicked_flips_channel() {
		State::mark_clicked();
		$this->assertSame( State::STATE_RATED, State::get()['state'] );
		$this->assertSame( State::CHANNEL_WC, State::get()['next_channel'] );

		State::mark_clicked();
		$this->assertSame( State::CHANNEL_WPORG, State::get()['next_channel'] );
	}

	/**
	 * Rated cooldown suppresses the notice before the 60-day window elapses.
	 */
	public function test_decide_withholds_during_rated_cooldown() {
		State::save(
			array(
				'state'             => State::STATE_RATED,
				'state_changed_at'  => time() - ( 10 * DAY_IN_SECONDS ),
				'first_eligible_at' => time() - ( 200 * DAY_IN_SECONDS ),
				'ask_count'         => 1,
			)
		);

		$decision = State::decide( true );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'rated_cooldown', $decision['reason'] );
	}

	/**
	 * An ineligible evaluation is rejected regardless of state.
	 */
	public function test_decide_withholds_when_not_eligible() {
		State::save(
			array(
				'first_eligible_at' => time() - ( 200 * DAY_IN_SECONDS ),
			)
		);

		$decision = State::decide( false );

		$this->assertFalse( $decision['should_show'] );
		$this->assertSame( 'not_eligible', $decision['reason'] );
	}
}
