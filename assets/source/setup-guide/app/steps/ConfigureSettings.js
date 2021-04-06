/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardBody,
	CardFooter,
	FlexItem,
	__experimentalText as Text
} from '@wordpress/components';

/**
 * Internal dependencies
 */
 import StepHeader from '../StepHeader';
 import StepOverview from '../StepOverview';

const ConfigureSettings = () => {
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
					Column B
				</div>
			</div>
		</div>
	);
}

export default ConfigureSettings;
