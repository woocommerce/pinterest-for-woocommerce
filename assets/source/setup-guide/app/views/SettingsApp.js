/**
 * External dependencies
 */
import '@wordpress/notices';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import SetupProductSync from '../steps/SetupProductSync';
import SetupPins from '../steps/SetupPins';
import AdvancedSettings from '../steps/AdvancedSettings';
import SaveSettingsButton from '../components/SaveSettingsButton';
import TransientNotices from '../components/TransientNotices';
import HealthCheck from '../components/HealthCheck';
import {
	useSettingsSelect,
	useBodyClasses,
	useCreateNotice,
} from '../helpers/effects';

import NavigationClassic from '../../../components/navigation-classic';

const SettingsApp = () => {
	const VIEW_NAME = 'settings';
	const appSettings = useSettingsSelect();

	useBodyClasses();
	useCreateNotice()( wcSettings.pinterest_for_woocommerce.error );

	return (
		<>
			<HealthCheck />
			<NavigationClassic />

			<TransientNotices />
			{ appSettings ? (
				<div className="woocommerce-setup-guide__container">
					<>
						<SetupProductSync view={ VIEW_NAME } />
						<SetupPins view={ VIEW_NAME } />
						<AdvancedSettings view={ VIEW_NAME } />
						<SaveSettingsButton view={ VIEW_NAME } />
					</>
				</div>
			) : (
				<Spinner />
			) }
		</>
	);
};

export default SettingsApp;
