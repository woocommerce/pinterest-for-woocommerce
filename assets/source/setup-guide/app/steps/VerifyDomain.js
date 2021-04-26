/**
 * External dependencies
 */
 import { __ } from '@wordpress/i18n';
 import { compose } from '@wordpress/compose';
 import { withDispatch, withSelect } from '@wordpress/data';
 import { useState } from '@wordpress/element';
 import apiFetch from '@wordpress/api-fetch';
 import { Button, Card, CardBody } from '@wordpress/components';
 import { OPTIONS_STORE_NAME } from '@woocommerce/data';
 import { Spinner } from '@woocommerce/components';
 
 /**
  * Internal dependencies
  */
 import StepHeader from '../components/StepHeader';
 import StepOverview from '../components/StepOverview';
 import StepStatus from '../components/StepStatus';
 
 const VerifyDomain = ( {
	 goToNextStep,
	 pin4wc,
	 updateOptions,
	 createNotice,
	 view
 } ) => {
	 const [ status, setStatus ] = useState( 'idle' );
 
	 const isDomainVerified = () => {
		 const result =
			 undefined === pin4wc
				 ? undefined
				 : undefined === pin4wc?.account_data?.verified_domains
					 ? false
					 : pin4wc?.account_data?.verified_domains.includes( pin4wcSetupGuide.domainToVerify );
 
		 if ( result === true && status !== 'success' ) {
			 setStatus( 'success' );
		 }
 
		 return result;
	 };
 
	 const handleVerifyDomain = () => {
		 setStatus( 'pending' );
 
		 apiFetch( {
			 path: pin4wcSetupGuide.apiRoute + '/domain_verification',
			 method: 'POST',
		 } )
			 .then( response => {
				 setStatus( 'success' );
 
				 const newOptions = {
					...pin4wc,
					[ 'account_data' ]: response.account_data
				};
 
				 updateOptions( {
					 [ pin4wcSetupGuide.optionsName ]: newOptions,
				 } );
			 } )
			 .catch( error => {
				 setStatus( 'error' );
 
				 createNotice(
					 'error',
					 error.message ||
						 __(
						 'Couldn’t verify your domain.',
						 'pinterest-for-woocommerce'
					 )
				 );
			 } );
	 };
 
	const StepButton = () => {
		const buttonLabels = {
			idle: __( 'Start Verification', 'pinterest-for-woocommerce' ),
			pending: __( 'Verifying Domain', 'pinterest-for-woocommerce' ),
			error: __( 'Try Again', 'pinterest-for-woocommerce' ),
			success: __( 'Continue', 'pinterest-for-woocommerce' ),
		};

		return (
			<Button
				isPrimary
				disabled={ status === 'pending' }
				onClick={
					status === 'success'
						? goToNextStep
						: handleVerifyDomain
				}
			>
				{ buttonLabels[ status ] }
			</Button>
		);
	}

	 return (
		 <div className="woocommerce-setup-guide__verify-domain">
			 { view === 'wizard' && (
				 <StepHeader
					 title={ __( 'Verify your domain' ) }
					 subtitle={ __( 'Step Two' ) }
				 />
			 ) }
 
			 <div className="woocommerce-setup-guide__step-columns">
				 <div className="woocommerce-setup-guide__step-column">
					 <StepOverview
						 title={ __( 'Verify your domain' ) }
						 description={ __(
							 'Claim your website yo get access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site and let people know where they can find more of you content.'
						 ) }
						 link={ pin4wcSetupGuide.pinterestLinks.verifyDomain }
					 />
				 </div>
				 <div className="woocommerce-setup-guide__step-column">
					 <Card>
						 { undefined !== isDomainVerified() ? (
							 <CardBody size="large">
								 <StepStatus
									 label={ pin4wcSetupGuide.domainToVerify }
									 status={ status }
									 options={ pin4wc }
								 />
 
								 { view === 'settings' && ! isDomainVerified() && (
									<StepButton />
								) }
							</CardBody>
						) : (
							<CardBody size="large">
								<Spinner />
							</CardBody>
						) }
					</Card>

					{ view === 'wizard' && (
								 ) }
							 </CardBody>
						 ) : (
							 <CardBody size="large">
								 <Spinner />
							 </CardBody>
						 ) }
					 </Card>
 
					 { view === 'wizard' && (
						 <div className="woocommerce-setup-guide__footer-button">
							<StepButton />
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
			 pin4wc: getOption( pin4wcSetupGuide.optionsName ),
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
 )( VerifyDomain );
 