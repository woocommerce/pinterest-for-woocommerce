/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import StepHeader from '../StepHeader';
import StepOverview from '../StepOverview';

const SetupAccount = () => {
	return (
		<div className="woocommerce-setup-guide__setup-account">
			<StepHeader
				title={ __( 'Set up your account' ) }
				subtitle={ __( 'Step One' ) }
				description={ __( 'Use description text to help users understand what accounts they need to connect, and why they need to connect it.' ) }
			/>

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __( 'Pinterest Account' ) }
						description={ __( 'Use description text to help users understand more' ) }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					Column B
				</div>
			</div>
		</div>
	);
}

export default SetupAccount;
