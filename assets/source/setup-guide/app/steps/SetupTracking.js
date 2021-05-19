/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	SelectControl,
	__experimentalText as Text,
} from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';

const SetupTracking = ( {
	goToNextStep,
	pin4wc,
	updateOptions,
	createNotice,
	view,
} ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ status, setStatus ] = useState( 'idle' );
	const [ advertisersList, setAdvertisersList ] = useState();
	const [ tagsList, setTagsList ] = useState();
	const [ advertiser, setAdvertiser ] = useState();
	const [ tag, setTag ] = useState();

	useEffect( () => {
		if ( undefined !== pin4wc ) {
			if ( pin4wc.tracking_advertiser !== advertiser ) {
				setAdvertiser( pin4wc.tracking_advertiser );
			}

			if ( pin4wc.tracking_tag !== tag ) {
				setTag( pin4wc.tracking_tag );
			}

			if ( undefined === advertisersList ) {
				fetchAdvertisers();
			}
		}
	}, [ pin4wc, advertisersList, advertiser, tag ] );

	const fetchAdvertisers = async () => {
		try {
			setAdvertisersList();

			const results = await apiFetch( {
				path: wcSettings.pin4wc.apiRoute + '/advertisers',
				method: 'GET',
			} );

			setAdvertisersList( results.advertisers );

			if ( results.advertisers.length > 0 ) {
				if ( results.advertisers.length === 1 ) {
					handleOptionChange(
						'advertiser',
						results.advertisers[ 0 ].id
					);
				}
			} else {
				setStatus( 'error' );
			}
		} catch ( error ) {
			setStatus( 'error' );
			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t retrieve your advertisers.',
						'pinterest-for-woocommerce'
					)
			);
		}
	};

	const fetchTags = async ( advertiserId ) => {
		try {
			setTagsList();

			const results = await apiFetch( {
				path:
					wcSettings.pin4wc.apiRoute +
					'/tags/?advrtsr_id=' +
					advertiserId,
				method: 'GET',
			} );

			setTagsList( results );

			if ( results.length > 0 ) {
				if ( results.length === 1 ) {
					handleOptionChange( 'tag', results[ 0 ].id );
				}
			} else {
				setStatus( 'error' );
			}
		} catch ( error ) {
			setStatus( 'error' );
			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t retrieve your tags.',
						'pinterest-for-woocommerce'
					)
			);
		}
	};

	const handleOptionChange = async ( name, value ) => {
		if ( name === 'advertiser' ) {
			setAdvertiser( value );
			setStatus( 'idle' );
			fetchTags( value );
		} else if ( name === 'tag' ) {
			setTag( value );
			setStatus( 'success' );
		}
	};

	const handleCompleteSetup = async () => {
		setIsSaving( true );

		const newOptions = {
			...pin4wc,
			tracking_advertiser: advertiser ?? pin4wc.tracking_advertiser,
			tracking_tag: tag ?? pin4wc.tracking_tag,
		};

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
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}

		setIsSaving( false );

		goToNextStep();
	};

	const handleTryAgain = () => {
		setStatus( 'idle' );

		if ( advertiser ) {
			fetchTags( advertiser );
		} else {
			fetchAdvertisers();
		}
	};

	const StepButton = () => {
		if ( status === 'idle' ) {
			return '';
		}

		const buttonLabels = {
			error: __( 'Try Again', 'pinterest-for-woocommerce' ),
			success: __( 'Continue', 'pinterest-for-woocommerce' ),
		};

		return (
			<Button
				isPrimary
				disabled={ isSaving }
				onClick={
					status === 'success' ? handleCompleteSetup : handleTryAgain
				}
			>
				{ buttonLabels[ status ] }
			</Button>
		);
	};

	return (
		<div className="woocommerce-setup-guide__setup-tracking">
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Set up tracking',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step Three', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Select your advertiser and tag',
							'pinterest-for-woocommerce'
						) }
						link={ wcSettings.pin4wc.pinterestLinks.SetupTracking }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						{ undefined !== pin4wc &&
						Object.keys( pin4wc ).length > 0 &&
						undefined !== advertisersList ? (
							<CardBody size="large">
								{ advertisersList.length > 0 ? (
									<div>
										<SelectControl
											label={ __(
												'Advertiser',
												'pinterest-for-woocommerce'
											) }
											value={ advertiser }
											onChange={ ( e ) =>
												handleOptionChange(
													'advertiser',
													e.target.value
												)
											}
											options={ advertisersList.map(
												( item ) => ( {
													label: item.id,
													value: item.id,
												} )
											) }
											help={ __(
												'Select the advertiser for which you would like to install a tracking snippet.',
												'pinterest-for-woocommerce'
											) }
										/>
									</div>
								) : (
									<>
										<Text
											variant="body"
											className="text-margin"
										>
											{ __(
												'Tracking cannot be configured automatically.',
												'pinterest-for-woocommerce'
											) }
										</Text>
										<Text
											variant="body"
											className="text-margin"
										>
											{ __(
												'Please visit your',
												'pinterest-for-woocommerce'
											) }{ ' ' }
											<Button
												isLink
												href={
													wcSettings.pin4wc
														.pinterestLinks
														.Dashboard
												}
												target="_blank"
											>
												{ __(
													'Pinterest Dashboard',
													'pinterest-for-woocommerce'
												) }
											</Button>{ ' ' }
											{ __(
												'and click “Create Ad”.',
												'pinterest-for-woocommerce'
											) }
										</Text>
										<Text
											variant="body"
											className="text-margin"
										>
											{ __(
												'After completing this step, click “Try again” button.',
												'pinterest-for-woocommerce'
											) }
										</Text>
									</>
								) }

								{ undefined !== pin4wc &&
									Object.keys( pin4wc ).length > 0 &&
									undefined !== advertiser &&
									( undefined !== tagsList ? (
										Object.keys( tagsList ).length > 0 && (
											<>
												<SelectControl
													label={ __(
														'Tracking Tag',
														'pinterest-for-woocommerce'
													) }
													value={ tag }
													onChange={ ( e ) =>
														handleOptionChange(
															'tag',
															e.target.value
														)
													}
													options={ Object.values(
														tagsList
													).map( ( item ) => ( {
														label: item.id,
														value: item.id,
													} ) ) }
													help={ __(
														'Select the tracking tag to use.',
														'pinterest-for-woocommerce'
													) }
												/>
											</>
										)
									) : (
										<Spinner />
									) ) }
							</CardBody>
						) : (
							<CardBody size="large">
								<Spinner />
							</CardBody>
						) }
					</Card>

					{ view === 'wizard' && (
						<div className="woocommerce-setup-guide__footer-button">
							<StepButton />
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
		const { createNotice } = dispatch( 'core/notices' );
		const { updateOptions } = dispatch( OPTIONS_STORE_NAME );

		return {
			createNotice,
			updateOptions,
		};
	} )
)( SetupTracking );
