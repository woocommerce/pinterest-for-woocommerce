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
import SetupProductSync from '../steps/SetupProductSync';
import SetupPins from '../steps/SetupPins';
import SaveSettingsButton from '../components/SaveSettingsButton';
import TransientNotices from '../components/TransientNotices';
import { useBodyClasses, useCreateNotice } from '../helpers/effects';
import {
	isConnected,
	isDomainVerified,
	isTrackingConfigured,
} from '../helpers/conditionals';
import { SETTINGS_STORE_NAME } from '../data';

const SettingsApp = () => {
	const appSettings = useSelect( ( select ) =>
		select( SETTINGS_STORE_NAME ).getSettings()
	);

	const { updateSettings: setAppSettings } = useDispatch(
		SETTINGS_STORE_NAME
	);
	const { createNotice } = useDispatch( 'core/notices' );

	const childComponentProps = {
		appSettings,
		setAppSettings,
		createNotice,
	};

	useBodyClasses();
	useCreateNotice( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				{ appSettings ? (
					<div className="woocommerce-setup-guide__container">
						<SetupAccount
							view="settings"
							{ ...childComponentProps }
						/>

						{ isConnected( appSettings ) && (
							<>
								<ClaimWebsite
									view="settings"
									{ ...childComponentProps }
								/>

								{ isDomainVerified( appSettings ) && (
									<>
										<SetupTracking
											view="settings"
											{ ...childComponentProps }
										/>

										{ isTrackingConfigured(
											appSettings
										) && (
											<>
												<SetupProductSync
													{ ...childComponentProps }
												/>
												<SetupPins
													view="settings"
													{ ...childComponentProps }
												/>
												<SaveSettingsButton
													{ ...childComponentProps }
												/>
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
