/**
 * External dependencies
 */
import '@wordpress/notices';
import { Spinner } from '@woocommerce/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import SetupAccount from '../steps/SetupAccount';
import ClaimWebsite from '../steps/ClaimWebsite';
import SetupTracking from '../steps/SetupTracking';
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

	const [ isConnected, setIsConnected ] = useState(
		wcSettings.pin4wc.isConnected
	);

	const isGroup1Visible = isConnected;
	const isGroup2Visible = isGroup1Visible && isDomainVerified;

	useBodyClasses();
	useCreateNotice()( wcSettings.pin4wc.error );

	return (
		<>
			<NavigationClassic />

			<TransientNotices />
			{ appSettings ? (
				<div className="woocommerce-setup-guide__container">
					<SetupAccount
						view="settings"
						setIsConnected={ setIsConnected }
						isConnected={ isConnected }
					/>

					{ isGroup1Visible && <ClaimWebsite view="settings" /> }
					{ isGroup2Visible && <SetupTracking view="settings" /> }
					{ isGroup2Visible && <SaveSettingsButton /> }
				</div>
			) : (
				<Spinner />
			) }
		</>
	);
};

export default SettingsApp;
