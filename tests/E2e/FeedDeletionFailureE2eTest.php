<?php declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Tests\E2e;

use Automattic\WooCommerce\Admin\Notes\Note;
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
			$additional_data = array(
				'role' => 'administrator',
			);
			$note = new Note();
			$note->set_title( __( 'Pinterest For WooCommerce Feed Deletion Failed.', 'pinterest-for-woocommerce' ) );
			$note->set_content( 'Notice text.' );
			$note->set_content_data( (object) $additional_data );
			$note->set_type( Note::E_WC_ADMIN_NOTE_ERROR );
			$note->set_status( Note::E_WC_ADMIN_NOTE_UNACTIONED );
			$note->set_name( FeedDeletionFailure::NOTE_NAME );
			$note->set_source( 'pinterest-for-woocommerce' );
			$note->add_action(
				'dismiss',
				__( 'Dismiss', 'pinterest-for-woocommerce' )
			);
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

	public function test_no_notice_duplicates() {
		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );

		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( FeedDeletionFailure::NOTE_NAME );
		$note       = Notes::get_note( current( $note_ids ) );
		$note->set_status( Note::E_WC_ADMIN_NOTE_ACTIONED );
		$note->save();

		FeedDeletionFailure::possibly_add_note( PinterestApiException::CATALOGS_FEED_HAS_ACTIVE_PROMOTIONS );

		$data_store = Notes::load_data_store();
		$note_ids   = $data_store->get_notes_with_name( FeedDeletionFailure::NOTE_NAME );
		$note       = Notes::get_note( current( $note_ids ) );

		$this->assertCount( 1, $note_ids );
		$this->assertEquals( Note::E_WC_ADMIN_NOTE_ACTIONED, $note->get_status() );
	}
}
