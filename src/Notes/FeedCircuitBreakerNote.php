<?php
/**
 * WooCommerce Admin: Pinterest Feed Circuit Breaker Triggered.
 *
 * Adds a note to inform the user that feed generation was truncated because
 * the maximum batch limit was reached, and provides guidance on how to fix it.
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
 * Feed Circuit Breaker admin note.
 */
class FeedCircuitBreakerNote {

	use NoteTraits;

	/**
	 * Name of the note for use in the database.
	 *
	 * @var string
	 */
	const NOTE_NAME = 'pinterest-for-woocommerce-feed-circuit-breaker';

	/**
	 * Build the note object.
	 *
	 * @param int $recommended_limit Recommended value for the max batches filter.
	 * @return Note
	 */
	public static function get_note( int $recommended_limit ): Note {
		$content = sprintf(
			// translators: %d: recommended batch limit value for the filter.
			__(
				'Your catalog is too large to fully sync with Pinterest. Some products may not appear on Pinterest yet. Please refer to plugin documentation at https://woocommerce.com/document/pinterest-for-woocommerce/ to increase the batch limit to %d to sync all products.',
				'pinterest-for-woocommerce'
			),
			$recommended_limit
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest catalog feed incomplete — product limit reached', 'pinterest-for-woocommerce' ) );
		$note->set_content( $content );
		$note->set_content_data( (object) array( 'role' => 'administrator' ) );
		$note->set_type( Note::E_WC_ADMIN_NOTE_WARNING );
		$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'pinterest-for-woocommerce' );
		$note->add_action(
			'go-to-catalog-sync',
			__( 'Go to Catalog Sync', 'pinterest-for-woocommerce' ),
			admin_url( 'admin.php?page=wc-admin&path=/pinterest/catalog' )
		);
		$note->add_action(
			'dismiss',
			__( 'Dismiss', 'pinterest-for-woocommerce' )
		);

		return $note;
	}

	/**
	 * Delete any existing note and save a fresh one.
	 *
	 * Unlike other notes that use NoteTraits::possibly_add_note() (which no-ops
	 * if the note already exists), this always deletes and re-saves so the note
	 * reappears even if the merchant previously dismissed it.
	 *
	 * @param int $recommended_limit Recommended value for the max batches filter.
	 * @return void
	 */
	public static function add_note( int $recommended_limit ): void {
		try {
			Notes::delete_notes_with_name( self::NOTE_NAME );
			$note = self::get_note( $recommended_limit );
			$note->save();
		} catch ( NotesUnavailableException $e ) {
			return;
		}
	}
}
