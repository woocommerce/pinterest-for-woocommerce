/**
 * External dependencies
 */

import { createInterpolateElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Button, Card, CardFooter, CardDivider } from '@wordpress/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import AccountConnection from '../components/Account/Connection';
import BusinessAccountSelection from '../components/Account/Business';
import { useSettingsSelect } from '../helpers/effects';

const SetupAccount = ( {
	goToNextStep,
	view,
	isConnected,
	setIsConnected,
	isBusinessConnected,
	setIsBusinessConnected,
} ) => {
	const appSettings = useSettingsSelect();

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
								'Set up a free Pinterest business account to get access to analytics on your Pins and the ability to run ads. This requires agreeing to the <a>Pinterest advertising guidelines</a>.',
								'pinterest-for-woocommerce'
							),
							{
								a: (
									// Disabling no-content rule - content is interpolated from above string.
									// eslint-disable-next-line jsx-a11y/anchor-has-content
									<a
										href={
											wcSettings.pinterest_for_woocommerce
												.pinterestLinks.adGuidelines
										}
										target="_blank"
										rel="noreferrer"
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
										businessAccounts={
											wcSettings.pinterest_for_woocommerce
												.businessAccounts
										}
									/>
								</>
							) }

						{ isConnected === false && (
							<CardFooter>
								<Button
									isLink
									href={
										wcSettings.pinterest_for_woocommerce
											.pinterestLinks.newAccount
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
							wcSettings.pinterest_for_woocommerce
								.businessAccounts.length < 1 && (
								<CardFooter>
									<Button
										isLink
										href={
											wcSettings.pinterest_for_woocommerce
												.pinterestLinks.newAccount // TODO: change link
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
