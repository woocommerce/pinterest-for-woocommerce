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

 const VerifyDomain = () => {
	return (
		<div className="woocommerce-setup-guide__verify-domain">
			<StepHeader
				title={ __( 'Verify your domain' ) }
				subtitle={ __( 'Step Two' ) }
			/>
			<div class="woocommerce-setup-guide__step-columns">
				...
			</div>
		</div>
	)
}

export default VerifyDomain;
