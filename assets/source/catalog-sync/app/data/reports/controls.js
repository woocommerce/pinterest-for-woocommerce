/**
 * External dependencies
 */
import { controls as dataControls } from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { API_ROUTE } from './constants';

export const fetch = ( endpoint ) => {
	return {
		type: 'FETCH',
		endpoint
	};
};

export const controls = {
	...dataControls,
	FETCH({ endpoint }) {
		return new Promise( ( resolve ) => {
			const url = API_ROUTE + '/' + endpoint;
			apiFetch( { path: url } ).then( ( result ) =>
				resolve( result )
			);
		} );
	},
};
