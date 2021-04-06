/**
 * External dependencies
 */
 import { __ } from '@wordpress/i18n';
 import {
     Button,
     Card,
     CardBody,
     CardFooter,
     FlexItem,
     Stepper,
     __experimentalText as Text
 } from '@wordpress/components';
//  import { List } from '@woocommerce/components';
 
 const VerifyDomain = props => {
 
	// constructor( props ) {
		// super( props );

    this.initialState = {
        isPending: false,
        step: 'store_location',
        shippingZones: [],
    };

    this.state = this.initialState;
	// }

	// componentDidMount() {
	// 	this.reset();
	// }

	// reset() {
	// 	this.setState( this.initialState );
	// }

    const initiate = () => {

        this.setState( { isPending: true } );

		apiFetch( {
			path: WC_ADMIN_NAMESPACE + '/pppppp',
			method: 'POST',
		} )
			.then( ( response ) => {
				window.location = response.connectUrl;
			} )
			.catch( () => {
				createNotice( 'error', errorMessage );
				reject();
			} );
	};


    const getSteps = () => {
		const steps = [
			{
				key: 'request_verification_data',
				label: __( 'Requesting verification code from Pinterest', 'pinterest-for-woocommerce' ),
				description: __(
					'This is the description',
					'pinterest-for-woocommerce'
				),
			},
            {
				key: 'attempt_verification',
				label: __( 'Attempting auto-verification', 'pinterest-for-woocommerce' ),
				description: __(
					'This is the description',
					'pinterest-for-woocommerce'
				),
			},
		];

		return steps;
	}

    const { isPending, step } = this.state;
 
     return (
         <div className="woocommerce-setup-guide__connect">
             <div className="woocommerce-setup-guide__step-header">
                 <Text variant="title.small" as="h2">
                     { __( 'Verify your domain!', 'pinterest-for-woocommerce' ) }
                 </Text>
             </div>

            <Card className="woocommerce-task-card">
                <CardBody>
                    <Stepper
                        isPending={
                            isPending
                        }
                        isVertical
                        currentStep={ step }
                        steps={ getSteps() }
                    />

                    <Button
                        isPrimary
                        onClick={ initiate() }
                    >
                        { __(
                            'Verify',
                            'pinterest-for-woocommerce'
                        ) }
                    </Button>
                </CardBody>

                <CardFooter justify="center">
                     <FlexItem>
                         <div className="woocommerce-setup-guide__submit">
                             <Button
                                 isPrimary
                                 href={ pin4wcSetupGuide.adminUrl }
                             >
                                 { __(
                                     'Back to Dashboard',
                                     'pinterest-for-woocommerce'
                                 ) }
                             </Button>
                         </div>
                     </FlexItem>
                 </CardFooter>
            </Card>

         </div>
     )
 }
 
 export default VerifyDomain;
 