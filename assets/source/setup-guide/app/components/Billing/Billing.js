/**
 * External dependencies
 */
import classnames from 'classnames';
import { __ } from '@wordpress/i18n';
import { Spinner } from '@woocommerce/components';
import { recordEvent } from '@woocommerce/tracks';
import {
	Button,
	CardBody,
	Flex,
	FlexItem,
	FlexBlock,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Clicking on "Setup Billing" button.
 *
 * @event wcadmin_pfw_billing_setup_button_click
 */

/**
 * Clicking on "Go to billing settings" button.
 *
 * @event wcadmin_pfw_go_to_billing_button_click
 */

const Billing = ( { accountData } ) => {
	const isBillingSetup = accountData?.is_billing_setup;
	const statusLabe = classnames( 'pfw-billing-info', {
		'pfw-billing-info--status-success': isBillingSetup === true,
		'pfw-billing-info--status-error': isBillingSetup === false,
	} );

	let element = null;

	if ( isBillingSetup === undefined ) {
		element = <Spinner className="billing-info__preloader" />;
	}

	if ( isBillingSetup === true ) {
		element = (
			<>
				<FlexBlock className={ statusLabe }>
					<Text variant="body">
						{ __(
							'Billing Setup Correctly',
							'pinterest-for-woocommerce'
						) }
					</Text>
				</FlexBlock>

				<FlexItem>
					<Button
						variant="link"
						href={
							wcSettings.pinterest_for_woocommerce
								.billingSettingsUrl
						}
						onClick={ () =>
							recordEvent( 'pfw_go_to_billing_button_click' )
						}
					>
						{ __(
							'Go to billing settings',
							'pinterest-for-woocommerce'
						) }
					</Button>
				</FlexItem>
			</>
		);
	}

	if ( isBillingSetup === false ) {
		element = (
			<>
				<FlexBlock className={ statusLabe }>
					<Text variant="body">
						{ __(
							'No Valid Billing Setup Found',
							'pinterest-for-woocommerce'
						) }
					</Text>
				</FlexBlock>

				<FlexItem>
					<Button
						isLink
						href={
							wcSettings.pinterest_for_woocommerce
								.billingSettingsUrl
						}
						onClick={ () =>
							recordEvent( 'pfw_billing_setup_button_click' )
						}
					>
						{ __( 'Setup Billing', 'pinterest-for-woocommerce' ) }
					</Button>
				</FlexItem>
			</>
		);
	}

	return (
		<CardBody size="large">
			<Flex direction="row" className="pfw-billing-info">
				{ element }
			</Flex>
		</CardBody>
	);
};

export default Billing;
