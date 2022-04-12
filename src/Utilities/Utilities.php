<?php
/**
 * Pinterest for WooCommerce Utilities.
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Utilities;

use \Error;

/**
 * Utilities class.
 *
 * @since x.x.x
 */
class Utilities {

	const PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-account-connection-timestamp';

	/**
	 * Set the account connection timestamp.
	 *
	 * @since x.x.x
	 * @return int
	 */
	public static function set_account_connection_timestamp(): int {
		$timestamp = time();

		add_option( self::PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP, $timestamp );

		return $timestamp;
	}

	/**
	 * Gets the account connection timestamp.
	 * Initialize if not.
	 *
	 * @since x.x.x
	 * @return int Initialization timestamp.
	 */
	public static function get_account_connection_timestamp(): int {
		$timestamp = get_option( self::PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP );
		if ( false !== $timestamp ) {
			return (int) $timestamp;
		}

		return self::set_account_connection_timestamp();
	}
}
