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

const SetupTracking = ( {
    goToNextStep,
    pin4wc,
    updateOptions,
    createNotice,
    view,
 } ) => {
    const [ status, setStatus ] = useState( 'idle' );
	const [ options, setOptions ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );

	useEffect( () => {
		if ( options !== pin4wc ) {
			setOptions( pin4wc );
		}
	}, [ pin4wc, options ] );

    const StepButton = () => {
        if ( 'idle' === status ) {
            return '';
        }

        const buttonLabels = {
            error: __( 'Try Again', 'pinterest-for-woocommerce' ),
            success: __( 'Continue', 'pinterest-for-woocommerce' ),
        };

        return (
            <Button
                isPrimary
                disabled={ status === 'pending' }
                onClick={
                    status === 'success' ? goToNextStep : false
                }
            >
                { buttonLabels[ status ] }
            </Button>
        );
    };

    return (
        <div className="woocommerce-setup-guide__setup-tracking">
            { view === 'wizard' && (
                <StepHeader
                    title={ __( 'Set up tracking', 'pinterest-for-woocommerce' ) }
                    subtitle={ __( 'Step Three', 'pinterest-for-woocommerce' ) }
                />
            ) }

            <div className="woocommerce-setup-guide__step-columns">
                <div className="woocommerce-setup-guide__step-column">
                    <StepOverview
                        title={ __( 'Select your advertiser and tag', 'pinterest-for-woocommerce' ) }
                        link={ wcSettings.pin4wc.pinterestLinks.SetupTracking }
                    />
                </div>
                <div className="woocommerce-setup-guide__step-column">
                    <Card>
                        { undefined !== pin4wc ? (
                            <CardBody size="large">
                                Body
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
)( SetupTracking );
