<?php

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

class CompleteOnboardingReminderAfterSevenDays extends AbstractCompleteOnboarding {

	const DELAY = 7;

	protected function get_note_title(): string {
		return __( 'Reminder: Connect Pinterest for WooCommerce', 'pinterest-for-woocommerce' );
	}

	protected function get_note_content(): string {
		return __( 'Finish setting up Pinterest for WooCommerce to reach over 400 million shoppers and inspire their next purchase', 'pinterest-for-woocommerce' );
	}

}
