/**
 * External dependencies
 */
import '@wordpress/notices';
import { Spinner } from '@woocommerce/components';
import { useSelect } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import SyncSettings from '../components/SyncSettings';
import SetupProductSync from '../steps/SetupProductSync';
import SetupPins from '../steps/SetupPins';
import AdvancedSettings from '../steps/AdvancedSettings';
import SaveSettingsButton from '../components/SaveSettingsButton';
import HealthCheck from '../components/HealthCheck';
import {
	useSettingsSelect,
	useBodyClasses,
	useCreateNotice,
	useResetSettings,
} from '../helpers/effects';
import { SETTINGS_VIEW } from '../helpers/views';
import NavigationClassic from '../../../components/navigation-classic';
import { SETTINGS_STORE_NAME } from '../data';

const SettingsApp = () => {
	const appSettings = useSettingsSelect();
	const resetSettings = useResetSettings();
	const [ hasLoadedSettings, setHasLoadedSettings ] = useState( false );
	const { hasResolvedSettings, settingsError } = useSelect( ( select ) => {
		const settingsStore = select( SETTINGS_STORE_NAME );
		return {
			hasResolvedSettings: settingsStore.hasFinishedResolution(
				'getSettings',
				[]
			),
			settingsError: settingsStore.getSettingsRequestingError( 'all' ),
		};
	}, [] );
	// A save refetches settings; keep the loaded form mounted during that refresh.
	useEffect( () => {
		if ( hasResolvedSettings && ! settingsError ) {
			setHasLoadedSettings( true );
		}
	}, [ hasResolvedSettings, settingsError ] );

	useBodyClasses();
	useCreateNotice()( wcSettings.pinterest_for_woocommerce.error );

	return (
		<div className="pinterest-for-woocommerce-settings">
			<HealthCheck />
			<NavigationClassic />
			{ ! hasLoadedSettings && settingsError && (
				<Notice
					status="error"
					isDismissible={ false }
					actions={ [
						{
							label: __( 'Retry', 'pinterest-for-woocommerce' ),
							onClick: resetSettings,
						},
					] }
				>
					{ __(
						'Could not load your Pinterest settings. Please try again.',
						'pinterest-for-woocommerce'
					) }
				</Notice>
			) }

			{ hasLoadedSettings && appSettings ? (
				<div className="woocommerce-setup-guide__container">
					<>
						<SyncSettings view={ SETTINGS_VIEW } />
						<SetupProductSync view={ SETTINGS_VIEW } />
						<SetupPins view={ SETTINGS_VIEW } />
						<AdvancedSettings view={ SETTINGS_VIEW } />
						<SaveSettingsButton />
					</>
				</div>
			) : (
				! settingsError && <Spinner />
			) }
		</div>
	);
};

export default SettingsApp;
