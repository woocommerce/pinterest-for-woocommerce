/**
 * External dependencies
 */
import '@wordpress/notices';

/**
 * Internal dependencies
 */
import SyncState from './sections/SyncState';
import SyncIssues from './sections/SyncIssues';
import TransientNotices from './components/TransientNotices';
import { useCreateNotice } from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';

const CatalogSyncApp = () => {
	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );

	return (
		<div className="pin4wc-catalog-sync">
			<NavigationClassic />

			<TransientNotices />
			<div className="pin4wc-catalog-sync__container">
				<SyncState />
				<SyncIssues />
			</div>
		</div>
	);
};

export default CatalogSyncApp;
