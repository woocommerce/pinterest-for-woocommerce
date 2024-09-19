<?php
/**
 * WooCommerce Admin: Pinterest Feed Deletion Failed.
 *
 * Adds a note to tell the user that Pinterest Feed deletion failed when
 * disconnecting/deactivating/uninstalling the extension.
 *
 * @package Automattic\WooCommerce\Pinterest\Notes
 */

namespace Automattic\WooCommerce\Pinterest\Notes;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Pinterest\PinterestApiException;

/**
 * Pinterest Feed Deletion Error Admin Note Class
 */
class FeedDeletionFailure {

	use NoteTraits;

	/**
	 * @var string Name of the note for use in the database.
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-feed-deletion-failure';

	/**
	 * @var string Message in case feed could not be deleted due to a disapproved merchant reason.
	 */
	const MESSAGE_MERCHANT_DISAPPROVED = 'The merchant is disapproved.';

	/**
	 * @var string Message in case feed could not be deleted due to active promotions still present on it.
	 */
	const MESSAGE_FEED_HAS_PROMOTIONS = 'You feed has active promotions.';

	/**
	 * @var string Message in case feed could not be deleted due to a merchant is still under review reason.
	 */
	const MESSAGE_MERCHANT_UNDER_REVIEW = 'The merchant is under review.';

	/**
	 * @var string A default message in case of an error code mismatch.
	 */
	const MESSAGE_DEFAULT = 'Unexpected error. Please, see the log for more details.';

	/**
	 * Get the note.
	 *
	 * @param string $message - An additional message to show.
	 *
	 * @return Note
	 */
	public static function get_note( string $message = '' ) {
		$content = sprintf(
			// translators: %1$s: Pinterest API message (reason of the failure).
			__(
				'The Pinterest For WooCommerce plugin has failed to delete the feed.<br/>%1$s<br/>Please, contact Pinterest support to resolve the issue.',
				'pinterest-for-woocommerce'
			),
			$message
		);

		$additional_data = array(
			'role' => 'administrator',
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest For WooCommerce Feed Deletion Failed.', 'pinterest-for-woocommerce' ) );
		$note->set_content( $content );
		$note->set_content_data( (object) $additional_data );
		$note->set_type( Note::E_WC_ADMIN_NOTE_ERROR );
		$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'pinterest-for-woocommerce' );
		$note->add_action(
			'dismiss',
			__( 'Dismiss', 'pinterest-for-woocommerce' )
		);
		return $note;
	}

	/**
	 * Add the note if it passes predefined conditions.
	 *
	 * @param int $code - Pinterest API Error Code.
	 *
	 * @return void
	 */
	public static function possibly_add_note( int $code ) {
		try {
			if ( self::note_exists() ) {
				return;
			}

			$message = self::code_to_message( $code );

			$note = self::get_note( $message );
			$note->save();
		} catch ( NotesUnavailableException $e ) {
			return;
		}
	}

	/**
	 * Maps code to message.
	 *
	 * @param int $code - Pinterest error code.
	 * @return string - Predefined error message.
	 */
	private static function code_to_message( int $code ): string {
		if ( PinterestApiException::MERCHANT_DISAPPROVED === $code ) {
			return self::MESSAGE_MERCHANT_DISAPPROVED;
		}

		if ( PinterestApiException::MERCHANT_UNDER_REVIEW === $code ) {
			return self::MESSAGE_MERCHANT_UNDER_REVIEW;
		}

		if ( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS === $code ) {
			return self::MESSAGE_FEED_HAS_PROMOTIONS;
		}

		return self::MESSAGE_DEFAULT;
	}
}
