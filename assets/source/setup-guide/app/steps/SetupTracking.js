/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
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
import { getNewPath } from '@woocommerce/navigation';

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

const SetupTracking = ( { goToNextStep, view } ) => {
	const [ isSaving, setIsSaving ] = useState( false );
	const [ isFetching, setIsFetching ] = useState( false );
	const [ status, setStatus ] = useState( 'idle' );
	const [ advertisersList, setAdvertisersList ] = useState();
	const [ tagsList, setTagsList ] = useState();
	const appSettings = useSettingsSelect();
	const setAppSettings = useSettingsDispatch( view === 'wizard' );
	const createNotice = useCreateNotice();

	useEffect( () => {
		if (
			! isFetching &&
			undefined !== appSettings &&
			undefined === advertisersList
		) {
			fetchAdvertisers();
		}

		if (
			advertisersList &&
			tagsList &&
			appSettings?.tracking_advertiser &&
			appSettings?.tracking_tag
		) {
			setStatus( 'success' );
		}
	}, [ appSettings, advertisersList ] );

	const fetchAdvertisers = async () => {
		setIsFetching( true );

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
						'tracking_advertiser',
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

		setIsFetching( false );
	};

	const fetchTags = async ( advertiserId ) => {
		setIsFetching( true );

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
					handleOptionChange(
						'tracking_tag',
						Object.keys( results )[ 0 ]
					);
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

		setIsFetching( false );
	};

	const handleOptionChange = async ( name, value ) => {
		if ( name === 'tracking_advertiser' ) {
			fetchTags( value );
		}

		if ( appSettings?.tracking_advertiser && appSettings?.tracking_tag ) {
			setStatus( 'success' );
		} else {
			setStatus( 'idle' );
		}

		await saveOptions( name, value );
	};

	const saveOptions = async ( name, value ) => {
		setIsSaving( true );

		const update = await setAppSettings( {
			[ name ]: value ?? ! appSettings[ name ],
		} );

		if ( ! update.success ) {
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
			success: __( 'Complete Setup', 'pinterest-for-woocommerce' ),
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

	const handleCompleteSetup = async () => {
		await handleOptionChange( 'is_setup_complete', true );

		// Force reload WC admin page to initiate the relevant dependencies of the Dashboard page.
		const path = getNewPath( {}, '/pinterest/catalog', {} );

		window.location = new URL(
			decodeEntities( wcSettings.adminUrl + path )
		);
	};

	return (
		<div className="woocommerce-setup-guide__setup-tracking">
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Track conversions with the Pinterest tag',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step Three', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={
							view === 'wizard'
								? __(
										'Select your advertiser and tag',
										'pinterest-for-woocommerce'
								  )
								: __(
										'Track conversions with the Pinterest tag',
										'pinterest-for-woocommerce'
								  )
						}
						description={
							<>
								{ __(
									'The Pinterest tag is a piece of JavaScript code you put on your website to gather conversion insights and build audiences to target based on actions people have taken on your site.',
									'pinterest-for-woocommerce'
								) }
								<br />
								<br />
								{ __(
									'Using conversion tags means you agree to our',
									'pinterest-for-woocommerce'
								) }{ ' ' }
								<Button
									isLink
									href={
										wcSettings.pin4wc.pinterestLinks
											.adGuidelines
									}
									target="_blank"
								>
									{ __(
										'Ad Guidelines',
										'pinterest-for-woocommerce'
									) }
								</Button>{ ' ' }
								{ __( 'and', 'pinterest-for-woocommerce' ) }{ ' ' }
								<Button
									isLink
									href={
										wcSettings.pin4wc.pinterestLinks
											.adDataTerms
									}
									target="_blank"
								>
									{ __(
										'Ad Data Terms',
										'pinterest-for-woocommerce'
									) }
								</Button>
							</>
						}
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
									<>
										<SelectControl
											label={ __(
												'Advertiser',
												'pinterest-for-woocommerce'
											) }
											labelPosition="top"
											value={
												appSettings.tracking_advertiser
											}
											onChange={ ( selectedAdvertiser ) =>
												handleOptionChange(
													'tracking_advertiser',
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
									</>
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
												'Please visit your Pinterest Dashboard and click “Create Ad”.',
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
													labelPosition="top"
													value={
														appSettings.tracking_tag
													}
													onChange={ (
														selectedTag
													) =>
														handleOptionChange(
															'tracking_tag',
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
