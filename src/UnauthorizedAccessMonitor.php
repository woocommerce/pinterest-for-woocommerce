<?php
/**
 * Pinterest for WooCommerce UnauthorizedAccessMonitor class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Admin\Notes\DataStore;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Pinterest\Notes\Collection\ReconnectMerchant;
use Throwable;

/**
 * Class UnauthorizedAccessMonitor responsible for monitoring unauthorized access to Pinterest API
 * and perform actions accordingly.
 *
 * @since x.x.x
 */
class UnauthorizedAccessMonitor {

	/**
	 * Monitors unauthorized access to Pinterest API.
	 * If the exception is thrown by Pinterest API and the code is 401,
	 * then it marks that access token renewal is required.
	 *
	 * @param Throwable $throwable The exception thrown by Pinterest api call.
	 *
	 * @return void
	 */
	public static function monitor( Throwable $throwable ): void {
		if ( $throwable instanceof PinterestApiException && 401 === $throwable->getCode() ) {
			self::pause_as_tasks();
		}
	}

	/**
	 * Checks if access token renewal is required and shows the corresponding note.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function maybe_show_error(): void {
		if ( self::is_as_task_paused() ) {
			try {
				/** @var DataStore $data_store */
				$data_store = Notes::load_data_store();
				$note_ids   = $data_store->get_notes_with_name( ReconnectMerchant::NOTE_NAME );
				if ( 0 === count( $note_ids ) ) {
					( new ReconnectMerchant() )->prepare_note()->save();
				} else {
					$note_id = current( $note_ids );
					/** @var Note $note */
					$note    = Notes::get_note( $note_id );
					if ( $note instanceof Note ) {
						$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );
						$note->save();
					}
				}
			} catch ( NotesUnavailableException $exception ) {
				( new ReconnectMerchant() )->prepare_note()->save();
			}
		}
	}

	/**
	 * Marks that access token renewal is required and pause all corresponding Action Scheduler tasks.
	 *
	 * @since x.x.x
	 */
	public static function pause_as_tasks(): void {
		set_transient( 'pinterest_for_woocommerce_renew_token_required', true, 2 * HOUR_IN_SECONDS );

		FeedRegistration::cancel_jobs();
		FeedGenerator::cancel_jobs();
	}

	/**
	 * Returns access token renewal status.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function is_as_task_paused(): bool {
		return get_transient( 'pinterest_for_woocommerce_renew_token_required' );
	}

	/**
	 * Removes access token renewal status.
	 *
	 * @since x.x.x
	 *
	 * @return bool The result of operation.
	 */
	public static function unpause_as_tasks(): bool {
		return delete_transient( 'pinterest_for_woocommerce_renew_token_required' );
	}
}
