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

const VerifyDomain = () => {
	return (
		<div className="woocommerce-setup-guide__verify-domain">
			<StepHeader
				title={ __( 'Verify your domain' ) }
				subtitle={ __( 'Step Two' ) }
			/>

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __( 'Verify your domain' ) }
						description={ __( 'Claim your website yo get access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site and let people know where they can find more of you content.' ) }
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

export default VerifyDomain;
