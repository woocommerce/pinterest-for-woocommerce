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

const SetupAccount = () => {
	return (
		<div className="woocommerce-setup-guide__setup-account">
			<StepHeader
				title={ __( 'Set up your account' ) }
				subtitle={ __( 'Step One' ) }
				description={ __( 'Use description text to help users understand what accounts they need to connect, and why they need to connect it.' ) }
			/>
			<div class="woocommerce-setup-guide__step-columns">
				...
			</div>
		</div>
	)
}

export default SetupAccount;
