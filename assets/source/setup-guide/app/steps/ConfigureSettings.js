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

const ConfigureSettings = () => {
	 return (
		<div className="woocommerce-setup-guide__configure-settings">
			<StepHeader
				title={ __( 'Configure your settings' ) }
				subtitle={ __( 'Step Three' ) }
			/>
			<div class="woocommerce-setup-guide__step-columns">
				...
			</div>
		</div>
	)
}

export default ConfigureSettings;
