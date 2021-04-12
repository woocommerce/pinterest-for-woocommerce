/**
 * External dependencies
 */
import '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withDispatch } from '@wordpress/data';
import { createElement, useEffect, useState } from '@wordpress/element';
import { Spinner } from '@woocommerce/components';
import { pick } from 'lodash';
import {
	getHistory,
	getQuery,
	updateQueryString,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import AppHeader from '../components/AppHeader';
import SetupAccount from '../steps/SetupAccount';
import VerifyDomain from '../steps/VerifyDomain';
import ConfigureSettings from '../steps/ConfigureSettings';
import TransientNotices from '../transient-notices';

const WizardApp = ( { createNotice } ) => {
	const [ step, setStep ] = useState( {} );

	useEffect( () => {
		document.body.parentNode.classList.remove( 'wp-toolbar' );
		document.body.classList.remove( 'woocommerce-admin-is-loading' );
		document.body.classList.remove( 'woocommerce-embed-page' );
		document.body.classList.add( 'woocommerce-onboarding' );
		document.body.classList.add( 'woocommerce-setup-guide__body' );
		document.body.classList.add( 'woocommerce-setup-guide--wizard' );
		document.body.classList.add( 'woocommerce-admin-full-screen' );

		if ( pin4wcSetupGuide.error ) {
			createNotice( 'error', pin4wcSetupGuide.error );
		}

		return () => {
			document.body.classList.remove( 'woocommerce-onboarding' );
			document.body.classList.remove( 'woocommerce-setup-guide--wizard' );
			document.body.classList.remove( 'woocommerce-setup-guide__body' );
			document.body.classList.add( 'woocommerce-embed-page' );
			document.body.classList.remove( 'woocommerce-admin-full-screen' );
			document.body.parentNode.classList.add( 'wp-toolbar' );
		};
	}, [ createNotice ] );

	const getSteps = () => {
		const steps = [];

		steps.push( {
			key: 'setup-account',
			container: SetupAccount,
			label: __( 'Set up your account', 'pinterest-for-woocommerce' ),
		} );
		steps.push( {
			key: 'verify-domain',
			container: VerifyDomain,
			label: __( 'Verify your domain', 'pinterest-for-woocommerce' ),
		} );
		steps.push( {
			key: 'configure-settings',
			container: ConfigureSettings,
			label: __( 'Configure your settings', 'pinterest-for-woocommerce' ),
		} );

		return steps;
	};

	getHistory().listen( () => {
		setStep( getCurrentStep() );
	} );

	const getCurrentStep = () => {
		const query = getQuery();

		const currentStep = getSteps().find( ( s ) => s.key === query.step );

		if ( ! currentStep ) {
			return getSteps()[ 0 ];
		}

		return currentStep;
	};

	const goToNextStep = () => {
		const currentStep = step;
		const currentStepIndex = getSteps().findIndex(
			( s ) => s.key === currentStep.key
		);

		const nextStep = getSteps()[ currentStepIndex + 1 ];

		if ( typeof nextStep === 'undefined' ) {
			return;
		}

		return updateQueryString( { step: nextStep.key } );
	};

	const stepKey = step.key;

	if ( ! step.container ) {
		setStep( getCurrentStep() );

		return <Spinner />;
	}

	const container = createElement( step.container, {
		query: getQuery(),
		step,
		goToNextStep,
		view: 'wizard',
	} );

	const steps = getSteps().map( ( _step ) =>
		pick( _step, [ 'key', 'label', 'isComplete' ] )
	);
	const classNames = `woocommerce-setup-guide__container ${ stepKey }`;

	return (
		<div className="woocommerce-layout">
			<Spinner />
			<div className="woocommerce-layout__main">
				<AppHeader currentStep={ stepKey } steps={ steps } />
				<TransientNotices />
				<div className={ classNames }>{ container }</div>
			</div>
		</div>
	);
};

export default compose(
	withDispatch( ( dispatch ) => {
		const { createNotice } = dispatch( 'core/notices' );

		return {
			createNotice,
		};
	} )
)( WizardApp );
