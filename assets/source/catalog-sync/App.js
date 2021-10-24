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
import HealthCheck from '../setup-guide/app/components/HealthCheck';
import { useCreateNotice } from './helpers/effects';
import NavigationClassic from '../components/navigation-classic';

const CatalogSyncApp = () => {
	useCreateNotice( wcSettings.pinterest_for_woocommerce.error );

	return (
		<div className="pinterest-for-woocommerce-catalog-sync">
			<HealthCheck />
			<NavigationClassic />

			<TransientNotices />
			<div className="pinterest-for-woocommerce-catalog-sync__container">
				<SyncState />
				<SyncIssues />
			</div>
		</div>
	);
};

export default CatalogSyncApp;
