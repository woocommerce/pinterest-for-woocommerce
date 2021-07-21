/**
 * External dependencies
 */
import '@wordpress/notices';
import { Spinner } from '@woocommerce/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SetupProductSync from '../steps/SetupProductSync';
import SetupPins from '../steps/SetupPins';
import AdvancedSettings from '../steps/AdvancedSettings';
import SaveSettingsButton from '../components/SaveSettingsButton';
import TransientNotices from '../components/TransientNotices';
import {
	useSettingsSelect,
	useBodyClasses,
	useCreateNotice,
} from '../helpers/effects';

import NavigationClassic from '../../../components/navigation-classic';

const SettingsApp = () => {
	const appSettings = useSettingsSelect();
	const isDomainVerified = useSettingsSelect( 'isDomainVerified' );
	const isTrackingConfigured = useSettingsSelect( 'isTrackingConfigured' );

	const [ isConnected, setIsConnected ] = useState(
		wcSettings.pin4wc.isConnected
	);

	const isGroup1Visible = isConnected;
	const isGroup2Visible = isGroup1Visible && isDomainVerified;
	const isGroup3Visible = isGroup2Visible && isTrackingConfigured;

	useBodyClasses();
	useCreateNotice()( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<NavigationClassic />

				<TransientNotices />
				{ appSettings ? (
					<div className="woocommerce-setup-guide__container">
						{ isGroup3Visible && (
							<>
								<SetupProductSync view="settings" />
								<SetupPins view="settings" />
								<AdvancedSettings view="settings" />
								<SaveSettingsButton />
							</>
						) }
					</div>
				) : (
					<Spinner />
				) }
			</div>
		</div>
	);
};

export default SettingsApp;
