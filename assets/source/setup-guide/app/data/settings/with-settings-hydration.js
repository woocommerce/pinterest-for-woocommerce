/**
 * External dependencies
 */
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';
import { useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { STORE_NAME } from './constants';

export const useSettingsHydration = ( data ) => {
	const dataRef = useRef( data );

	useSelect( ( select, registry ) => {
		if ( ! dataRef.current ) {
			return;
		}

		const { isResolving, hasFinishedResolution } = select( STORE_NAME );
		const {
			startResolution,
			finishResolution,
			receiveSettings,
		} = registry.dispatch( STORE_NAME );
		const names = Object.keys( dataRef.current );

		names.forEach( ( name ) => {
			if (
				! isResolving( 'getSetting', [ name ] ) &&
				! hasFinishedResolution( 'getSetting', [ name ] )
			) {
				startResolution( 'getSetting', [ name ] );
				receiveSettings( { [ name ]: dataRef.current[ name ] } );
				finishResolution( 'getSetting', [ name ] );
			}
		} );
	}, [] );
};

export const withSettingsHydration = ( data ) =>
	createHigherOrderComponent(
		( OriginalComponent ) => ( props ) => {
			useSettingsHydration( data );

			return <OriginalComponent { ...props } />;
		},
		'withSettingsHydration'
	);
