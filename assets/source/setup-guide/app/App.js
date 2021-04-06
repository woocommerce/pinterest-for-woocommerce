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
import SetupGuideHeader from './header';
import Connect from './steps/connect.js';
import Setup from './steps/setup.js';
import VerifyDomain from './steps/verifydomain.js';
import Ready from './steps/ready.js';
import TransientNotices from './transient-notices';
import './style.scss';

const App = ( { createNotice } ) => {

	const [step, setStep] = useState( {} );

	useEffect(() => {
		document.body.parentNode.classList.remove( 'wp-toolbar' );
		document.body.classList.remove( 'woocommerce-admin-is-loading' );
		document.body.classList.add( 'woocommerce-onboarding' );
		document.body.classList.add( 'woocommerce-setup-guide__body' );
		document.body.classList.add( 'woocommerce-admin-full-screen' );

		if ( pin4wcSetupGuide.error ) {
			createNotice(
				'error',
				pin4wcSetupGuide.error
			);
		}

		return () => {
			document.body.classList.remove( 'woocommerce-onboarding' );
			document.body.classList.remove( 'woocommerce-setup-guide__body' );
			document.body.classList.remove( 'woocommerce-admin-full-screen' );
			document.body.parentNode.classList.add( 'wp-toolbar' );
		}
	}, [])

	const getSteps = () => {
		const steps = [];

		steps.push( {
			key: 'connect',
			container: Connect,
			label: __( 'Connect', 'pinterest-for-woocommerce' ),
		} );
		steps.push( {
			key: 'verifydomain',
			container: VerifyDomain,
			label: __( 'Domain Verification', 'pinterest-for-woocommerce' ),
		} );
		steps.push( {
			key: 'setup',
			container: Setup,
			label: __( 'Setup', 'pinterest-for-woocommerce' ),
		} );
		steps.push( {
			key: 'ready',
			container: Ready,
			label: __( 'Ready!', 'pinterest-for-woocommerce' ),
		} );

		return steps;
	}

	getHistory().listen( () => {
		setStep( getCurrentStep() );
	} );

	const getCurrentStep = () => {
		const query = getQuery();

		const currentStep = getSteps().find( s => s.key === query.step );

		if ( ! currentStep ) {
			return getSteps()[ 0 ];
		}

		return currentStep;
	}

	const goToNextStep = () => {
		const currentStep = step;
		const currentStepIndex = getSteps().findIndex(
			s => s.key === currentStep.key
		);

		const nextStep = getSteps()[ currentStepIndex + 1 ];

		if ( typeof nextStep === 'undefined' ) {
			return;
		}

		return updateQueryString( { step: nextStep.key } );
	}

	const stepKey = step.key;

	if ( ! step.container ) {
		setStep( getCurrentStep() );

		return <Spinner />;
	}

	const container = createElement( step.container, {
		query: getQuery(),
		step,
		goToNextStep,
	} );

	const steps = getSteps().map( ( _step ) =>
		pick( _step, [ 'key', 'label', 'isComplete' ] )
	);
	const classNames = `woocommerce-setup-guide__container ${ stepKey }`;


	return (
		<div className="woocommerce-layout">
			<Spinner />
			<div className="woocommerce-layout__main">
				<SetupGuideHeader currentStep={ stepKey } steps={ steps } />
				<TransientNotices />
				<div className={ classNames }>{ container }</div>
			</div>
		</div>
	)
}

export default compose(
	withDispatch( dispatch => {
		const { createNotice } = dispatch( 'core/notices' );

		return {
			createNotice,
		};
	})
)(App);
