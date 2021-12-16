/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { recordEvent } from '@woocommerce/tracks';

/**
 * Internal dependencies
 */
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';
import prepareForTracking from '../helpers/prepare-for-tracking';

/**
 * Clicking on "… Save changes" button.
 *
 * @event wcadmin_pfw_save_changes_button_click
 */

/**
 * Save Settings button component
 *
 * @fires wcadmin_pfw_save_changes_button_click
 * @return {JSX.Element} Rendered element
 */
const SaveSettingsButton = () => {
	const isSaving = useSettingsSelect( 'isSettingsUpdating' );
	const settings = useSettingsSelect( 'getSettings' );
	const setAppSettings = useSettingsDispatch( true );
	const createNotice = useCreateNotice();

	const saveSettings = async () => {
		recordEvent( 'pfw_save_changes_button_click', {
			...prepareForTracking( settings ),
			context: `pinterest_settings`,
		} );

		try {
			await setAppSettings( {} );

			createNotice(
				'success',
				__(
					'Your settings have been saved successfully.',
					'pinterest-for-woocommerce'
				)
			);
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
