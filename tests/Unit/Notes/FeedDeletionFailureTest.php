<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Notes;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;

/**
 * FeedDeletionFailureTest class.
 *
 * @version 1.6.0
 */
class FeedDeletionFailureTest extends \WP_UnitTestCase {

	/**
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();
		Notes::delete_notes_with_name( FeedDeletionFailure::NOTE_NAME );
	}

	/**
	 * @inheritDoc
	 */
	public function tearDown(): void {
		Notes::delete_notes_with_name( FeedDeletionFailure::NOTE_NAME );
		parent::tearDown();
	}

	/**
	 * Without a stored note, get_note() builds a new one.
	 *
	 * @return void
	 */
	public function test_get_note_builds_new_note_when_none_exists() {
		$note = FeedDeletionFailure::get_note( 'Reason.' );

		$this->assertInstanceOf( Note::class, $note );
		$this->assertSame( 0, $note->get_id() );
		$this->assertSame( FeedDeletionFailure::NOTE_NAME, $note->get_name() );
		$this->assertStringContainsString( 'Reason.', $note->get_content() );
	}

	/**
	 * With a stored note, get_note() returns it.
	 *
	 * @return void
	 */
	public function test_get_note_returns_stored_note() {
		FeedDeletionFailure::possibly_add_note( 0 );
		$stored_id = (int) current( Notes::load_data_store()->get_notes_with_name( FeedDeletionFailure::NOTE_NAME ) );

		$note = FeedDeletionFailure::get_note();

		$this->assertInstanceOf( Note::class, $note );
		$this->assertSame( $stored_id, $note->get_id() );
	}

	/**
	 * When the stored note can't be loaded, get_note() builds a new one instead of returning false.
	 *
	 * @return void
	 */
	public function test_get_note_builds_new_note_when_stored_note_cannot_be_loaded() {
		FeedDeletionFailure::possibly_add_note( 0 );

		// Notes::get_note() returns false when loading the note throws.
		$fail_loading = static function () {
			throw new \Exception( 'Note could not be loaded.' );
		};
		add_action( 'woocommerce_note_loaded', $fail_loading );
		try {
			$note = FeedDeletionFailure::get_note( 'Reason.' );
		} finally {
			remove_action( 'woocommerce_note_loaded', $fail_loading );
		}

		$this->assertInstanceOf( Note::class, $note );
		$this->assertSame( 0, $note->get_id() );
		$this->assertSame( FeedDeletionFailure::NOTE_NAME, $note->get_name() );
	}
}
