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

const Connect = () => {

	return (
		<div className="woocommerce-setup-guide__connect">
			<div className="woocommerce-setup-guide__step-header">
				<Text variant="title.small" as="h2">
					{ __( 'Pinterest for WooCommerce', 'pinterest-for-woocommerce' ) }
				</Text>
			</div>
			<Card>
				<CardBody>
					<Text variant="body">
						{ __(
							"Let\'s walk through a few steps to get the plugin configured and connected to Pinterest.",
							'pinterest-for-woocommerce'
						) }
					</Text>

					<Button
						isPrimary
						href={ pin4wcSetupGuide.serviceLoginUrl }
					>
						{ __(
							'Login to Pinterest',
							'pinterest-for-woocommerce'
						) }
					</Button>
				</CardBody>

				<CardFooter justify="center">
					<FlexItem>
						<div className="woocommerce-setup-guide__submit">

						</div>
					</FlexItem>
				</CardFooter>
			</Card>
		</div>
	)
}

export default Connect;
