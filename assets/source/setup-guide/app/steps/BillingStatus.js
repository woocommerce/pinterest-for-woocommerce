/* eslint-disable @wordpress/no-global-event-listener */
/**
 * External dependencies
 */

import { __ } from '@wordpress/i18n';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Billing from '../components/Billing/Billing';
import StepOverview from '../components/StepOverview';
import { useSettingsSelect } from '../helpers/effects';

const BillingStatus = () => {
	const appSettings = useSettingsSelect();

	return (
		<div className="woocommerce-setup-guide__billing-status">
			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Billing status',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'A valid billing setup is necessary if you wish to advertise your products on Pinterest. You can set up and manage your billing through your account settings on Pinterest.',
							'pinterest-for-woocommerce'
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<Billing accountData={ appSettings.account_data } />
					</Card>
				</div>
			</div>
		</div>
	);
};

export default BillingStatus;
