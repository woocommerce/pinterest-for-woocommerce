/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
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
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';

const SetupAccount = ( {
	pin4wc,
	updateOptions,
	createNotice,
	view,
	goToNextStep,
} ) => {
	const [ options, setOptions ] = useState( undefined );

	useEffect( () => {
		if ( options !== pin4wc ) {
			setOptions( pin4wc );
		}
	}, [ pin4wc ] );

	const isConnected = () => {
		return undefined === options
			? undefined
			: !! options?.token?.access_token;
	};

	const handleDisconnectAccount = async () => {
		const oldOptions = Object.assign( {}, options );
		const newOptions = Object.assign( {}, options );

		delete newOptions.token;
		delete newOptions.crypto_encoded_key;

		setOptions( newOptions );

		const update = await updateOptions( {
			[ wcSettings.pin4wc.optionsName ]: newOptions,
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
			setOptions( oldOptions );
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
							{ isConnected() === true ? (
								<Flex>
									<FlexBlock className="is-connected">
										<Text variant="subtitle">
											{ __(
												'Pinterest Account',
												'pinterest-for-woocommerce'
											) }
										</Text>
										{ options?.account_data?.id && (
											<Text variant="body">{ `${ __(
												'Account',
												'pinterest-for-woocommerce'
											) }: ${
												options.account_data.username
											}
											- ${ options.account_data.id }
											` }</Text>
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
							) : isConnected() === false ? (
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

						{ isConnected() === false && (
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

					{ view === 'wizard' && isConnected() === true && (
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

export default compose(
	withSelect( ( select ) => {
		const { getOption } = select( OPTIONS_STORE_NAME );

		return {
			pin4wc: getOption( wcSettings.pin4wc.optionsName ),
		};
	} ),
	withDispatch( ( dispatch ) => {
		const { updateOptions } = dispatch( OPTIONS_STORE_NAME );
		const { createNotice } = dispatch( 'core/notices' );

		return {
			updateOptions,
			createNotice,
		};
	} )
)( SetupAccount );
