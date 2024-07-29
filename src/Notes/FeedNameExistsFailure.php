<?php
/**
 * WooCommerce Admin: Feed with the same name already exists at Pinterest notice.
 *
 * Adds a note to inform clients there is a feed from another website already at Pinterest.
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
 * Feed name already exists at Pinterest admin notice.
 */
class FeedNameExistsFailure {
	/**
	 * Note traits.
	 */
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-feed-name-exists-failure';

	/**
	 * Get the note.
	 *
	 * @since x.x.x
	 *
	 * @return Note
	 */
	public static function get_note() {
		$content_lines = array(
			__( 'The Pinterest For WooCommerce plugin has detected an issue with your feed.<br/>The feed with the same name already exists at Pinterest.<br/>This can be due to another website already connected to the same Pinterest account.', 'pinterest-for-woocommerce' ),
		);

		$additional_data = array(
			'role' => 'administrator',
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest For WooCommerce action required.', 'pinterest-for-woocommerce' ) );
		$note->set_content( implode( '', $content_lines ) );
		$note->set_content_data( (object) $additional_data );
		$note->set_type( Note::E_WC_ADMIN_NOTE_ERROR );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-admin' );
		$note->add_action(
			'pinterest-for-woocommerce-feed-name-exists-failure-disconnect',
			__( 'Disconnect Pinterest', 'pinterest-for-woocommerce' ),
			admin_url( 'admin.php?page=wc-admin&path=/pinterest/onboarding' ),
			Note::E_WC_ADMIN_NOTE_ACTIONED,
			true,
			'primary'
		);
		return $note;
	}

	/**
	 * Add the note if it passes predefined conditions.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 * @throws NotesUnavailableException Throws exception when notes are unavailable.
	 */
	public static function possibly_add_note() {
		if ( self::note_exists() ) {
			return;
		}

		$note = self::get_note();
		$note->save();
	}
}
