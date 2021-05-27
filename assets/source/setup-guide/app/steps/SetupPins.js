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
	CheckboxControl,
	Icon,
	__experimentalText as Text,
} from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';

const SetupPins = ( {
	appSettings,
	setAppSettings,
	createNotice,
	view
} ) => {
	const [ isSaving, setIsSaving ] = useState( false );

	const handleOptionChange = async ( name, value ) => {
		setIsSaving( true );

		const update = await setAppSettings( {
			[ name ]: value ?? ! appSettings[ name ],
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

	const handleCompleteSetup = async () => {
		await handleOptionChange( 'is_setup_complete', true );

		window.location = new URL(
			decodeEntities( wcSettings.pin4wc.adminUrl )
		);
	};

	return (
		<div className="woocommerce-setup-guide__setup-pins">
			{ view === 'wizard' && (
				<StepHeader
					title={ __( 'Set up pins', 'pinterest-for-woocommerce' ) }
					subtitle={ __( 'Step Four', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Set up pins and Rich Pins',
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
							{ undefined !== appSettings && Object.keys( appSettings ).length > 0 ? (
								<>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Tracking',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Track conversions',
											'pinterest-for-woocommerce'
										) }
										checked={ appSettings.track_conversions }
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'track_conversions'
											)
										}
									/>
									<CheckboxControl
										label={ __(
											'Enhanced Match support',
											'pinterest-for-woocommerce'
										) }
										help={
											<Button
												isLink
												href={
													wcSettings.pin4wc
														.pinterestLinks
														.enhancedMatch
												}
												target="_blank"
											>
												<Icon icon="editor-help" />
											</Button>
										}
										checked={
											appSettings.enhanced_match_support
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'enhanced_match_support'
											)
										}
									/>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Rich Pins',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Enable Rich Pins for Products',
											'pinterest-for-woocommerce'
										) }
										checked={
											appSettings.rich_pins_on_products
										}
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'rich_pins_on_products'
											)
										}
									/>
									<CheckboxControl
										label={ __(
											'Enable Rich Pins for Posts',
											'pinterest-for-woocommerce'
										) }
										checked={ appSettings.rich_pins_on_posts }
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'rich_pins_on_posts'
											)
										}
									/>
									<Text
										className="woocommerce-setup-guide__checkbox-heading"
										variant="subtitle"
									>
										{ __(
											'Save to Pinterest',
											'pinterest-for-woocommerce'
										) }
									</Text>
									<CheckboxControl
										label={ __(
											'Save to Pinterest',
											'pinterest-for-woocommerce'
										) }
										checked={ appSettings.save_to_pinterest }
										className="woocommerce-setup-guide__checkbox-group"
										onChange={ () =>
											handleOptionChange(
												'save_to_pinterest'
											)
										}
									/>
								</>
							) : (
								<Spinner />
							) }
						</CardBody>
					</Card>

					{ view === 'wizard' && (
						<div className="woocommerce-setup-guide__footer-button">
							<Button
								isPrimary
								onClick={ handleCompleteSetup }
								disabled={ isSaving }
							>
								{ isSaving
									? __(
											'Saving settings…',
											'pinterest-for-woocommerce'
									  )
									: __(
											'Complete Setup',
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

export default SetupPins;
