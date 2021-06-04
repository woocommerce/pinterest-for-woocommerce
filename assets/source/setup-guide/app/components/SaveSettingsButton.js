/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { SETTINGS_STORE_NAME } from '../data';

const SaveSettingsButton = ({ setAppSettings, createNotice }) => {
	const isSaving = useSelect( ( select ) =>
		select( SETTINGS_STORE_NAME ).isSettingsUpdating()
	);

	const saveSettings = async () => {
		const update = await setAppSettings( {}, true )

		if ( update.success ) {
			createNotice(
				'error',
				__(
					'Your settings have been saved successfully.',
					'pinterest-for-woocommerce'
				)
			);
		} else {
			createNotice(
				'error',
				__(
					'There was a problem saving your settings.',
					'pinterest-for-woocommerce'
				)
			);
		}
	}

	return (
		<div className="woocommerce-setup-guide__footer-button">
			<Button
				isPrimary
				onClick={ saveSettings }
				disabled={ isSaving }
			>
				{ isSaving
					? __(
							'Saving settings…',
							'pinterest-for-woocommerce'
					  )
					: __(
							'Save changes',
							'pinterest-for-woocommerce'
					  ) }
			</Button>
		</div>
	)
}

export default SaveSettingsButton;
