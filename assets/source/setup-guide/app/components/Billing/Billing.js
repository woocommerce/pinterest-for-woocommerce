/**
 * External dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@woocommerce/components';
import { getNewPath } from '@woocommerce/navigation';
import { recordEvent } from '@woocommerce/tracks';
import {
	Button,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';


/**
 * Clicking on "Connect" Pinterest account button.
 *
 * @event wcadmin_pfw_account_connect_button_click
 */
/**
 * Clicking on "Disconnect" Pinterest account button.
 *
 * @event wcadmin_pfw_account_disconnect_button_click
 * @property {string} context `'settings' | 'wizard'` In which context it was used?
 */
/**
 * Opening a modal.
 *
 * @event wcadmin_pfw_modal_open
 * @property {string} name Which modal is it?
 * @property {string} context `'settings' | 'wizard'` In which context it was used?
 */
/**
 * Closing a modal.
 *
 * @event wcadmin_pfw_modal_closed
 * @property {string} name Which modal is it?
 * @property {string} context `'settings' | 'wizard'` In which context it was used?
 * @property {string} action
 * 				`confirm` - When the final "Yes, I'm sure" button is clicked.
 * 				`dismiss` -  When the modal is dismissed by clicking on "x", "cancel", overlay, or by pressing a keystroke.
 */

/**
 * Pinterest account connection component.
 *
 * This renders the body of `SetupAccount` card, to connect or disconnect Pinterest account.
 *
 * @fires wcadmin_pfw_account_connect_button_click
 * @fires wcadmin_pfw_account_disconnect_button_click with the given `{ context }`
 * @fires wcadmin_pfw_modal_open with `{ name: 'account-disconnection', … }`
 * @fires wcadmin_pfw_modal_closed with `{ name: 'account-disconnection', … }`
 * @param {Object} props React props.
 * @param {boolean} props.isConnected
 * @param {Function} props.setIsConnected
 * @param {Object} props.accountData
 * @param {string} props.context Context in which the component is used, to be forwarded to fired Track Events.
 * @return {JSX.Element} Rendered element.
 */
const Billing = ( {
	accountData,
} ) => {

	const isBillingSetup = accountData?.is_billing_setup;

	return (
		<CardBody size="large">
			<Flex direction="row" className="billing-info">
				{ isBillingSetup === true ? ( // eslint-disable-line no-nested-ternary --- Code is reasonable readable
					<>
						<FlexBlock className="billing-label">
							<Text variant="body">
								{ __(
									'Billing Setup Correctly',
									'pinterest-for-woocommerce'
								) }
							</Text>
						</FlexBlock>

						<FlexItem>
							<Button
								href={
									wcSettings.pinterest_for_woocommerce
										.billingSettingsUrl
								}
								onClick={ () =>
									recordEvent(
										'pfw_go_to_billing_button_click'
									)
								}
							>
								{ __( 'Go to billing settings', 'pinterest-for-woocommerce' ) }
							</Button>
						</FlexItem>
					</>
				) : isBillingSetup === false ? (
					<>
						<FlexBlock>
							<Text variant="body">
								{ __(
									'No Valid Billing Setup Found',
									'pinterest-for-woocommerce'
								) }
							</Text>
						</FlexBlock>

						<FlexItem>
							<Button
								href={
									wcSettings.pinterest_for_woocommerce
										.billingSettingsUrl
								}
								onClick={ () =>
									recordEvent(
										'pfw_billing_setup_button_click'
									)
								}
							>
								{ __( 'Setup Billing', 'pinterest-for-woocommerce' ) }
							</Button>
						</FlexItem>
					</>
				) : (
					<Spinner className="connection-info__preloader" />
				) }
			</Flex>
		</CardBody>
	);
};

export default Billing;
