/**
 * External dependencies
 */
import { useSelect } from '@wordpress/data';
import {createInterpolateElement, useState} from '@wordpress/element';
import { getHistory, getQuery, onQueryChange } from '@woocommerce/navigation';
import {
	ExternalLink,
	Icon,
	Notice,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';
/**
 * Internal dependencies
 */
import { REPORTS_STORE_NAME } from '../data';
import SyncIssuesTable from './SyncIssuesTable';
import {__, sprintf} from "@wordpress/i18n";
import {useSettingsSelect} from "../../setup-guide/app/helpers/effects";

const SyncIssues = () => {
	const itemsLimit = 250
	const [ query, setQuery ] = useState( getQuery() );
	const feedIssues = useSelect( ( select ) =>
		select( REPORTS_STORE_NAME ).getFeedIssues( query )
	);
	const isRequesting = useSelect( ( select ) =>
		select( REPORTS_STORE_NAME ).isRequesting()
	);

	const appSettings = useSettingsSelect();
	const trackingAdvertiser = appSettings?.tracking_advertiser;

	const total = feedIssues?.total_rows ?? 0

	if ( ! feedIssues?.lines?.length ) {
		return null;
	}

	getHistory().listen( () => {
		setQuery( getQuery() );
	} );

	if ( ! query.paged ) {
		query.paged = 1;
	}

	if ( ! query.per_page ) {
		query.per_page = 25;
	}

	return (
		<>
			{
				itemsLimit === total && (
					<Text style={{ marginTop: "40px", textAlign: "right" }}>
						{ createInterpolateElement(
								sprintf(
									// translators: %1$s: Amount of money required to spend to claim ad credits with currency. %2$s: Amount of ad credits given with currency.
									__(
										'Only the first %1$s Errors and Warnings are displayed below. To see more, please, visit <feedDiagnostics>Pinterest Feed Diagnostics</feedDiagnostics> page.',
										'pinterest-for-woocommerce'
									),
									total
								),
								{
									feedDiagnostics: <ExternalLink
										href={ `https://pinterest.com/business/catalogs/diagnosticsv2/?advertiserId=${trackingAdvertiser}` }
									/>
								}
							)
						}
					</Text>
				)
			}
			<SyncIssuesTable
				issues={feedIssues?.lines}
				query={query}
				totalRows={total}
				isRequesting={isRequesting}
				onQueryChange={onQueryChange}
			/>
		</>
	);
};

export default SyncIssues;
