/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { controls as dataControls } from '@wordpress/data-controls';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { API_ROUTE } from './constants';

export const fetch = ( endpoint, data = {} ) => {
	return {
		type: 'FETCH',
		endpoint,
		data,
	};
};

export const controls = {
	...dataControls,
	FETCH( { data = {}, method = 'GET' } ) {
		const url = addQueryArgs( `${ API_ROUTE }`, data );
		return apiFetch( { path: url, method } );
	},
};
