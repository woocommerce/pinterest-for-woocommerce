<?php declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\E2e;

use Automattic\WooCommerce\Admin\Notes\Notes;
use Automattic\WooCommerce\Pinterest\Notes\FeedDeletionFailure;
use Automattic\WooCommerce\Pinterest\PinterestApiException;

class FeedDeletionFailureE2eTest extends \WP_UnitTestCase {

	public function tearDown(): void {
		parent::tearDown();

		FeedDeletionFailure::possibly_delete_note();
	}

	/**
	 * Tests if local feed config is empty we attempt to fetch a feed from Pinterest in case it exists.
	 * This test aims to check if we restore empty local feed config from the server in case of auto-disconnect.
	 *
	 * @return void
	 */
	public function test_there_is_a_single_note_only() {
		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );
		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );
		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );
		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );

		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( FeedDeletionFailure::NOTE_NAME );

		$this->assertCount( 1, $note_ids );
	}

	public function test_deletes_multiple_notes() {
		for ( $i = 0; $i < 10; $i ++ ) {
			$note = FeedDeletionFailure::get_note( 'Some message' );
			$note->save();
		}

		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( FeedDeletionFailure::NOTE_NAME );

		$this->assertCount( 10, $note_ids );

		FeedDeletionFailure::possibly_delete_note();

		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( FeedDeletionFailure::NOTE_NAME );

		$this->assertCount( 0, $note_ids );
	}
}
