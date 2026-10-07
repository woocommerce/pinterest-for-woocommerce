<?php
/**
 * Class PHPViewFactory
 *
 * @package Automattic\WooCommerce\Pinterest\View
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Pinterest\View;

defined( 'ABSPATH' ) || exit;

/**
 * Class PHPViewFactory
 *
 * @class   PHPViewFactory
 * @version 1.0.2
 */
final class PHPViewFactory implements ViewFactory {

	/**
	 * Create a new view object.
	 *
	 * @param string $path Path to the view file to render.
	 *
	 * @return View Instantiated view object.
	 *
	 * @throws ViewException If an invalid path was passed into the View.
	 */
	public function create( string $path ): View {
		return new PHPView( $path, $this );
	}
}
