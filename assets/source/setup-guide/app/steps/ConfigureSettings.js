/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardBody,
	CheckboxControl,
	Icon,
	Tooltip,
	__experimentalText as Text
} from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';

/**
 * Internal dependencies
 */
 import StepHeader from '../StepHeader';
 import StepOverview from '../StepOverview';

const ALLOWED_OPTIONS = [
	'track_conversions',
	'enhanced_match_support',
	'save_to_pinterest',
	'is_setup_complete'
];

const ConfigureSettings = ({ pin4wc, createNotice, updateOptions }) => {
	const [ options, setOptions ] = useState( {} );

	useEffect(() => {
		if ( options !== pin4wc ) {
			setOptions( pin4wc );
		}
	}, [pin4wc])

	const handleOptionChange = async name => {
		if ( ALLOWED_OPTIONS.includes( name ) ) {
			const oldOptions = Object.assign( {}, options );
			const newOptions = {
				...options,
				[name]: ! options[ name ]
			};

			setOptions( newOptions );

			const update = await updateOptions( {
				[pin4wcSetupGuide.optionsName]: newOptions
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
		}
	}

	const handleCompleteSetup = async () => {
		handleOptionChange( 'is_setup_complete', true );
	}

	return (
		<div className="woocommerce-setup-guide__configure-settings">
			<StepHeader
				title={ __( 'Configure your settings' ) }
				subtitle={ __( 'Step Three' ) }
			/>

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __( 'Setup tracking and Rich Pins' ) }
						description={ __( 'Use description text to help users understand more' ) }
						link='#'
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						<CardBody size="large">
							<Text className="woocommerce-setup-guide__checkbox-heading" variant="subtitle">{ __( 'Tracking', 'pinterest-for-woocommerce' ) }</Text>
							<CheckboxControl
								label={ __( 'Track conversions', 'pinterest-for-woocommerce' ) }
								help={
									<Tooltip
										text={ __( 'Here goes the help text', 'pinterest-for-woocommerce' ) }
										position="center right"
									>
										<span><Icon icon="editor-help" /></span>
									</Tooltip>
								}
								checked={ options.track_conversions }
								className="woocommerce-setup-guide__checkbox-group"
								onChange={ () => handleOptionChange( 'track_conversions' ) }
							/>
							<CheckboxControl
								label={ __( 'Enhanced Match support', 'pinterest-for-woocommerce' ) }
								help={
									<Tooltip
										text={ __( 'Here goes the help text', 'pinterest-for-woocommerce' ) }
										position="center right"
									>
										<span><Icon icon="editor-help" /></span>
									</Tooltip>
								}
								checked={ options.enhanced_match_support }
								className="woocommerce-setup-guide__checkbox-group"
								onChange={ () => handleOptionChange( 'enhanced_match_support' ) }
							/>
							<Text className="woocommerce-setup-guide__checkbox-heading" variant="subtitle">{ __( 'Rich Pins', 'pinterest-for-woocommerce' ) }</Text>
							<CheckboxControl
								label={ __( 'Save to Pinterest', 'pinterest-for-woocommerce' ) }
								help={
									<Tooltip
										text={ __( 'Here goes the help text', 'pinterest-for-woocommerce' ) }
										position="center right"
									>
										<span><Icon icon="editor-help" /></span>
									</Tooltip>
								}
								checked={ options.save_to_pinterest }
								className="woocommerce-setup-guide__checkbox-group"
								onChange={ () => handleOptionChange( 'save_to_pinterest' ) }
							/>
						</CardBody>
					</Card>

					<div className="woocommerce-setup-guide__footer-button">
						<Button
							isPrimary
							href={ pin4wcSetupGuide.adminUrl }
							onClick={ handleCompleteSetup }
						>
							{ __(
								'Complete Setup',
								'pinterest-for-woocommerce'
							) }
						</Button>
					</div>
				</div>
			</div>
		</div>
	);
}

export default compose(
	withSelect( select => {
		const { getOption } = select( OPTIONS_STORE_NAME );

		return {
			pin4wc: getOption( pin4wcSetupGuide.optionsName ) || [],
		}
	}),
	withDispatch( dispatch => {
		const { createNotice } = dispatch( 'core/notices' );
		const { updateOptions } = dispatch( OPTIONS_STORE_NAME );

		return {
			createNotice,
			updateOptions
		};
	})
)(ConfigureSettings);
