/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	SelectControl,
	__experimentalText as Text,
} from '@wordpress/components';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';

const SetupTracking = ( {
	goToNextStep,
	appSettings,
	setAppSettings,
	createNotice,
	view,
} ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ status, setStatus ] = useState( 'idle' );
	const [ advertisersList, setAdvertisersList ] = useState();
	const [ tagsList, setTagsList ] = useState();

	useEffect( () => {
		if ( undefined !== appSettings && undefined === advertisersList ) {
			fetchAdvertisers();
		}

		if ( advertisersList && tagsList && appSettings?.tracking_advertiser && appSettings?.tracking_tag ) {
			setStatus( 'success' );
		}
	}, [ appSettings, advertisersList ] );

	const fetchAdvertisers = async () => {
		try {
			setAdvertisersList();

			const results = await apiFetch( {
				path: wcSettings.pin4wc.apiRoute + '/advertisers',
				method: 'GET',
			} );

			setAdvertisersList( results.advertisers );

			if ( results.advertisers.length > 0 ) {
				if ( ! appSettings?.tracking_advertiser ) {
					handleOptionChange(
						'advertiser',
						results.advertisers[ 0 ].id
					);
				} else {
					fetchTags( appSettings?.tracking_advertiser );
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

			if ( Object.keys( results ).length > 0 ) {
				if ( ! appSettings?.tracking_tag ) {
					handleOptionChange( 'tag', Object.keys( results )[ 0 ] );
				}
			} else {
				setStatus( 'error' );
			}

			if ( appSettings?.tracking_tag ) {
				setStatus( 'success' );
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
			fetchTags( value );
		}

		if ( appSettings?.tracking_advertiser && appSettings?.tracking_tag ) {
			setStatus( 'success' );
		} else {
			setStatus( 'idle' );
		}

		saveOptions( name, value );
	};

	const saveOptions = async ( name, value ) => {
		setIsSaving( true );

		const update = await setAppSettings( {
			[ `tracking_${ name }` ]: value,
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
	};

	const handleTryAgain = () => {
		setStatus( 'idle' );

		if ( appSettings.tracking_advertiser ) {
			fetchTags( appSettings.tracking_advertiser );
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
				onClick={ status === 'success' ? goToNextStep : handleTryAgain }
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
						{ undefined !== appSettings &&
						Object.keys( appSettings ).length > 0 &&
						undefined !== advertisersList ? (
							<CardBody size="large">
								{ advertisersList.length > 0 ? (
									<div>
										<SelectControl
											label={ __(
												'Advertiser',
												'pinterest-for-woocommerce'
											) }
											value={
												appSettings.tracking_advertiser
											}
											onChange={ ( selectedAdvertiser ) =>
												handleOptionChange(
													'advertiser',
													selectedAdvertiser
												)
											}
											options={ advertisersList.map(
												( item ) => ( {
													label: sprintf(
														'%1$s (%2$d)',
														item.name,
														item.id
													),
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
												'Tracking cannot be configured automatically since your Pinterest account is not configured as an Advertiser.',
												'pinterest-for-woocommerce'
											) }
										</Text>
										<Text
											variant="body"
											className="text-margin"
										>
											{ __(
												'Please follow',
												'pinterest-for-woocommerce'
											) }{ ' ' }
											<Button
												isLink
												href={
													wcSettings.pin4wc
														.pinterestLinks
														.createAdvertiser
												}
												target="_blank"
											>
												{ __(
													'this guide',
													'pinterest-for-woocommerce'
												) }
											</Button>{ ' ' }
											{ __(
												'to create an advertiser account.',
												'pinterest-for-woocommerce'
											) }
										</Text>
										<Text
											variant="body"
											className="text-margin"
										>
											{ __(
												'Once you’ve successfully created it, come back here and click “Try again” below.',
												'pinterest-for-woocommerce'
											) }
										</Text>
									</>
								) }

								{ undefined !==
									appSettings.tracking_advertiser &&
									( undefined !== tagsList ? (
										Object.keys( tagsList ).length > 0 && (
											<>
												<SelectControl
													label={ __(
														'Tracking Tag',
														'pinterest-for-woocommerce'
													) }
													value={
														appSettings.tracking_tag
													}
													onChange={ (
														selectedTag
													) =>
														handleOptionChange(
															'tag',
															selectedTag
														)
													}
													options={ Object.values(
														tagsList
													).map( ( item ) => ( {
														label: sprintf(
															'%1$s (%2$d)',
															item.name,
															item.id
														),
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

export default SetupTracking;
