<?php
/**
 * Pinterest for WooCommerce Feed Logger
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @since       x.x.x
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Utilities;

use Automattic\WooCommerce\Pinterest\Logger;

/**
 * Trait FeedLogger
 *
 * @since x.x.x
 */
trait FeedLogger {

	/**
	 * Logs Sync related messages separately.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level   The level of the message.
	 *
	 * @return void
	 */
	private static function log( $message, $level = 'debug' ) {
		Logger::log( $message, $level, 'product-sync' );
	}

}
