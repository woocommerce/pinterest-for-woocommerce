<?php
/**
 * Pinterest for WooCommerce Utilities.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Utilities;

/**
 * Utilities class.
 *
 * @since x.x.x
 */
class Utilities {

	const ACCOUNT_CONNECTION_TIMESTAMP = PINTEREST_FOR_WOOCOMMERCE_OPTION_NAME . '_account_connection_timestamp';

	/**
	 * Set the account connection timestamp.
	 *
	 * @since x.x.x
	 */
	public static function set_account_connection_timestamp() {
		update_option( self::ACCOUNT_CONNECTION_TIMESTAMP, time() );
	}

	/**
	 * Gets the account connection timestamp.
	 *
	 * @since x.x.x
	 * @return int Account connection timestamp. Zero if not set.
	 */
	public static function get_account_connection_timestamp(): int {
		return (int) get_option( self::ACCOUNT_CONNECTION_TIMESTAMP, 0 );
	}
}
