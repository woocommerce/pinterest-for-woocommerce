<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;
use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\NoteTraits;


use WC_Data_Store;
use stdClass;

defined( 'ABSPATH' ) || exit;

/**
 * AbstractNote class.
 *
 * @since x.x.x
 *
 * @package Automattic\WooCommerce\GoogleListingsAndAds\Notes
 */
abstract class AbstractNote {

	use NoteTraits;

	/**
	 * NOTE_NAME const needs to be defined in subclass.
	 */

	/**
	 * Get the note entry.
	 */
	protected function prepare_note() {
		$note = new Note();
		$this->fill_in_note_details( $note );
		return $note;
	}

	/**
	 * @param NoteEntry $note
	 *
	 * @return void
	 */
	protected function fill_in_note_details( Note $note ): void {
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
	 */
	protected function get_type() {
		return Note::E_WC_ADMIN_NOTE_INFORMATIONAL;
	}

	/**
	 * Get the note's unique name.
	 *
	 * @return string
	 */
	protected function get_name(): string {
		return self::NOTE_NAME;
	}

	/**
	 * Get note layout.
	 */
	protected function get_layout() {
		return 'plain';
	}

	/**
	 * Get note image.
	 */
	protected function get_image() {
		return '';
	}

	/**
	 * Add action to note if necessary.
	 */
	protected function add_action( $note ) {
		return;
	}

	/**
	 * Get note slug;
	 */
	private function get_slug() {
		return PINTEREST_FOR_WOOCOMMERCE_PREFIX;
	}

	/**
	 * Get note title.
	 */
	abstract protected function get_note_title(): string;

	/**
	 * Get note content.
	 */
	abstract protected function get_note_content(): string;



	/**
	 * Check whether the note should be added.
	 */
	abstract public function should_be_added(): bool;

}
