/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	CardBody,
	Flex,
	FlexBlock,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

const BusinessAccountSelection = ( { businessAccounts } ) => {
	return (
		<CardBody size="large">
			{ businessAccounts.length > 0 ? (
				<Flex>
					<FlexBlock className="is-connected">
						<Text variant="subtitle">
							{ __(
								'Select a business account',
								'pinterest-for-woocommerce'
							) }
						</Text>
					</FlexBlock>
				</Flex>
			) : (
				<Flex>
					<FlexBlock>
						<Text variant="subtitle">
							{ __(
								'No business account detected',
								'pinterest-for-woocommerce'
							) }
						</Text>
					</FlexBlock>
				</Flex>
			) }
		</CardBody>
	);
};

export default BusinessAccountSelection;
