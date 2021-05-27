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
import { isDomainVerified } from '../helpers/conditionals'

const ClaimWebsite = ( {
	goToNextStep,
	appSettings,
	setAppSettings,
	createNotice,
	view,
} ) => {
	const [ status, setStatus ] = useState( 'idle' );

	const handleClaimWebsite = async () => {
		setStatus( 'pending' );

		try {
			const results = await apiFetch( {
				path: wcSettings.pin4wc.apiRoute + '/domain_verification',
				method: 'POST',
			} );

			setStatus( 'success' );

			setAppSettings( { account_data: results.account_data } );
		} catch ( error ) {
			setStatus( 'error' );

			createNotice(
				'error',
				error.message ||
					__(
						'Couldn’t verify your domain.',
						'pinterest-for-woocommerce'
					)
			);
		}
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
					status === 'success' ? goToNextStep : handleClaimWebsite
				}
			>
				{ buttonLabels[ status ] }
			</Button>
		);
	};

	return (
		<div className="woocommerce-setup-guide__claim-website">
			{ view === 'wizard' && (
				<StepHeader
					title={ __(
						'Claim your website',
						'pinterest-for-woocommerce'
					) }
					subtitle={ __( 'Step Two', 'pinterest-for-woocommerce' ) }
				/>
			) }

			<div className="woocommerce-setup-guide__step-columns">
				<div className="woocommerce-setup-guide__step-column">
					<StepOverview
						title={ __(
							'Claim your website',
							'pinterest-for-woocommerce'
						) }
						description={ __(
							'Claim your website get access to analytics for the Pins you publish from your site, the analytics on Pins that other people create from your site and let people know where they can find more of you content.'
						) }
						link={ wcSettings.pin4wc.pinterestLinks.claimWebsite }
					/>
				</div>
				<div className="woocommerce-setup-guide__step-column">
					<Card>
						{ undefined !== isDomainVerified( appSettings ) ? (
							<CardBody size="large">
								<StepStatus
									label={ wcSettings.pin4wc.domainToVerify }
									status={ status }
									options={ appSettings }
								/>

								{ view === 'settings' &&
									! isDomainVerified( appSettings ) && <StepButton /> }
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
			pin4wc: getOption( wcSettings.pin4wc.optionsName ),
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
)( ClaimWebsite );
