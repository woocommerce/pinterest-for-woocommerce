<?php
/**
 * WooCommerce Admin: Add First Product.
 *
 * Adds a note (type `email`) to bring the client back to the store setup flow.
 *
 * @package Automattic\WooCommerce\Pinterest\Notes
 */

namespace Automattic\WooCommerce\Pinterest\Notes;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

/**
 * Add_First_Product.
 */
class FeedDeletionFailure {
	/**
	 * Note traits.
	 */
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-feed-deletion-failure';

	/**
	 * Get the note.
	 *
	 * @param string $message - An additional message to show.
	 *
	 * @return Note
	 */
	public static function get_note( string $message = '' ) {
		$content_lines = array(
			sprintf(
				// translators: %1$s: Pinterest API message (reason of the failure).
				__(
					'The Pinterest For WooCommerce plugin has failed to delete the feed.<br/>%1$s',
					'pinterest-for-woocommerce'
				),
				$message
			),
		);

		$additional_data = array(
			'role' => 'administrator',
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest For WooCommerce Feed Deletion Failed.', 'pinterest-for-woocommerce' ) );
		$note->set_content( implode( '', $content_lines ) );
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
	 * @param string $message - Pinterest API Exception message.
	 *
	 * @return void
	 */
	public static function possibly_add_note( string $message ) {
		try {
			if ( self::note_exists() && !self::has_note_been_actioned() ) {
				return;
			}

			$note = self::get_note( $message );
			$note->save();
		} catch ( NotesUnavailableException $e ) {
			return;
		}
	}

	/**
	 * Delete the note.
	 *
	 * @return void
	 */
	public static function delete_note() {
		Notes::delete_notes_with_name( self::NOTE_NAME );
	}
}
