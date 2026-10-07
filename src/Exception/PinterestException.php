<?php
/**
 * PinterestException interface.
 *
 * @package Automattic\WooCommerce\Pinterest\Exception
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Exception;

use Throwable;

/**
 * This interface is used for all of our exceptions so that we can easily catch only our own exceptions.
 *
 * @class   PinterestException
 * @version 1.0.2
 */
interface PinterestException extends Throwable {}
