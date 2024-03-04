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
use Automattic\WooCommerce\Admin\Notes\NoteTraits;
use Automattic\WooCommerce\Internal\Admin\Notes\MerchantEmailNotifications;

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
	const NOTE_NAME = 'pinterest-for-woocommerce-v5-token-exchange-failure-email-t';

	/**
	 * Get the note.
	 *
	 * @return Note
	 */
	public static function get_note() {
		$content_lines = array(
			'{greetings}<br/><br/>',
			__( 'We\'re writing to notify you of a critical update to the Pinterest For WooCommerce plugin (version 1.4.0) that has recently been applied to your system. <br/><br/>', 'pinterest-for-woocommerce' ),
			__( 'Pinterest is phasing out its V3 API, which our plugin previously utilized. The updated version 1.4.0 now supports Pinterest\'s new V5 API.<br/><br/>', 'pinterest-for-woocommerce' ),
			__( 'To maintain the functionality of your Pinterest For WooCommerce integration, it is necessary to re-authorize the plugin with your Pinterest account. You can do this by navigating to the plugin settings in your WooCommerce dashboard and clicking on the "Get Started" button.<br/><br/> ', 'pinterest-for-woocommerce' ),
			__( 'This step is essential to ensure uninterrupted service and access to the latest features offered by the Pinterest integration.<br/><br/>', 'pinterest-for-woocommerce' ),
			__( 'You’re receiving this email because you signed up for Store Management Insights from your WooCommerce dashboard. <br/><br/>', 'pinterest-for-woocommerce' ),
			__( 'Thank you for your immediate attention to this matter.<br/><br/>', 'pinterest-for-woocommerce' ),
			__( 'Best regards,<br/>', 'pinterest-for-woocommerce' ),
			__( 'The Pinterest For WooCommerce Team', 'pinterest-for-woocommerce' ),
		);

		$additional_data = array(
			'role' => 'administrator',
		);

		$note = new Note();
		$note->set_title( __( 'Pinterest For WooCommerce action required.', 'pinterest-for-woocommerce' ) );
		$note->set_content( implode( '', $content_lines ) );
		$note->set_content_data( (object) $additional_data );
		$note->set_image(
			Pinterest_For_Woocommerce()->plugin_url() . '/assets/images/pinterest-logo.svg'
		);
		$note->set_type( Note::E_WC_ADMIN_NOTE_EMAIL );
		$note->set_name( self::NOTE_NAME );
		$note->set_source( 'woocommerce-admin' );
		$note->add_action( 'pinterest-for-woocommerce-v5-update-failure-go-to-settings', __( 'Go to Pinterest Settings.', 'pinterest-for-woocommerce' ), admin_url( 'admin.php?page=wc-admin&path=/pinterest/landing' ) );
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
	 * Send an email (Note) to the merchant tha they need to reconnect manually.
	 *
	 * @since x.x.x
	 *
	 * @return void
	 */
	public static function maybe_send_email_to_merchant() {

		// Check if merchant emails are enabled.
		$merchant_emails_enabled = get_option( 'woocommerce_merchant_email_notifications', 'no' ) === 'yes';
		if ( ! $merchant_emails_enabled ) {
			return;
		}

		// Try to add the note.
		$note_added = self::possibly_add_note();
		if ( ! $note_added ) {
			return;
		}

		// Make sure the class exists.
		if ( ! class_exists( MerchantEmailNotifications::class ) ) {
			return;
		}

		$note = self::get_note();
		MerchantEmailNotifications::send_merchant_notification( $note );
		$note->set_status( 'sent' );
		$note->save();
	}
}
