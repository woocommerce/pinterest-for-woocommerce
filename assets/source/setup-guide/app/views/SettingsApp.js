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
import { useSettingsSelect, useBodyClasses, useCreateNotice } from '../helpers/effects';

const SettingsApp = () => {
	const appSettings = useSettingsSelect();
	const isConnected = useSettingsSelect( 'isConnected' );
	const isDomainVerified = useSettingsSelect( 'isDomainVerified' );
	const isTrackingConfigured = useSettingsSelect( 'isTrackingConfigured' );

	useBodyClasses();
	useCreateNotice()( wcSettings.pin4wc.error );

	return (
		<div className="woocommerce-layout">
			<div className="woocommerce-layout__main">
				<TransientNotices />
				{ appSettings ? (
					<div className="woocommerce-setup-guide__container">
						<SetupAccount view="settings" />

						{ isConnected && (
							<>
								<ClaimWebsite view="settings" />

								{ isDomainVerified && (
									<>
										<SetupTracking view="settings" />

										{ isTrackingConfigured && (
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
