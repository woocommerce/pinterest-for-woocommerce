<?php
/**
 * Pinterest for WooCommerce Ratings Notice State.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @since   x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\RatingsNotice;

defined( 'ABSPATH' ) || exit;

/**
 * Persists the ratings notice lifecycle and decides when to surface it.
 *
 * Backed by a single wp_options row so the contract stays easy to audit and
 * trivial to reset on deactivate.
 */
class State {

	const OPTION_KEY = 'pinterest_for_woocommerce_rating_notice_state';

	const STATE_UNSEEN            = 'unseen';
	const STATE_SNOOZED           = 'snoozed';
	const STATE_RATED             = 'rated';
	const STATE_DISMISSED_FOREVER = 'dismissed_forever';

	const CHANNEL_WPORG = 'wporg';
	const CHANNEL_WC    = 'wc';

	const SNOOZE_DAYS           = 90;
	const RATED_COOLDOWN_DAYS   = 60;
	const ELIGIBILITY_HOLD_DAYS = 7;
	const MAX_ASKS              = 2;

	/**
	 * Default shape for the persisted state.
	 *
	 * @return array
	 */
	private static function defaults(): array {
		return array(
			'state'             => self::STATE_UNSEEN,
			'state_changed_at'  => 0,
			'ask_count'         => 0,
			'first_eligible_at' => 0,
			'last_computed_at'  => 0,
			'next_channel'      => self::CHANNEL_WPORG,
		);
	}

	/**
	 * Load the current state, merged with defaults for forward compatibility.
	 *
	 * @return array
	 */
	public static function get(): array {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		return array_merge( self::defaults(), $raw );
	}

	/**
	 * Persist the given state, guarding against unknown keys.
	 *
	 * @param array $state Partial or full state array.
	 * @return void
	 */
	public static function save( array $state ): void {
		update_option( self::OPTION_KEY, array_merge( self::defaults(), $state ), false );
	}

	/**
	 * Record a continuous-eligibility tick. Stamps first_eligible_at on the first hit.
	 *
	 * @return void
	 */
	public static function mark_eligible(): void {
		$state = self::get();
		if ( 0 === (int) $state['first_eligible_at'] ) {
			$state['first_eligible_at'] = time();
		}
		$state['last_computed_at'] = time();
		self::save( $state );
	}

	/**
	 * Record that the notice was shown. Increments ask_count.
	 *
	 * @return void
	 */
	public static function record_shown(): void {
		$state                     = self::get();
		$state['ask_count']        = (int) $state['ask_count'] + 1;
		$state['state_changed_at'] = time();
		self::save( $state );
	}

	/**
	 * Snooze the notice. Promotes to a terminal dismissal once the ask cap is hit.
	 *
	 * @return void
	 */
	public static function snooze(): void {
		$state = self::get();
		if ( (int) $state['ask_count'] >= self::MAX_ASKS ) {
			$state['state'] = self::STATE_DISMISSED_FOREVER;
		} else {
			$state['state'] = self::STATE_SNOOZED;
		}
		$state['state_changed_at'] = time();
		self::save( $state );
	}

	/**
	 * Record that the CTA was clicked. Flips the channel for any subsequent ask.
	 *
	 * @return void
	 */
	public static function mark_clicked(): void {
		$state                     = self::get();
		$state['state']            = self::STATE_RATED;
		$state['state_changed_at'] = time();
		$state['next_channel']     = self::CHANNEL_WPORG === $state['next_channel']
			? self::CHANNEL_WC
			: self::CHANNEL_WPORG;
		self::save( $state );
	}

	/**
	 * Permanently silence the notice.
	 *
	 * @return void
	 */
	public static function dismiss_forever(): void {
		$state                     = self::get();
		$state['state']            = self::STATE_DISMISSED_FOREVER;
		$state['state_changed_at'] = time();
		self::save( $state );
	}

	/**
	 * Clear all persisted state.
	 *
	 * @return void
	 */
	public static function reset(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Decide whether the notice should currently be shown.
	 *
	 * Consumes an externally-computed eligibility bool so this class stays free
	 * of the gating rules (see {@see Eligibility}).
	 *
	 * @param bool $eligible Result of Eligibility::compute()['eligible'].
	 * @return array{should_show: bool, channel: string, ask_count: int, reason: string}
	 */
	public static function decide( bool $eligible ): array {
		$state = self::get();

		$base = array(
			'should_show' => false,
			'channel'     => (string) $state['next_channel'],
			'ask_count'   => (int) $state['ask_count'],
			'reason'      => '',
		);

		if ( ! $eligible ) {
			$base['reason'] = 'not_eligible';
			return $base;
		}

		if ( self::STATE_DISMISSED_FOREVER === $state['state'] ) {
			$base['reason'] = 'dismissed_forever';
			return $base;
		}

		if ( (int) $state['ask_count'] >= self::MAX_ASKS ) {
			$base['reason'] = 'ask_cap';
			return $base;
		}

		$now = time();

		if ( self::STATE_SNOOZED === $state['state']
			&& $now < (int) $state['state_changed_at'] + self::SNOOZE_DAYS * DAY_IN_SECONDS
		) {
			$base['reason'] = 'snoozed';
			return $base;
		}

		if ( self::STATE_RATED === $state['state']
			&& $now < (int) $state['state_changed_at'] + self::RATED_COOLDOWN_DAYS * DAY_IN_SECONDS
		) {
			$base['reason'] = 'rated_cooldown';
			return $base;
		}

		if ( 0 === (int) $state['ask_count'] ) {
			if ( 0 === (int) $state['first_eligible_at'] ) {
				$base['reason'] = 'awaiting_hold';
				return $base;
			}
			if ( $now < (int) $state['first_eligible_at'] + self::ELIGIBILITY_HOLD_DAYS * DAY_IN_SECONDS ) {
				$base['reason'] = 'awaiting_hold';
				return $base;
			}
		}

		$base['should_show'] = true;
		$base['reason']      = 'ok';
		return $base;
	}
}
