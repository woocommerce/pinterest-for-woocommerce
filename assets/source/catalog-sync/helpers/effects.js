/**
 * External dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { USER_INTERACTION_STORE_NAME } from '../data';

export const useCreateNotice = ( error ) => {
	const { createNotice } = useDispatch( 'core/notices' );

	useEffect( () => {
		if ( error ) {
			createNotice( 'error', error );
		}
	}, [ error, createNotice ] );
};

export const useDismissAdsModalDispatch = () => {
	const { adsModalDismissed } = useDispatch( USER_INTERACTION_STORE_NAME );
	return () => adsModalDismissed();
};
