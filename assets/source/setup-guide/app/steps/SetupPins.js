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

const ALLOWED_OPTIONS = [
	'track_conversions',
	'enhanced_match_support',
	'save_to_pinterest',
	'is_setup_complete',
	'rich_pins_on_posts',
	'rich_pins_on_products',
];

const SetupPins = ( { pin4wc, createNotice, updateOptions, view } ) => {
	const [ options, setOptions ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		if ( options !== pin4wc ) {
			setOptions( pin4wc );
		}
	}, [ pin4wc, options ] );

	const handleOptionChange = async ( name, value ) => {
		if ( ! ALLOWED_OPTIONS.includes( name ) ) {
			return;
		}

		setIsSaving( true );

		const oldOptions = Object.assign( {}, options );
		const newOptions = {
			...options,
			[ name ]: value ?? ! options[ name ],
		};

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
						title={ __( 'Set up pins and Rich Pins', 'pinterest-for-woocommerce' ) }
						description={ __(
							'Use description text to help users understand more',
                            'pinterest-for-woocommerce'
						) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							{ Object.keys( options ).length > 0 ? (
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
										checked={ options.track_conversions }
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
											options.enhanced_match_support
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
										checked={ options.rich_pins_on_products }
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
										checked={ options.rich_pins_on_posts }
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
										checked={ options.save_to_pinterest }
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

export default compose(
	withSelect( ( select ) => {
		const { getOption } = select( OPTIONS_STORE_NAME );

		return {
			pin4wc: getOption( wcSettings.pin4wc.optionsName ) || [],
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
)( SetupPins );
