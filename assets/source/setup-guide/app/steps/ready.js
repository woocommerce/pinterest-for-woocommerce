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
	__experimentalText as Text
} from '@wordpress/components';
import { List } from '@woocommerce/components';

const Ready = props => {

	const nextSteps = [
		{
			title: __( 'Map Fields', 'pinterest-for-woocommerce' ),
			content: __( 'Customer fields are mapped to Salesforce Contact.', 'pinterest-for-woocommerce' ),
			after: <Button isSecondary href={ pin4wcSetupGuide.reviewFieldsUrl }>{ __( 'Review Fields', 'pinterest-for-woocommerce' ) }</Button>,
		},
		{
			title: __( 'Settings', 'pinterest-for-woocommerce' ),
			content: __( 'You can check your configuration in \n\r[Marketing > Pinterest]', 'pinterest-for-woocommerce' ),
			after: <Button isSecondary href={ pin4wcSetupGuide.settingsUrl }>{ __( 'Go to Settings', 'pinterest-for-woocommerce' ) }</Button>,
		},
	];

	return (
		<div className="woocommerce-setup-guide__connect">
			<div className="woocommerce-setup-guide__step-header">
				<Text variant="title.small" as="h2">
					{ __( 'Pinterest for WooCommerce is ready!', 'pinterest-for-woocommerce' ) }
				</Text>
			</div>
			<Card>
				<CardBody>
					<List items={ nextSteps } />
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

export default Ready;
