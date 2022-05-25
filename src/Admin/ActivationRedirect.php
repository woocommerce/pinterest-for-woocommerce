<?php
/**
 * Helper class for handling the redirection to the onboarding page.
 *
 * @package Automattic\WooCommerce\Pinterest\Admin
 * @since   X.X.X
 */

namespace Automattic\WooCommerce\Pinterest\Admin;

use Automattic\WooCommerce\Pinterest\PluginHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ActivationRedirect
 */
class ActivationRedirect {

	use PluginHelper;

	/**
	 * The setting for redirect to onboarding.
	 *
	 * @since  X.X.X
	 */
	protected const REDIRECT_SETTING = 'redirect_to_onboarding';

	/**
	 * Initiate class.
	 *
	 * @since  X.X.X
	 */
	public function register() {
		add_action(
			'admin_init',
			function () {
				$this->maybe_redirect_to_onboarding();
			}
		);
	}

	/**
	 * Checks if merchant should be redirected to the onboarding page.
	 *
	 * @since  X.X.X
	 * @return bool True if the redirection should have happened
	 */
	public function maybe_redirect_to_onboarding(): bool {
		if ( wp_doing_ajax() ) {
			return false;
		}

		// If we have redirected before do not attempt to redirect again.
		if ( ! $this->redirect_setting() ) {
			return false;
		}

		// Do not redirect if setup is already complete.
		if ( Pinterest_For_Woocommerce()::is_setup_complete() ) {
			$this->update_redirect_setting( false );
			return false;
		}

		// Do not redirect if we already are in the Get Started page.
		if ( $this->is_onboarding_page() && $this->redirect_setting() ) {
			$this->update_redirect_setting( false );
			return false;
		}

		// Redirect if setup is not complete.
		$this->redirect_to_onboarding_page();

		return true;
	}

	/**
	 * Utility function to immediately redirect to the main "Get Started" onboarding page.
	 * Note that this function immediately ends the execution.
	 *
	 * @since  X.X.X
	 * @return void
	 */
	protected function redirect_to_onboarding_page(): void {
		// If we are already on the onboarding page, do nothing.
		if ( $this->is_onboarding_page() ) {
			return;
		}

		$this->update_redirect_setting( true );

		wp_safe_redirect( admin_url( add_query_arg( $this->onboarding_page_parameters(), 'admin.php' ) ) );
		exit();
	}

	/**
	 * Maybe update the redirect option.
	 *
	 * @since  X.X.X
	 */
	public function maybe_update_redirect_option(): void {

		if (
			// Only redirect to onboarding when activated on its own.
			isset( $_GET['action'] ) && 'activate' === $_GET['action'] // phpcs:ignore WordPress.Security.NonceVerification
			// ...or with a bulk action.
			|| isset( $_POST['checked'] ) && is_array( $_POST['checked'] ) && 1 === count( $_POST['checked'] ) // phpcs:ignore WordPress.Security.NonceVerification
		) {
			$this->update_redirect_setting( true );
		}
	}

	/**
	 * Update the redirect setting.
	 *
	 * @param bool $redirect_setting The new value.
	 * @since X.X.X
	 */
	protected function update_redirect_setting( $redirect_setting ): void {
		Pinterest_For_Woocommerce()->save_setting( self::REDIRECT_SETTING, $redirect_setting );
	}

	/**
	 * Get the redirect setting.
	 *
	 * @since  X.X.X
	 * @return bool
	 */
	protected function redirect_setting(): bool {
		$redirect_setting = Pinterest_For_Woocommerce()->get_setting( self::REDIRECT_SETTING );
		return isset( $redirect_setting ) ? (bool) $redirect_setting : false;
	}
}
