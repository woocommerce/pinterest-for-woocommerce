/* eslint-disable @wordpress/no-global-event-listener */
/**
 * External dependencies
 */

import {
	createInterpolateElement,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { recordEvent } from '@woocommerce/tracks';
import { Card } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Billing from '../components/Billing/Billing';
import StepOverview from '../components/StepOverview';
import { useSettingsSelect } from '../helpers/effects';
import documentationLinkProps from '../helpers/documentation-link-props';

/**
 * Clicking on "… create a new Pinterest account" button.
 *
 * @event wcadmin_pfw_account_create_button_click
 */
/**
 * Clicking on "… convert your personal account" button.
 *
 * @event wcadmin_pfw_account_convert_button_click
 */

/**
 * Account setup step component.
 *
 * @fires wcadmin_pfw_account_create_button_click
 * @fires wcadmin_pfw_account_convert_button_click
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'ad-guidelines', context: props.view }`
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'merchant-guidelines', context: props.view }`
 * @fires wcadmin_pfw_modal_open with `{ name: 'ads-credits-terms-and-conditions', … }`
 * @fires wcadmin_pfw_modal_closed with `{ name: 'ads-credits-terms-and-conditions'', … }`
 *
 * @param {Object} props React props
 * @param {Function} props.goToNextStep
 * @param {string} props.view
 * @param {boolean} props.isConnected
 * @param {Function} props.setIsConnected
 * @param {boolean} props.isBusinessConnected
 * @return {JSX.Element} Rendered element.
 */
const BillingStatus = ( {
	isConnected,
	setIsConnected,
} ) => {
	const appSettings = useSettingsSelect();

	return (
		<div className="woocommerce-setup-guide__billing-status">

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={
							__(
								'Billing status',
								'pinterest-for-woocommerce'
							)
						}
						description={
							__(
								'A valid billing setup is necessary if you wish to advertise your products on Pinterest. You can set up and manage your billing through your account settings on Pinterest.',
								'pinterest-for-woocommerce'
							)
						}
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<Billing
							isConnected={ isConnected }
							accountData={ appSettings.account_data }
						/>
					</Card>
				</div>
			</div>
		</div>
	);
};

export default BillingStatus;
