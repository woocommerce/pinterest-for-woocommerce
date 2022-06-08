<?php
/**
 * Helper class for handling the activation hook.
 *
 * @package Automattic\WooCommerce\Pinterest
 * @since   x.x.x
 */

namespace Automattic\WooCommerce\Pinterest;

use Automattic\WooCommerce\Pinterest\Admin\ActivationRedirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PluginActivate
 */
class PluginActivate {

	/**
	 * Activation hook
	 *
	 * @since x.x.x
	 */
	public function activate(): void {

		// Init the update class.
		$this->init_plugin_update();

		// Maybe update the redirect option.
		( new ActivationRedirect() )->maybe_update_redirect_option();
	}

	/**
	 * Initialize the update helper class.
	 *
	 * @since  x.x.x
	 */
	protected function init_plugin_update(): void {
		( new PluginUpdate() )->update_plugin_update_version_option();
	}

}
