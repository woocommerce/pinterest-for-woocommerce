/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalText as Text,
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import { isConnected } from '../helpers/conditionals'

const SetupAccount = ( {
	appSettings,
	setAppSettings,
	createNotice,
	view,
	goToNextStep,
} ) => {
	const handleDisconnectAccount = async () => {
		const update = await setAppSettings( {
			token: null,
			crypto_encoded_key: null
		} );

		if ( update.success ) {
			createNotice(
				'success',
				__(
					'Settings were saved successfully.',
					'pinterest-for-woocommerce'
				)
			);
		} else {
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
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Set up your account',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step One', 'pinterest-for-woocommerce' ) }
					description={ __(
						'Use description text to help users understand what accounts they need to connect, and why they need to connect it.',
						'pinterest-for-woocommerce'
					) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Pinterest Account',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'Use description text to help users understand more',
							'pinterest-for-woocommerce'
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							{ isConnected( appSettings ) === true ? (
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
											onClick={ handleDisconnectAccount }
										>
											{ __(
												'Disconnect Pinterest Account',
												'pinterest-for-woocommerce'
											) }
										</Button>
									</FlexBlock>
								</Flex>
							) : isConnected( appSettings ) === false ? (
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
												wcSettings.pin4wc
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

						{ isConnected( appSettings ) === false && (
							<CardFooter>
								<Button
									isLink
									href={
										wcSettings.pin4wc.pinterestLinks
											.newAccount
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
					</Card>

					{ view === 'wizard' && isConnected( appSettings ) === true && (
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
