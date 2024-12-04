<?php
/**
 * Adds an error note to display Pinterest API account status failure responses.
 *
 * @since 1.4.13
 * @package Automattic\WooCommerce\Pinterest\Notes
 */

namespace Automattic\WooCommerce\Pinterest\Notes;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Admin\Notes\NotesUnavailableException;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

/**
 * Account Failure admin notice.
 *
 * @since 1.4.13
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
	 * @since 1.4.13
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
	 * @since 1.4.13
	 * @param string $message Pinterest API error message.
	 * @return void
	 * @throws NotesUnavailableException An exception when notes are unavailable.
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
	 * @since 1.4.13
	 * @return void
	 */
	public static function delete_note() {
		Notes::delete_notes_with_name( self::NOTE_NAME );
	}
}
