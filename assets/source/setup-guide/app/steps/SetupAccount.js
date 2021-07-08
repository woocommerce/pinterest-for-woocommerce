/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	Flex,
	FlexItem,
	FlexBlock,
	Modal,
	__experimentalText as Text,
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const SetupAccount = ({ goToNextStep, view }) => {
	const [isConfirmationModalOpen, setIsConfirmationModalOpen] =
		useState(false);
	const appSettings = useSettingsSelect();
	const isConnected = useSettingsSelect('isConnected');
	const setAppSettings = useSettingsDispatch(view === 'wizard');
	const createNotice = useCreateNotice();

	const openConfirmationModal = () => {
		setIsConfirmationModalOpen(true);
	};

	const closeConfirmationModal = () => {
		setIsConfirmationModalOpen(false);
	};

	const renderConfirmationModal = () => {
		return (
			<Modal
				title={<>{__('Are you sure?', 'pinterest-for-woocommerce')}</>}
				onRequestClose={closeConfirmationModal}
				className="woocommerce-setup-guide__step-modal"
			>
				<div className="woocommerce-setup-guide__step-modal__wrapper">
					<p>
						{__(
							'Are you sure you want to disconnect this account?',
							'pinterest-for-woocommerce'
						)}
					</p>
					<div className="woocommerce-setup-guide__step-modal__buttons">
						<Button
							isDestructive
							isSecondary
							onClick={handleDisconnectAccount}
						>
							{__("Yes, I'm sure", 'pinterest-for-woocommerce')}
						</Button>
						<Button isTertiary onClick={closeConfirmationModal}>
							{__('Cancel', 'pinterest-for-woocommerce')}
						</Button>
					</div>
				</div>
			</Modal>
		);
	};

	const handleDisconnectAccount = async () => {
		closeConfirmationModal();

		const update = await setAppSettings(
			{
				token: null,
				crypto_encoded_key: null,
			},
			true
		);

		if (!update.success) {
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}
	};

	return (
		<div className="woocommerce-setup-guide__setup-account">
			{view === 'wizard' && (
				<StepHeader
					title={__(
						'Set up your business account',
						'pinterest-for-woocommerce'
					)}
					subtitle={__('Step One', 'pinterest-for-woocommerce')}
				/>
			)}

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
						description={
							<>
								{__(
									'Set up a free Pinterest business account to get access to analytics on your Pins and the ability to run ads. This requires agreeing to our',
									'pinterest-for-woocommerce'
								)}{' '}
								<Button
									isLink
									href={
										wcSettings.pin4wc.pinterestLinks
											.adGuidelines
									}
									target="_blank"
								>
									{__(
										'advertising guidelines',
										'pinterest-for-woocommerce'
									)}
								</Button>
							</>
						}
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							{isConnected === true ? (
								<Flex>
									<FlexBlock className="is-connected">
										<Text variant="subtitle">
											{__(
												'Pinterest Account',
												'pinterest-for-woocommerce'
											)}
										</Text>
										{appSettings?.account_data?.id && (
											<Text variant="body">
												{sprintf(
													'%1$s: %2$s - %3$s',
													__(
														'Account',
														'pinterest-for-woocommerce'
													),
													appSettings.account_data
														.username,
													appSettings.account_data.id
												)}
											</Text>
										)}
										<Button
											isLink
											isDestructive
											onClick={openConfirmationModal}
										>
											{__(
												'Disconnect Pinterest Account',
												'pinterest-for-woocommerce'
											)}
										</Button>
									</FlexBlock>
								</Flex>
							) : isConnected === false ? (
								<Flex>
									<FlexBlock>
										<Text variant="subtitle">
											{__(
												'Connect your Pinterest Account',
												'pinterest-for-woocommerce'
											)}
										</Text>
									</FlexBlock>
									<FlexItem>
										<Button
											isSecondary
											href={decodeEntities(
												wcSettings.pin4wc
													.serviceLoginUrl
											)}
										>
											{__(
												'Connect',
												'pinterest-for-woocommerce'
											)}
										</Button>
									</FlexItem>
								</Flex>
							) : (
								<Spinner />
							)}
						</CardBody>

						{isConnected === false && (
							<CardFooter>
								<Button
									isLink
									href={
										wcSettings.pin4wc.pinterestLinks
											.newAccount
									}
									target="_blank"
								>
									{__(
										'Or, create a new Pinterest account',
										'pinterest-for-woocommerce'
									)}
								</Button>
							</CardFooter>
						)}

						{isConfirmationModalOpen && renderConfirmationModal()}
					</Card>

					{view === 'wizard' && isConnected === true && (
						<div className="woocommerce-setup-guide__footer-button">
							<Button isPrimary onClick={goToNextStep}>
								{__('Continue', 'pinterest-for-woocommerce')}
							</Button>
						</div>
					)}
				</div>
			</div>
		</div>
	);
};

export default SetupAccount;
