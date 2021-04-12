/**
 * External dependencies
 */
import { filter } from 'lodash';
import { Stepper } from '@woocommerce/components';
import { updateQueryString } from '@woocommerce/navigation';

const AppHeader = ( props ) => {
	const renderStepper = () => {
		const { currentStep, steps } = props;
		const visibleSteps = filter( steps, ( step ) => !! step.label );
		const currentStepIndex = visibleSteps.findIndex(
			( step ) => step.key === currentStep
		);

		visibleSteps.map( ( step, index ) => {
			const previousStep = visibleSteps[ index - 1 ];

			if ( index < currentStepIndex ) {
				step.isComplete = true;
			}

			if ( ! previousStep || previousStep.isComplete ) {
				step.onClick = ( key ) => updateQueryString( { step: key } );
			}
			return step;
		} );

		return <Stepper steps={ visibleSteps } currentStep={ currentStep } />;
	};

	const currentStep = props.steps.find(
		( s ) => s.key === props.currentStep
	);

	if ( ! currentStep || ! currentStep.label ) {
		return null;
	}

	return (
		<div className="woocommerce-setup-guide__header">
			{ renderStepper() }
		</div>
	);
};

export default AppHeader;
