/**
 * External dependencies
 */
import { controls as dataControls } from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { WC_ADMIN_NAMESPACE, OPTIONS_NAME } from './constants';

export const fetch = () => {
	return {
		type: 'FETCH',
	};
};

export const controls = {
	...dataControls,
	FETCH() {
		return new Promise( ( resolve ) => {
			const url = WC_ADMIN_NAMESPACE + '/options?options=' + OPTIONS_NAME;
			apiFetch( { path: url } )
				.then( ( result ) => resolve( result[ OPTIONS_NAME ] ) );
		} );
	},
};
