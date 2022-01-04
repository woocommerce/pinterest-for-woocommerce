/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
	useConnectAdvertiser,
} from '../helpers/effects';

const SaveSettingsButton = () => {
	const isSaving = useSettingsSelect( 'isSettingsUpdating' );
	const setAppSettings = useSettingsDispatch( true );
	const createNotice = useCreateNotice();
	const appSettings = useSettingsSelect();
	const connectAdvertiser = useConnectAdvertiser();

	const saveSettings = async () => {
		try {
			await setAppSettings( {} );

			createNotice(
				'success',
				__(
					'Your settings have been saved successfully.',
					'pinterest-for-woocommerce'
				)
			);

			if (
				appSettings?.tracking_advertiser &&
				appSettings?.tracking_tag
			) {
				await connectAdvertiser(
					appSettings.tracking_advertiser,
					appSettings.tracking_tag
				);
			}
		} catch ( error ) {
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}
	};

	return (
		<div className="woocommerce-setup-guide__footer-button">
			<Button isPrimary onClick={ saveSettings } disabled={ isSaving }>
				{ isSaving
					? __( 'Saving settings…', 'pinterest-for-woocommerce' )
					: __( 'Save changes', 'pinterest-for-woocommerce' ) }
			</Button>
		</div>
	);
};

export default SaveSettingsButton;
