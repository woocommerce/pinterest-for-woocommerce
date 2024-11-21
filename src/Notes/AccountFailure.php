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
class AccountFailure {
	/**
	 * Note traits.
	 */
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-account-failure';

	/**
	 * Get the note.
	 *
	 * @param string $message Pinterest API error message.
	 * @return Note
	 */
	public static function get_note( string $message ) {
		$additional_data = array(
			'role' => 'administrator',
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest For WooCommerce action required.', 'pinterest-for-woocommerce' ) );
		$note->set_content( esc_html( $message ) );
		$note->set_content_data( (object) $additional_data );
		$note->set_type( Note::E_WC_ADMIN_NOTE_ERROR );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-admin' );
		return $note;
	}

	/**
	 * Used to add an account failure note if the one does not exist.
	 *
	 * @param string $message Pinterest API error message.
	 * @return void
	 * @throws NotesUnavailableException
	 */
	public static function maybe_add_note( string $message ): void {
		if ( self::note_exists() ) {
			return;
		}

		$note = self::get_note( $message );
		$note->save();
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
