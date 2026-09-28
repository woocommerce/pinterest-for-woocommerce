<?php
/**
 * Feed circuit breaker exception.
 *
 * @package Automattic\WooCommerce\Pinterest\Exception
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Exception;

use Exception;

/**
 * Thrown when the feed generator circuit breaker trips (batch_number exceeds
 * the configured max batches per cycle limit).
 */
class FeedCircuitBreakerException extends Exception implements PinterestException {}
