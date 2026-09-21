/**
 * External dependencies
 */
import { controls as dataControls } from '@wordpress/data-controls';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { API_ENDPOINT, OPTIONS_NAME } from './constants';

export const fetch = () => {
	return {
		type: 'FETCH',
	};
};

export const controls = {
	...dataControls,
	FETCH() {
		return apiFetch( { path: API_ENDPOINT } ).then(
			( result ) => result[ OPTIONS_NAME ]
		);
	},
};
