/**
 * External dependencies
 */
import '@wordpress/notices';
import { useSelect, useDispatch } from '@wordpress/data';
import { Spinner } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import SetupAccount from '../steps/SetupAccount';
import ClaimWebsite from '../steps/ClaimWebsite';
import SetupTracking from '../steps/SetupTracking';
import SetupPins from '../steps/SetupPins';
import SaveSettingsButton from '../components/SaveSettingsButton';
import TransientNotices from '../components/TransientNotices';
import { useSettingsSelect, useBodyClasses, useCreateNotice } from '../helpers/effects';
import {
	isConnected,
	isDomainVerified,
	isTrackingConfigured,
} from '../helpers/conditionals';

const SettingsApp = () => {
	const appSettings = useSettingsSelect();

	useBodyClasses();
	useCreateNotice()( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				{ appSettings ? (
					<div className="woocommerce-setup-guide__container">
						<SetupAccount view="settings" />

						{ isConnected( appSettings ) && (
							<>
								<ClaimWebsite view="settings" />

								{ /* isDomainVerified( appSettings ) &&  */(
									<>
										<SetupTracking view="settings" />

										{ isTrackingConfigured(
											appSettings
										) && (
											<>
												<SetupPins view="settings" />
												<SaveSettingsButton />
											</>
										) }
									</>
								) }
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
