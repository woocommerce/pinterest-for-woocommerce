<?php
/**
 * Pinterest for WooCommerce ReconnectMerchant class.
 *
 * @package Pinterest_For_WooCommerce/Classes/
 */

namespace Automattic\WooCommerce\Pinterest\Notes\Collection;

use UnauthorizedAccessMonitor;
use Automattic\WooCommerce\Admin\Notes\Note;

/**
 * Class ReconnectMerchant.
 *
 * Class responsible for admin reconnect account error notification after any of Pinterest API calls
 * return 401 Unauthorized response.
 *
 * @since x.x.x
 */
class ReconnectMerchant extends AbstractNote {

	const NOTE_NAME = 'pinterest-reconnect-merchant';

	/**
	 * Sets the note type.
	 *
	 * @since x.x.x
	 *
	 * @return string
	 */
	protected function get_type(): string {
		return Note::E_WC_ADMIN_NOTE_ERROR;
	}

	/**
	 * Should the note be added to the inbox.
	 *
	 * @since x.x.x
	 *
	 * @return bool
	 */
	public static function should_be_added(): bool {
		return UnauthorizedAccessMonitor::is_as_task_paused();
	}

	/**
	 * Get note title.
	 *
	 * @since x.x.x
	 *
	 * @return string Note title.
	 */
	protected function get_note_title(): string {
		return __( 'Reconnect your Pinterest account.', 'pinterest-for-woocommerce' );
	}

	/**
	 * Get note content.
	 *
	 * @since x.x.x
	 *
	 * @return string Note content.
	 */
	protected function get_note_content(): string {
		return __(
			'Pinterest did not authorize the request. Please, reconnect your Pinterest account. Meanwhile all the scheduled tasks like feed generation and synchronization were put on hold until the access is restored.',
			'pinterest-for-woocommerce'
		);
	}

	/**
	 * Add button to Pinterest For WooCommerce landing page
	 *
	 * @since x.x.x
	 *
	 * @param Note $note Note to which we add an action.
	 */
	protected function add_action( $note ): void {
		$note->add_action(
			'goto-pinterest-connection',
			__( 'Reconnect', 'pinterest-for-woocommerce' ),
			wc_admin_url( '&path=/pinterest/connection' )
		);
	}
}
