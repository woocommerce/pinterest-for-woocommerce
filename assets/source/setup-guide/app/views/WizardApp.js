/**
 * External dependencies
 */
import '@wordpress/notices';
import { __ } from '@wordpress/i18n';
import { createElement, useState, useEffect } from '@wordpress/element';
import { Spinner, Stepper } from '@woocommerce/components';
import {
	getHistory,
	getQuery,
	updateQueryString,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import SetupAccount from '../steps/SetupAccount';
import ClaimWebsite from '../steps/ClaimWebsite';
import SetupTracking from '../steps/SetupTracking';
import OnboardingTopBar from '../components/TopBar';
import TransientNotices from '../components/TransientNotices';
import {
	useSettingsSelect,
	useBodyClasses,
	useCreateNotice,
} from '../helpers/effects';

const WizardApp = () => {
	const [ currentStep, setCurrentStep ] = useState();
	const [ isConnected, setIsConnected ] = useState(
		wcSettings.pinterest_for_woocommerce.isConnected
	);

	const [ isBusinessConnected, setIsBusinessConnected ] = useState(
		wcSettings.pinterest_for_woocommerce.isBusinessConnected
	);

	useEffect( () => {
		if ( ! isConnected ) {
			setIsBusinessConnected( false );
		}
	}, [ isConnected, setIsBusinessConnected ] );

	const appSettings = useSettingsSelect();

	useBodyClasses( 'wizard' );
	useCreateNotice()( wcSettings.pinterest_for_woocommerce.error );

	const steps = [
		{
			key: 'setup-account',
			container: SetupAccount,
			label: __(
				'Set up your business account',
				'pinterest-for-woocommerce'
			),
			props: {
				setIsConnected,
				isConnected,
				setIsBusinessConnected,
				isBusinessConnected,
			},
		},
		{
			key: 'claim-website',
			container: ClaimWebsite,
			label: __( 'Claim your website', 'pinterest-for-woocommerce' ),
		},
		{
			key: 'setup-tracking',
			container: SetupTracking,
			label: __(
				'Track conversions with the Pinterest tag',
				'pinterest-for-woocommerce'
			),
		},
	];

	const getSteps = () => {
		return steps.map( ( step, index ) => {
			const container = createElement( step.container, {
				query: getQuery(),
				step,
				goToNextStep: () => goToNextStep( step ),
				view: 'wizard',
				...step.props,
			} );

			step.content = (
				<div
					className={ `woocommerce-setup-guide__container ${ step.key }` }
				>
					{ container }
				</div>
			);

			const previousStep = steps[ index - 1 ];

			if ( ! previousStep || previousStep.isComplete ) {
				step.onClick = ( key ) => updateQueryString( { step: key } );
			}

			return step;
		} );
	};

	const getCurrentStep = () => {
		const query = getQuery();
		const step = steps.find( ( s ) => s.key === query.step );

		if ( ! step ) {
			return steps[ 0 ].key;
		}

		return step.key;
	};

	const goToNextStep = ( step ) => {
		const currentStepIndex = steps.findIndex( ( s ) => s.key === step.key );

		const nextStep = steps[ currentStepIndex + 1 ];

		if ( typeof nextStep === 'undefined' ) {
			return;
		}

		return updateQueryString( { step: nextStep.key } );
	};

	getHistory().listen( () => {
		setCurrentStep( getCurrentStep() );
	} );

	if ( ! currentStep ) {
		setCurrentStep( getCurrentStep() );

		return <Spinner />;
	}

	return (
		<>
			<OnboardingTopBar />
			<div className="woocommerce-setup-guide__main">
				<TransientNotices />
				{ appSettings ? (
					<Stepper currentStep={ currentStep } steps={ getSteps() } />
				) : (
					<Spinner />
				) }
			</div>
		</>
	);
};

export default WizardApp;
