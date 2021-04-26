/**
 * External dependencies
 */

import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import WizardApp from '../setup-guide/app/views/WizardApp';

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
				title: __( 'Setup Pinterest Integration', 'pinterest-for-woocommerce' ),
				container: <WizardApp />,
				completed: pin4wcSetupGuide.isSetupComplete,
				visible: true,
				additionalInfo: __( 'Configure the connection to Pinterest and any additional settings like tracking, product sync, etc.', 'pinterest-for-woocommerce' ),
				time: __( '5 minutes', 'pinterest-for-woocommerce' ),
				isDismissable: true,
			},
		];
	}
);
