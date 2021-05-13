<?php
/**
 * Pinterest For WooCommerce Tracking
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class adding Save Pin support.
 */
class Tracking {


	/**
	 * The var used to hold the JS that is to be printed.
	 *
	 * @var string
	 */
	private static $script = '';

	/**
	 * The var used to hold the events specific JS that is to be printed.
	 *
	 * @var string
	 */
	private static $events = '';


	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		add_action( 'wp_head', array( __CLASS__, 'print_script' ) );

	}


	public static function print_script() {

		if ( ! empty( self::$script ) ) {
			echo self::$script;

			if ( ! empty( self::$events ) ) {
				echo self::$events;
			}
		}

	}

}
