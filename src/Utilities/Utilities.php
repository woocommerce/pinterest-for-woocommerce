<?php
/**
 * Pinterest for WooCommerce Feed Logger
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Utilities;

use \Error;

const PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-account-connection-timestamp';

/**
 * Set the account connection timestamp.
 *
 * @since x.x.x
 * @throws Error Wrong value provided for the timestamp.
 * @param int $timestamp Timestamp to set as the account connection moment.
 * @return int
 */
function set_account_connection_timestamp( $timestamp = null ): int {
	if ( is_null( $timestamp ) ) {
		$timestamp = time();
	}

	if ( ! is_int( $timestamp ) ) {
		throw new Error( 'Account connection timestamp needs to be an integer value.' );
	}

	add_option( PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP, $timestamp );
	return $timestamp;
}

/**
 * Gets the account connection timestamp.
 * Initialize if not.
 *
 * @since x.x.x
 * @return int Initialization timestamp.
 */
function get_account_connection_timestamp(): int {
	$timestamp = get_option( PINTEREST_ACCOUNT_CONNECTION_TIMESTAMP );
	if ( false !== $timestamp ) {
		return (int) $timestamp;
	}

	return set_account_connection_timestamp();
}
