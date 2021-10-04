/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Spinner } from '@woocommerce/components';
import { addQueryArgs } from '@wordpress/url';
import { useState } from '@wordpress/element';
import {
	Button,
	CardBody,
	Flex,
	FlexBlock,
	FlexItem,
	SelectControl,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

const BusinessAccountSelection = ( { businessAccounts } ) => {
	const [ targetBusinessId, setTargetBusinessId ] = useState(
		undefined !== businessAccounts && businessAccounts.length > 0
			? businessAccounts[ 0 ][ 'value' ]
			: null
	);

	const handleConnectToBusiness = () => {
		const newURL = addQueryArgs(
			wcSettings.pinterest_for_woocommerce.switchBusinessAccountUrl,
			{ business_id: targetBusinessId }
		);

		window.location = new URL( newURL );
	};

	if ( businessAccounts === undefined ) {
		return (
			<CardBody size="large">
				<Spinner />
			</CardBody>
		);
	}

		return (
		<CardBody size="large" className="business-connection">
			{ businessAccounts.length > 0 ? (
				<>
					<Text variant="subtitle">
						{ __(
							'Select a business account',
							'pinterest-for-woocommerce'
						) }
					</Text>
					<Flex>
						<FlexBlock>
							<SelectControl
								options={ businessAccounts }
								selected={ targetBusinessId }
								onChange={ ( businessId ) =>
									setTargetBusinessId( businessId )
								}
							/>
						</FlexBlock>
						<FlexItem>
							<Button
								isSecondary
								onClick={ handleConnectToBusiness }
							>
								{ __( 'Connect', 'pinterest-for-woocommerce' ) }
							</Button>
						</FlexItem>
					</Flex>
				</>
			) : (
				<Flex>
					<FlexBlock>
						<Text variant="subtitle">
							{ __(
								'No business account detected',
								'pinterest-for-woocommerce'
							) }
						</Text>
						<Text variant="body">
							{ __(
								'A Pinterest business account is required to connect Pinterest with your WooCommerce store.',
								'pinterest-for-woocommerce'
							) }
						</Text>
					</FlexBlock>
					<FlexItem>
						<Button
							isSecondary
							href={
								wcSettings.pinterest_for_woocommerce
									.createBusinessAccountUrl
							}
							target="_blank"
						>
							{ __(
								'Create business account',
								'pinterest-for-woocommerce'
							) }
						</Button>
					</FlexItem>
				</Flex>
			) }
		</CardBody>
	);
};

export default BusinessAccountSelection;
