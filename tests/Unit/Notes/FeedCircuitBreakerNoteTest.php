<?php

namespace Automattic\WooCommerce\Pinterest\Tests\Unit\Notes;

use Automattic\WooCommerce\Admin\Notes\Note;
use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Pinterest\Notes\FeedCircuitBreakerNote;

/**
 * FeedCircuitBreakerNoteTest class.
 */
class FeedCircuitBreakerNoteTest extends \WP_UnitTestCase {

	/**
	 * @inheritDoc
	 */
	public function setUp(): void {
		parent::setUp();
		Notes::delete_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
	}

	/**
	 * @inheritDoc
	 */
	public function tearDown(): void {
		Notes::delete_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		parent::tearDown();
	}

	/**
	 * @return void
	 */
	public function test_add_note_creates_note_when_none_exists() {
		FeedCircuitBreakerNote::add_note( 2500 );

		$note_ids = Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		$this->assertCount( 1, $note_ids );
	}

	/**
	 * @return void
	 */
	public function test_add_note_replaces_existing_unactioned_note() {
		FeedCircuitBreakerNote::add_note( 2500 );
		$first_id = current( Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) );

		FeedCircuitBreakerNote::add_note( 3000 );
		$second_ids = Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );

		$this->assertCount( 1, $second_ids, 'Only one note should exist after two add_note calls' );
		$this->assertNotEquals( $first_id, current( $second_ids ), 'Should be a new note, not the original' );
	}

	/**
	 * @return void
	 */
	public function test_add_note_replaces_dismissed_note() {
		FeedCircuitBreakerNote::add_note( 2500 );

		$data_store = Notes::load_data_store();
		$note       = Notes::get_note( current( $data_store->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) ) );
		$note->set_status( Note::E_WC_ADMIN_NOTE_ACTIONED );
		$note->save();

		FeedCircuitBreakerNote::add_note( 3000 );

		$note_ids   = $data_store->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME );
		$fresh_note = Notes::get_note( current( $note_ids ) );
		$this->assertCount( 1, $note_ids );
		$this->assertEquals( Note::E_WC_ADMIN_NOTE_UNACTIONED, $fresh_note->get_status() );
	}

	/**
	 * @return void
	 */
	public function test_note_has_warning_type_and_correct_title() {
		FeedCircuitBreakerNote::add_note( 2500 );

		$note = Notes::get_note(
			current( Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) )
		);

		$this->assertEquals( Note::E_WC_ADMIN_NOTE_WARNING, $note->get_type() );
		$this->assertStringContainsString( 'Pinterest catalog feed incomplete', $note->get_title() );
	}

	/**
	 * @return void
	 */
	public function test_note_body_contains_recommended_limit() {
		FeedCircuitBreakerNote::add_note( 2500 );

		$note = Notes::get_note(
			current( Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) )
		);

		$this->assertStringContainsString( '2500', $note->get_content() );
	}

	/**
	 * @return void
	 */
	public function test_delete_note_removes_existing_note(): void {
		FeedCircuitBreakerNote::add_note( 2500 );
		$this->assertCount( 1, Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) );

		FeedCircuitBreakerNote::delete_note();

		$this->assertCount( 0, Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) );
	}

	/**
	 * @return void
	 */
	public function test_delete_note_is_safe_when_no_note_exists(): void {
		// No note added — delete_note must not throw.
		FeedCircuitBreakerNote::delete_note();
		$this->assertCount( 0, Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) );
	}

	/**
	 * @return void
	 */
	public function test_note_has_catalog_sync_and_dismiss_actions() {
		FeedCircuitBreakerNote::add_note( 2500 );

		$note         = Notes::get_note(
			current( Notes::load_data_store()->get_notes_with_name( FeedCircuitBreakerNote::NOTE_NAME ) )
		);
		$action_names = array_map( fn( $a ) => $a->name, $note->get_actions() );

		$this->assertContains( 'go-to-catalog-sync', $action_names );
		$this->assertContains( 'dismiss', $action_names );
	}
}
