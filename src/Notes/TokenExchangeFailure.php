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
use Automattic\WooCommerce\Admin\Notes\NoteTraits;

/**
 * Add_First_Product.
 */
class TokenExchangeFailure {
	/**
	 * Note traits.
	 */
	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-v5-token-exchange-failure';

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		$content_lines = array(
			__( 'The Pinterest For WooCommerce plugin has been updated to version 1.4.0, which now supports Pinterest\'s new V5 API, replacing the outgoing V3 API.<br/>You need to re-authorize the plugin through your WooCommerce dashboard to maintain integration functionality and access new features.', 'pinterest-for-woocommerce' ),
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
			'pinterest-for-woocommerce-v5-update-failure-go-to-settings',
			__( 'Authenticate with Pinterest', 'pinterest-for-woocommerce' ),
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
	 * @return false|Note False if the note already exists, otherwise the Note object.
	 */
	public static function possibly_add_note() {
		if ( self::note_exists() ) {
			return false;
		}

		$note = self::get_note();
		$note->save();
		return true;
	}

	/**
	 * Set note to actioned.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function delete_failure_note() {
		Notes::delete_notes_with_name( self::NOTE_NAME );
	}
}
