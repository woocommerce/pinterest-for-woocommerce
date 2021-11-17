/* eslint-disable @wordpress/no-global-event-listener */
/**
 * External dependencies
 */

import {
	useEffect,
	useState,
	useCallback,
	createInterpolateElement,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardFooter, CardDivider } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import AccountConnection from '../components/Account/Connection';
import BusinessAccountSelection from '../components/Account/BusinessAccountSelection';
import { useSettingsSelect, useCreateNotice } from '../helpers/effects';

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
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'ad-guidelines', context: 'setup-account' }`
 * @fires wcadmin_pfw_documentation_link_click with `{ link_id: 'merchant-guidelines', context: 'setup-account' }`
 *
 * @param {Object} props React props
 * @param {Function} props.goToNextStep
 * @param {string} props.view
 * @param {boolean} props.isConnected
 * @param {Function} props.setIsConnected
 * @param {boolean} props.isBusinessConnected
 * @return {JSX.Element} Rendered element.
 */
const SetupAccount = ( {
	goToNextStep,
	view,
	isConnected,
	setIsConnected,
	isBusinessConnected,
} ) => {
	const createNotice = useCreateNotice();
	const appSettings = useSettingsSelect();
	const [ businessAccounts, setBusinessAccounts ] = useState(
		wcSettings.pinterest_for_woocommerce.businessAccounts
	);

	useEffect( () => {
		if ( undefined !== businessAccounts && businessAccounts.length > 0 ) {
			window.removeEventListener( 'focus', fetchBusinesses );
		} else {
			window.addEventListener( 'focus', fetchBusinesses );
		}

		return () => window.removeEventListener( 'focus', fetchBusinesses );
	}, [ fetchBusinesses, businessAccounts ] );

	const fetchBusinesses = useCallback( async () => {
		try {
			setBusinessAccounts();

			const results = await apiFetch( {
				path:
					wcSettings.pinterest_for_woocommerce.apiRoute +
					'/businesses/',
				method: 'GET',
			} );

			setBusinessAccounts( results );
		} catch ( error ) {
			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t retrieve your linked business accounts.',
						'pinterest-for-woocommerce'
					)
			);
		}
	}, [ createNotice ] );

	return (
		<div className="woocommerce-setup-guide__setup-account">
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Set up your business account',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step One', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={
							view === 'wizard'
								? __(
										'Pinterest business account',
										'pinterest-for-woocommerce'
								  )
								: __(
										'Linked account',
										'pinterest-for-woocommerce'
								  )
						}
						description={ createInterpolateElement(
							__(
								'Set up a free Pinterest business account to get access to analytics on your Pins and the ability to run ads. This requires agreeing to our <adGuidelinesLink>advertising guidelines</adGuidelinesLink> and following our <merchantGuidelinesLink>merchant guidelines</merchantGuidelinesLink>.',
								'pinterest-for-woocommerce'
							),
							{
								adGuidelinesLink: (
									// Disabling no-content rule - content is interpolated from above string.
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										href={
											wcSettings.pinterest_for_woocommerce
												.pinterestLinks.adGuidelines
										}
										target="_blank"
										rel="noreferrer"
										onClick={ () =>
											recordEvent(
												'pfw_documentation_link_click',
												{
													link_id: 'ad-guidelines',
													context: 'setup-account',
													href:
														wcSettings
															.pinterest_for_woocommerce
															.pinterestLinks
															.adGuidelines,
												}
											)
										}
									/>
								),
								merchantGuidelinesLink: (
									// Disabling no-content rule - content is interpolated from above string.
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										href={
											wcSettings.pinterest_for_woocommerce
												.pinterestLinks
												.merchantGuidelines
										}
										target="_blank"
										rel="noreferrer"
										onClick={ () =>
											recordEvent(
												'pfw_documentation_link_click',
												{
													link_id:
														'merchant-guidelines',
													context: 'setup-account',
													href:
														wcSettings
															.pinterest_for_woocommerce
															.pinterestLinks
															.merchantGuidelines,
												}
											)
										}
									/>
								),
							}
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<AccountConnection
							isConnected={ isConnected }
							setIsConnected={ setIsConnected }
							accountData={ appSettings.account_data }
						/>

						{ isConnected === true &&
							isBusinessConnected === false && (
								<>
									<CardDivider />
									<BusinessAccountSelection
										businessAccounts={ businessAccounts }
									/>
								</>
							) }

						{ isConnected === false && (
							<CardFooter size="large">
								<Button
									isLink
									href={
										wcSettings.pinterest_for_woocommerce
											.pinterestLinks.newAccount
									}
									onClick={ () =>
										recordEvent(
											'pfw_account_create_button_click'
										)
									}
									target="_blank"
								>
									{ __(
										'Or, create a new Pinterest account',
										'pinterest-for-woocommerce'
									) }
								</Button>
							</CardFooter>
						) }

						{ isConnected === true &&
							isBusinessConnected === false &&
							undefined !== businessAccounts &&
							businessAccounts.length < 1 && (
								<CardFooter size="large">
									<Button
										isLink
										href={
											wcSettings.pinterest_for_woocommerce
												.pinterestLinks
												.convertToBusinessAcct
										}
										onClick={ () =>
											recordEvent(
												'pfw_account_convert_button_click'
											)
										}
										target="_blank"
									>
										{ __(
											'Or, convert your personal account',
											'pinterest-for-woocommerce'
										) }
									</Button>
								</CardFooter>
							) }
					</Card>

					{ view === 'wizard' && isBusinessConnected === true && (
						<div className="woocommerce-setup-guide__footer-button">
							<Button isPrimary onClick={ goToNextStep }>
								{ __(
									'Continue',
									'pinterest-for-woocommerce'
								) }
							</Button>
						</div>
					) }
				</div>
			</div>
		</div>
	);
};

export default SetupAccount;
