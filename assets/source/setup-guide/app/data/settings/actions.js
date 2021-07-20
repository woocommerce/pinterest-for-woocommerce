/**
 * External dependencies
 */
import { select } from '@wordpress/data';
import { apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { STORE_NAME, WC_ADMIN_NAMESPACE, OPTIONS_NAME } from './constants';

export function receiveSettings( settings ) {
	return {
		type: TYPES.RECEIVE_SETTINGS,
		settings,
	};
}

export function setRequestingError( error, name ) {
	return {
		type: TYPES.SET_REQUESTING_ERROR,
		error,
		name,
	};
}

export function setUpdatingError( error ) {
	return {
		type: TYPES.SET_UPDATING_ERROR,
		error,
	};
}

export function setIsUpdating( isUpdating ) {
	return {
		type: TYPES.SET_IS_UPDATING,
		isUpdating,
	};
}

export function* updateSettings(data, saveToDb = false) {
	const isEmptyData = Object.entries(data).length === 0;

	yield receiveSettings(data);

	if ( ! saveToDb ) {
		return { success: true };
	}

	yield setIsUpdating( true );
	const settings = yield select( STORE_NAME ).getSettings();

	try {
		const results = yield apiFetch( {
			path: WC_ADMIN_NAMESPACE + '/options',
			method: 'POST',
			data: {
				[ OPTIONS_NAME ]: settings,
			},
		} );

		yield setIsUpdating(false);
		return { success: results[OPTIONS_NAME], isEmptyData };
	} catch (error) {
		yield setUpdatingError(error);
		return { success: false, ...error, isEmptyData };
	}
}
