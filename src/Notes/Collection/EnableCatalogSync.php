<?php

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Pinterest\ProductSync;
use Automattic\WooCommerce\Pinterest\Utilities;

class EnableCatalogSync extends AbstractNote {

	const NOTE_NAME = 'enable-catalog-sync';

	public function should_be_added(): bool {
		if ( ! Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		if ( ProductSync::is_product_sync_enabled() ) {
			return false;
		}

		if ( self::note_exists() ) {
			return false;
		}

		// Are we there yet? We want to try three days after the account was connected.
		if ( time() < ( DAY_IN_SECONDS * 3 + Utilities\get_account_connection_timestamp() ) ) {
			return false;
		}

		// All preconditions are met, we can send the note.
		return true;
	}


	protected function get_note_title(): string {
		return __( 'Notice: Your products aren’t synced on Pinterest', 'pinterest-for-woocommerce' );
	}

	protected function get_note_content(): string {
		return __( 'Your Catalog sync with Pinterest has been disabled. Select “Enable Product Sync” to sync your products and reach shoppers on Pinterest.', 'pinterest-for-woocommerce' );
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page
	 */
	protected function add_action( $note ) {
		$note->add_action(
			'goto-pinterest-settings',
			__( 'Review issues', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/settings' )
		);
	}

}
