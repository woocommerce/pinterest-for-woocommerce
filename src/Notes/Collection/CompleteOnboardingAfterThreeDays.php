<?php
/**
 * Pinterest for WooCommerce CompleteOnboardingAfterThreeDaysclass.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

defined( 'ABSPATH' ) || exit;

/**
 * Class CompleteOnboardingAfterThreeDays.
 *
 * Class responsible for admin Inbox notification after three days from setup.
 *
 * @since x.x.x
 */
class CompleteOnboardingAfterThreeDays extends AbstractCompleteOnboarding {

	const DELAY     = 0;
	const NOTE_NAME = 'complete-onboarding-note-after-' . self::DELAY . '-days';

	/**
	 * Get note title.
	 *
	 * @since x.x.x
	 * @return string Note title.
	 */
	protected function get_note_title(): string {
		return __( 'Reach more shoppers by connecting with Pinterest', 'pinterest-for-woocommerce' );
	}


	/**
	 * Get note content.
	 *
	 * @since x.x.x
	 * @return string Note content.
	 */
	protected function get_note_content(): string {
		return __( 'Complete setting up Pinterest for WooCommerce to get your catalog in front of a large, engaged audience who are ready to buy! Create or connect your Pinterest business account to sync your product catalog and turn your products into shoppable Pins.', 'pinterest-for-woocommerce' );
	}

}
