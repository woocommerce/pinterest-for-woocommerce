<?php
/**
 * Pinterest for WooCommerce CompleteOnboardingAfterThreeDays class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

defined( 'ABSPATH' ) || exit;

/**
 * Class CompleteOnboardingAfterSevenDays.
 *
 * Class responsible for admin Inbox notification after seven days from setup.
 *
 * @since x.x.x
 */
class CompleteOnboardingReminderAfterSevenDays extends AbstractCompleteOnboarding {

	const DELAY = 7;
	const NOTE_NAME = 'pinterest-complete-onboarding-note-after-' . self::DELAY . '-days';

	/**
	 * Get note title.
	 *
	 * @since x.x.x
	 * @return string Note title.
	 */
	protected function get_note_title(): string {
		return __( 'Reminder: Connect Pinterest for WooCommerce', 'pinterest-for-woocommerce' );
	}

	/**
	 * Get note content.
	 *
	 * @since x.x.x
	 * @return string Note content.
	 */
	protected function get_note_content(): string {
		return __( 'Finish setting up Pinterest for WooCommerce to reach over 400 million shoppers and inspire their next purchase.', 'pinterest-for-woocommerce' );
	}

}
