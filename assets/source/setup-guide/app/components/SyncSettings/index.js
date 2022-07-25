/**
 * External dependencies
 */
import { sprintf, __ } from '@wordpress/i18n';
import { Spinner } from '@woocommerce/components';
import {
	Button,
	Flex,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- _experimentalText unlikely to change/disappear and also used by WC Core
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useSettingsSelect,
	useSyncSettingsDispatch,
	useCreateNotice,
} from '../../helpers/effects';
import './style.scss';

/**
 * Sync settings button.
 */
const SyncSettings = () => {
	const isSyncing = useSettingsSelect( 'isSettingsSyncing' );
	const syncAppSettings = useSyncSettingsDispatch();
	const createNotice = useCreateNotice();

	const syncSettings = async () => {
		try {
			await syncAppSettings();

			createNotice(
				'success',
				__(
					'Settings successfully synced with Pinterest Ads Manager.',
					'pinterest-for-woocommerce'
				)
			);
		} catch ( error ) {
			createNotice(
				'error',
				__(
					'Failed to sync settings with Pinterest Ads Manager.',
					'pinterest-for-woocommerce'
				),
				{
					actions: [
						{
							label: 'Retry.',
							onClick: syncSettings,
						},
					],
				}
			);
		}
	};

	const syncInfo = sprintf(
		'%1$s: %2$s •',
		__( 'Settings last updated' ),
		'last updated'
	);

	const syncButton = isSyncing ? (
		<>
			{ __( 'Syncing settings', 'pinterest-for-woocommerce' ) }
			<Spinner />
		</>
	) : (
		<Button isLink target="_blank" onClick={ syncSettings }>
			{ __( 'Sync', 'pinterest-for-woocommerce' ) }
		</Button>
	);

	return (
		<Flex justify="end" className="pinterest-for-woocommerce-sync-settings">
			<Text className="pinterest-for-woocommerce-sync-settings__info">
				{ syncInfo }
				{ syncButton }
			</Text>
		</Flex>
	);
};

export default SyncSettings;
