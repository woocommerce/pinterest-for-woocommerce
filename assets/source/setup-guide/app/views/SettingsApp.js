/**
 * External dependencies
 */
import '@wordpress/notices';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import SetupAccount from '../steps/SetupAccount';
import ClaimWebsite from '../steps/ClaimWebsite';
import SetupTracking from '../steps/SetupTracking';
import SetupProductSync from '../steps/SetupProductSync';
import SetupPins from '../steps/SetupPins';
import SaveSettingsButton from '../components/SaveSettingsButton';
import TransientNotices from '../components/TransientNotices';
import {
	useSettingsSelect,
	useBodyClasses,
	useCreateNotice,
} from '../helpers/effects';

const SettingsApp = () => {
	const appSettings = useSettingsSelect();
	const isConnected = useSettingsSelect( 'isConnected' );
	const isDomainVerified = useSettingsSelect( 'isDomainVerified' );
	const isTrackingConfigured = useSettingsSelect( 'isTrackingConfigured' );

	const isGroup1Visible = isConnected;
	const isGroup2Visible = isGroup1Visible && isDomainVerified;
	const isGroup3Visible = isGroup2Visible && isTrackingConfigured;

	useBodyClasses();
	useCreateNotice()( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				{ appSettings ? (
					<div className="woocommerce-setup-guide__container">
						<SetupAccount view="settings" />

						{ isGroup1Visible && <ClaimWebsite view="settings" /> }
						{ isGroup2Visible && <SetupTracking view="settings" /> }
						{ isGroup3Visible && (
							<>
								<SetupProductSync view="settings" />
								<SetupPins view="settings" />
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
