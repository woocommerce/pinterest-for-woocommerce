<?php
/**
 * FeedNotFoundException class.
 *
 * @package Automattic\WooCommerce\Pinterest\Exception
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Exception;

use Exception;

/**
 * Exception thrown when there is no matching feed at Pinterest.
 */
class FeedNotFoundException extends Exception {}
