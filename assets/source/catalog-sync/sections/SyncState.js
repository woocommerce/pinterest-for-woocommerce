/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { createInterpolateElement } from '@wordpress/element';
import { Icon, trendingUp as trendingUpIcon } from '@wordpress/icons';
import {
	Card,
	CardHeader,
	CardFooter,
	ExternalLink,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { REPORTS_STORE_NAME } from '../data';
import SyncStateSummary from './SyncStateSummary';
import SyncStateTable from './SyncStateTable';

const SyncState = () => {
	const feedState = useSelect( ( select ) =>
		select( REPORTS_STORE_NAME ).getFeedState()
	);

	return (
		<Card className="woocommerce-table pinterest-for-woocommerce-catalog-sync__state">
			<CardHeader>
				<Text variant="title.small" as="h2">
					{ __( 'Overview', 'pinterest-for-woocommerce' ) }
				</Text>
			</CardHeader>
			<SyncStateSummary overview={ feedState?.overview } />
			<SyncStateTable workflow={ feedState?.workflow } />
			<CardFooter justify="flex-start">
				<Icon icon={ trendingUpIcon } />
				<Text>
					{ createInterpolateElement(
						__(
							'Set up and manage ads to increase your reach with <adsManagerLink>Pinterest ads manager</adsManagerLink>',
							'pinterest-for-woocommerce'
						),
						{
							adsManagerLink: (
								<ExternalLink
									className="link"
									href={
										wcSettings.pinterest_for_woocommerce
											.pinterestLinks.adsManager
									}
								/>
							),
						}
					) }
				</Text>
			</CardFooter>
		</Card>
	);
};

export default SyncState;
