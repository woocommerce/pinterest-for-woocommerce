/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

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
	}, [] );
};

export const useCreateNotice = ( error ) => {
	const { createNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		if ( error ) {
			createNotice( 'error', error );
		}
	}, [ error ] );
};
