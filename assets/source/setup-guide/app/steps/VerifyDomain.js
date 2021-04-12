/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch, withSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody
 } from '@wordpress/components';
import { OPTIONS_STORE_NAME } from '@woocommerce/data';
import { Spinner } from '@woocommerce/components';

/**
  * Internal dependencies
  */
import StepHeader from '../components/StepHeader';
import StepOverview from '../components/StepOverview';
import StepStatus from '../components/StepStatus';

const VerifyDomain = ({ goToNextStep, pin4wc, createNotice, view }) => {
	const [ status, setStatus ] = useState( 'idle' );

	const isDomainVerified = () => {
		const isDomainVerified = undefined === pin4wc ? undefined : pin4wcSetupGuide.domainToVerify in pin4wc?.account_data?.verified_domains;

		if ( true === isDomainVerified && 'success' !== status ) {
			setStatus( 'success' );
		}

		return isDomainVerified;
	}

	const buttonLabels = {
		idle: __( 'Start Verification', 'pinterest-for-woocommerce' ),
		pending: __( 'Verifying Domain', 'pinterest-for-woocommerce' ),
		error: __( 'Try Again', 'pinterest-for-woocommerce' ),
		success: __( 'Continue', 'pinterest-for-woocommerce' )
	}

	const handleVerifyDomain = () => {
		setStatus( 'pending' );

		apiFetch( {
			path: pin4wcSetupGuide.apiRoute + '/domain_verification',
			method: 'POST',
		} ).then( () => {
			setStatus( 'success' );
		} ).catch( () => {
			setStatus( 'error' );

			createNotice(
				'error',
				__(
					'Couldn’t verify your domain.',
					'pinterest-for-woocommerce'
				)
			);
		} );
	}

	return (
		<div className="woocommerce-setup-guide__verify-domain">
			{ 'wizard' === view &&
				<StepHeader
					title={ __( 'Verify your domain' ) }
					subtitle={ __( 'Step Two' ) }
				/>
			}

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __( 'Verify your domain' ) }
						description={ __( 'Claim your website yo get access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site and let people know where they can find more of you content.' ) }
						link={ pin4wcSetupGuide.pinterestLinks.verifyDomain }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						{ undefined !== isDomainVerified()
							? <CardBody size="large">
								<StepStatus
									label={ pin4wcSetupGuide.domainToVerify }
									status={ status }
									options={ pin4wc }
								/>

							{ 'settings' === view && ! isDomainVerified() &&
								<Button
									isPrimary
									disabled={ 'pending' === status }
									onClick={ 'success' === status ? goToNextStep : handleVerifyDomain }
								>
									{ buttonLabels[ status ] }
								</Button>
							}
							</CardBody>
							: <Spinner />
						}
					</Card>

					{ 'wizard' === view && isDomainVerified() &&
						<div className="woocommerce-setup-guide__footer-button">
							<Button
								isPrimary
								disabled={ 'pending' === status }
								onClick={ 'success' === status ? goToNextStep : handleVerifyDomain }
							>
								{ buttonLabels[ status ] }
							</Button>
						</div>
					}
				</div>
			</div>
		</div>
	);
}

export default compose(
	withSelect( select => {
		const { getOption } = select( OPTIONS_STORE_NAME );

		return {
			pin4wc: getOption( pin4wcSetupGuide.optionsName ),
		}
	}),
	withDispatch( dispatch => {
		const { createNotice } = dispatch( 'core/notices' );

		return {
			createNotice,
		};
	})
)(VerifyDomain);
