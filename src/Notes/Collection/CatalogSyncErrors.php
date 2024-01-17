<?php
/**
 * Pinterest for WooCommerce CatalogSyncErrors class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version 1.1.0
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Pinterest\FeedRegistration;
use Automattic\WooCommerce\Pinterest\Feeds;
use Automattic\WooCommerce\Pinterest\ProductSync;
use Automattic\WooCommerce\Pinterest\Utilities\Utilities;

/**
 * Class CatalogSyncErrors.
 *
 * Class responsible for admin Inbox notification after successful connection but
 * when the catalog ingestion fails.
 *
 * @since 1.1.0
 */
class CatalogSyncErrors extends AbstractNote {

	const NOTE_NAME = 'pinterest-catalog-sync-error';

	/**
	 * Should the note be added to the inbox.
	 *
	 * @since 1.1.0
	 * @return bool
	 */
	public static function should_be_added(): bool {
		if ( ! Pinterest_For_Woocommerce()::is_setup_complete() ) {
			return false;
		}

		$feed_id = FeedRegistration::get_locally_stored_registered_feed_id();
		if ( ! ProductSync::is_product_sync_enabled() || ! $feed_id ) {
			return false;
		}

		if ( self::note_exists() ) {
			return false;
		}

		// Are we there yet? We want to try three days after the account was connected.
		if ( time() < ( DAY_IN_SECONDS * 3 + Utilities::get_account_connection_timestamp() ) ) {
			return false;
		}

		$processing_results = Feeds::get_feed_recent_processing_results( $feed_id );
		if ( empty( $processing_results ) ) {
			// No workflow to check.
			return false;
		}
		switch ( $processing_results['status'] ?? '' ) {
			case Feeds::FEED_PROCESSING_STATUS_COMPLETED:
			case Feeds::FEED_PROCESSING_STATUS_COMPLETED_EARLY:
			case Feeds::FEED_PROCESSING_STATUS_PROCESSING:
			case Feeds::FEED_PROCESSING_STATUS_QUEUED_FOR_PROCESSING:
			case Feeds::FEED_PROCESSING_STATUS_UNDER_APPEAL:
			case Feeds::FEED_PROCESSING_STATUS_UNDER_REVIEW:
				return false;

			case Feeds::FEED_PROCESSING_STATUS_DISAPPROVED:
			case Feeds::FEED_PROCESSING_STATUS_FAILED:
			default:
				return true;
		}
	}

	/**
	 * Get note title.
	 *
	 * @since 1.1.0
	 * @return string Note title.
	 */
	protected function get_note_title(): string {
		return __( 'Review issues affecting your connection with Pinterest', 'pinterest-for-woocommerce' );
	}

	/**
	 * Get note content.
	 *
	 * @since 1.1.0
	 * @return string Note content.
	 */
	protected function get_note_content(): string {
		return __( 'Your product sync to Pinterest was unsuccessful. To complete your connection, Review and resolve issues in the extension.', 'pinterest-for-woocommerce' );
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page
	 *
	 * @since 1.1.0
	 * @param Note $note Note to which we add an action.
	 */
	protected function add_action( $note ): void {
		$note->add_action(
			'goto-pinterest-catalog',
			__( 'Review issues', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/catalog' )
		);
	}

}
