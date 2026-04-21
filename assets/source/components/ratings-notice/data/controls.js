/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { controls as dataControls } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import { API_ROUTE } from './constants';

export const fetchDecision = () => ( { type: 'FETCH_DECISION' } );

export const controls = {
	...dataControls,
	FETCH_DECISION() {
		return apiFetch( { path: API_ROUTE, method: 'GET' } );
	},
};
