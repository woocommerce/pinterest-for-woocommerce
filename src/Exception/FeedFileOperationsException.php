<?php
declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\Exception;

use Exception;

class FeedFileOperationsException extends Exception {
	public const CODE_COULD_NOT_RENAME_ERROR = 10;

	public const CODE_COULD_NOT_OPEN_FILE_ERROR = 20;

	public const CODE_COULD_NOT_WRITE_FILE_ERROR = 30;
}
