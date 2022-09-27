/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
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
import AdCreditsNotice from './AdCreditsNotice';

/**
 * Clicking on the "Pinterest ads manager" link.
 *
 * @event wcadmin_pfw_ads_manager_link_click
 */

/**
 * Catalog sync state overview component.
 *
 * @fires wcadmin_pfw_ads_manager_link_click
 * @return {JSX.Element} Rendered component.
 */
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
									href={
										wcSettings.pinterest_for_woocommerce
											.pinterestLinks.adsManager
									}
									onClick={ () => {
										recordEvent(
											'pfw_ads_manager_link_click'
										);
									} }
								/>
							),
						}
					) }
				</Text>
			</CardFooter>
			<AdCreditsNotice />
		</Card>
	);
};

export default SyncState;
