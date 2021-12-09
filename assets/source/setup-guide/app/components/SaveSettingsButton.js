/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { recordEvent } from '@woocommerce/tracks';
import { getPath } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import {
	useSettingsSelect,
	useSettingsDispatch,
	useCreateNotice,
} from '../helpers/effects';

const SaveSettingsButton = () => {
	const isSaving = useSettingsSelect( 'isSettingsUpdating' );
	const updatedData = useSettingsSelect( 'getUpdatedData' );
	const setAppSettings = useSettingsDispatch( true );
	const createNotice = useCreateNotice();

	const saveSettings = async () => {
		const path = getPath();

		recordEvent( 'pfw_save_changes_button_click', {
			updatedData,
			context: path.substring( 1 ).replace( '/', '_' ),
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
