<?php

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

class CompleteOnboardingAfterThreeDays extends AbstractCompleteOnboarding {

	const DELAY     = 0;
	const NOTE_NAME = 'complete-onboarding-note-after-' . self::DELAY . '-days';

	protected function get_note_title(): string {
		return __( 'Reach more shoppers by connecting with Pinterest', 'pinterest-for-woocommerce' );
	}

	protected function get_note_content(): string {
		return __( 'Complete setting up Pinterest for WooCommerce to get your catalog in front of a large, engaged audience who are ready to buy! Create or connect your Pinterest business account to sync your product catalog and turn your products into shoppable Pins.', 'pinterest-for-woocommerce' );
	}

}
