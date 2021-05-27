/**
 * External dependencies
 */
import '@wordpress/notices';
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import SyncToggler from './sections/SyncToggler';
import SyncStatuses from './sections/SyncStatuses';
import SyncOverview from './sections/SyncOverview';
import SyncIssues from './sections/SyncIssues';
import TransientNotices from './components/TransientNotices';
import { useCreateNotice } from './helpers/effects';
import { SETTINGS_STORE_NAME } from './data';

const App = () => {
	const appSettings = useSelect( ( select ) =>
		select( SETTINGS_STORE_NAME ).getSettings()
	);

	const { patchSettings: setAppSettings } = useDispatch( SETTINGS_STORE_NAME );
	const { createNotice } = useDispatch( 'core/notices' );

	const childComponentProps = {
		appSettings,
		setAppSettings,
		createNotice
	}

	useCreateNotice( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				{ appSettings
					? (
						<div className="woocommerce-catalog_sync__container">
							<SyncToggler />
							<SyncStatuses />
							<SyncOverview />
							<SyncIssues />
						</div>
					) : <Spinner />
				}
			</div>
		</div>
	);
};

export default App;
