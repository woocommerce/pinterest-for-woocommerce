/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

import { useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

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
	const setAppSettings = useSettingsDispatch( true );
	const createNotice = useCreateNotice();
	const appSettings = useSettingsSelect();

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

	const connectAdvertiser = useCallback(
		async ( trackingAdvertiser, trackingTag ) => {
			try {
				const results = await apiFetch( {
					path: `${ wcSettings.pinterest_for_woocommerce.apiRoute }/tagowner/`,
					data: {
						advrtsr_id: trackingAdvertiser,
						tag_id: trackingTag,
					},
					method: 'POST',
				} );

				if ( trackingAdvertiser === results.connected ) {
					if ( results.reconnected ) {
						createNotice(
							'success',
							__(
								'Advertiser connected successfully.',
								'pinterest-for-woocommerce'
							)
						);
					}
				} else {
					createNotice(
						'error',
						__(
							'Couldn’t connect advertiser.',
							'pinterest-for-woocommerce'
						)
					);
				}
			} catch ( error ) {
				createNotice(
					'error',
					error.message ||
						__(
							'Couldn’t connect advertiser.',
							'pinterest-for-woocommerce'
						)
				);
			}
		},
		[ createNotice ]
	);

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
