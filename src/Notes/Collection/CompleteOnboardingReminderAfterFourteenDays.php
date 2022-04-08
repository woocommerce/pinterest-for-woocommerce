<?php
/**
 * Pinterest for WooCommerce CompleteOnboardingAfterFourteenDays class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

defined( 'ABSPATH' ) || exit;

/**
 * Class CompleteOnboardingAfterFourteenDays.
 *
 * Class responsible for admin Inbox notification after fourteen days from setup.
 *
 * @since x.x.x
 */
class CompleteOnboardingReminderAfterFourteenDays extends CompleteOnboardingReminderAfterSevenDays {

	const DELAY     = 14;
	const NOTE_NAME = 'pinterest-complete-onboarding-note-after-' . self::DELAY . '-days';

}
