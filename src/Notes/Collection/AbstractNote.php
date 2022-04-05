<?php
/**
 * Pinterest for WooCommerce Abstract Note.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 * @version x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;

use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractNote class. This is a proxy that helps us using the WC Admin Note
 * class in the context of Pinterest For WooCommerce plugin.
 *
 * @since x.x.x
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Notes
 */
abstract class AbstractNote {

	/**
	 * Check if the note has been previously added.
	 *
	 * @throws NotesUnavailableException Throws exception when notes are unavailable.
	 */
	public static function note_exists() {
		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( static::NOTE_NAME );
		return ! empty( $note_ids );
	}

	/**
	 * Get the note entry.
	 *
	 * @since x.x.x
	 */
	public function prepare_note() {
		$note = new Note();
		$this->fill_in_note_details( $note );
		return $note;
	}

	/**
	 * Use helper functions to prepare the note.
	 *
	 * @since x.x.x
	 * @param Note $note The note that we are setting up.
	 *
	 * @return void
	 */
	protected function fill_in_note_details( $note ): void {
		$note->set_title( $this->get_note_title() );
		$note->set_content( $this->get_note_content() );
		$note->set_content_data( new stdClass() );
		$note->set_type( $this->get_type() );
		$note->set_layout( $this->get_layout() );
		$note->set_image( $this->get_image() );
		$note->set_name( $this->get_name() );
		$note->set_source( $this->get_slug() );
		$this->add_action( $note );
	}

	/**
	 * Get note type.
	 *
	 * @since x.x.x
	 */
	protected function get_type(): string {
		return Note::E_WC_ADMIN_NOTE_INFORMATIONAL;
	}

	/**
	 * Get the note's unique name.
	 *
	 * @since x.x.x
	 * @return string
	 */
	protected function get_name(): string {
		return static::NOTE_NAME;
	}

	/**
	 * Get note layout style.
	 *
	 * @since x.x.x
	 */
	protected function get_layout() {
		return 'plain';
	}

	/**
	 * Get note image.
	 *
	 * @since x.x.x
	 */
	protected function get_image() {
		return '';
	}

	/**
	 * Add action to note if necessary.
	 *
	 * @since x.x.x
	 * @param Note $note Note to which we want to add an action.
	 */
	protected function add_action( $note ): void {
		return;
	}

	/**
	 * Get note slug;
	 *
	 * @since x.x.x
	 */
	private function get_slug(): string {
		return PINTEREST_FOR_WOOCOMMERCE_PREFIX;
	}

	/**
	 * Get note title.
	 *
	 * @since x.x.x
	 */
	abstract protected function get_note_title(): string;

	/**
	 * Get note content.
	 *
	 * @since x.x.x
	 */
	abstract protected function get_note_content(): string;

	/**
	 * Check whether the note should be added.
	 *
	 * @since x.x.x
	 */
	abstract public function should_be_added(): bool;

}
