/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { getNewPath, getHistory } from '@woocommerce/navigation';

/**
 * Use the 'woocommerce_admin_onboarding_task_list' filter to add a task page.
 */
addFilter(
	'woocommerce_admin_onboarding_task_list',
	'pinterest-for-woocommerce',
	( tasks ) => {
		return [
			...tasks,
			{
				key: 'setup-pinterest',
				title: __(
					'Setup Pinterest for WooCommerce',
					'pinterest-for-woocommerce'
				),
				onClick: () => {
					getHistory().push( getNewPath( {}, '/pinterest/landing' ) );
				},
				completed: wcSettings.pinterest_for_woocommerce.isSetupComplete,
				visible: true,
				additionalInfo: __(
					'Connect your store to Pinterest to sync products and track conversions.',
					'pinterest-for-woocommerce'
				),
				time: __( '5 minutes', 'pinterest-for-woocommerce' ),
				isDismissable: true,
			},
		];
	}
);
