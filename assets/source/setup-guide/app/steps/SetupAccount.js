/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { createInterpolateElement, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@woocommerce/components';
import { getNewPath } from '@woocommerce/navigation';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	Flex,
	FlexItem,
	FlexBlock,
	Modal,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import { useSettingsSelect, useCreateNotice } from '../helpers/effects';

const SetupAccount = ( {
	goToNextStep,
	view,
	isBusinessConnected,
	setIsBusinessConnected,
} ) => {
	const [ isConfirmationModalOpen, setIsConfirmationModalOpen ] = useState(
		false
	);
	const appSettings = useSettingsSelect();

	const createNotice = useCreateNotice();

	const openConfirmationModal = () => {
		setIsConfirmationModalOpen( true );
	};

	const closeConfirmationModal = () => {
		setIsConfirmationModalOpen( false );
	};

	const renderConfirmationModal = () => {
		return (
			<Modal
				title={
					<>{ __( 'Are you sure?', 'pinterest-for-woocommerce' ) }</>
				}
				onRequestClose={ closeConfirmationModal }
				className="woocommerce-setup-guide__step-modal"
			>
				<div className="woocommerce-setup-guide__step-modal__wrapper">
					<p>
						{ __(
							'Are you sure you want to disconnect this account?',
							'pinterest-for-woocommerce'
						) }
					</p>
					<div className="woocommerce-setup-guide__step-modal__buttons">
						<Button
							isDestructive
							isSecondary
							onClick={ handleDisconnectAccount }
						>
							{ __(
								"Yes, I'm sure",
								'pinterest-for-woocommerce'
							) }
						</Button>
						<Button isTertiary onClick={ closeConfirmationModal }>
							{ __( 'Cancel', 'pinterest-for-woocommerce' ) }
						</Button>
					</div>
				</div>
			</Modal>
		);
	};

	const handleDisconnectAccount = async () => {
		closeConfirmationModal();

		const result = await apiFetch( {
			path:
				wcSettings.pinterest_for_woocommerce.apiRoute +
				'/auth_disconnect',
			method: 'POST',
		} );

		if ( ! result.disconnected ) {
			createNotice(
				'error',
				__(
					'There was a problem while trying to disconnect.',
					'pinterest-for-woocommerce'
				)
			);
		} else {
			setIsBusinessConnected( false );

			// Force reload WC admin page to initiate the relevant dependencies of the Dashboard page.
			const path = getNewPath( {}, '/pinterest/landing', {} );

			window.location = new URL(
				decodeEntities( wcSettings.adminUrl + path )
			);
		}
	};

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
						<CardBody size="large">
							{ isBusinessConnected === true ? ( // eslint-disable-line no-nested-ternary --- Code is reasonable readable
								<Flex>
									<FlexBlock className="is-connected">
										<Text variant="subtitle">
											{ __(
												'Pinterest Account',
												'pinterest-for-woocommerce'
											) }
										</Text>
										{ appSettings?.account_data?.id && (
											<Text variant="body">
												{ sprintf(
													'%1$s: %2$s - %3$s',
													__(
														'Account',
														'pinterest-for-woocommerce'
													),
													appSettings.account_data
														.username,
													appSettings.account_data.id
												) }
											</Text>
										) }
										<Button
											isLink
											isDestructive
											onClick={ openConfirmationModal }
										>
											{ __(
												'Disconnect Pinterest Account',
												'pinterest-for-woocommerce'
											) }
										</Button>
									</FlexBlock>
								</Flex>
							) : isBusinessConnected === false ? (
								<Flex>
									<FlexBlock>
										<Text variant="subtitle">
											{ __(
												'Connect your Pinterest Account',
												'pinterest-for-woocommerce'
											) }
										</Text>
									</FlexBlock>
									<FlexItem>
										<Button
											isSecondary
											href={ decodeEntities(
												wcSettings
													.pinterest_for_woocommerce
													.serviceLoginUrl
											) }
										>
											{ __(
												'Connect',
												'pinterest-for-woocommerce'
											) }
										</Button>
									</FlexItem>
								</Flex>
							) : (
								<Spinner />
							) }
						</CardBody>

						{ isBusinessConnected === false && (
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

						{ isConfirmationModalOpen && renderConfirmationModal() }
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
