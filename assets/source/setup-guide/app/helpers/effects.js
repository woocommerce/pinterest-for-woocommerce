/**
 * External dependencies
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SETTINGS_STORE_NAME } from '../data';

export const useSettingsSelect = ( selector = 'getSettings' ) => {
	return useSelect( ( select ) =>
		select( SETTINGS_STORE_NAME )[ selector ]()
	);
};

export const useSettingsDispatch = ( saveToDb = false ) => {
	const { updateSettings } = useDispatch( SETTINGS_STORE_NAME );

	return ( data ) => updateSettings( data, saveToDb );
};

export const useCreateNotice = () => {
	const { createNotice } = useDispatch( 'core/notices' );

	return ( type, message ) => message && createNotice( type, message );
};

export const useBodyClasses = ( style ) => {
	useEffect( () => {
		document.body.classList.add( 'woocommerce-setup-guide__body' );

		if ( style === 'wizard' ) {
			document.body.parentNode.classList.remove( 'wp-toolbar' );
			document.body.classList.remove( 'woocommerce-admin-is-loading' );
			document.body.classList.remove( 'woocommerce-embed-page' );
			document.body.classList.add( 'woocommerce-onboarding' );
			document.body.classList.add( 'woocommerce-admin-full-screen' );
		}

		return () => {
			document.body.classList.remove( 'woocommerce-setup-guide__body' );

			if ( style === 'wizard' ) {
				document.body.classList.remove( 'woocommerce-onboarding' );
				document.body.classList.add( 'woocommerce-embed-page' );
				document.body.classList.remove(
					'woocommerce-admin-full-screen'
				);
				document.body.parentNode.classList.add( 'wp-toolbar' );
			}
		};
	}, [ style ] );
};
