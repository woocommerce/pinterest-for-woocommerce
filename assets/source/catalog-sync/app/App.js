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

const App = () => {
	useCreateNotice( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				<div className="pin4wc-catalog-sync__container">
					<SyncState />
					<SyncIssues />
				</div>
			</div>
		</div>
	);
};

export default App;
