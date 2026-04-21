/**
 * External dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import TYPES from './action-types';
import { API_ROUTE } from './constants';

export function receiveDecision( decision ) {
	return {
		type: TYPES.RECEIVE_DECISION,
		decision,
	};
}

export function setIsRequesting( isRequesting ) {
	return {
		type: TYPES.SET_IS_REQUESTING,
		isRequesting,
	};
}

export function setRequestingError( error ) {
	return {
		type: TYPES.SET_REQUESTING_ERROR,
		error,
	};
}

export function dismissLocal() {
	return { type: TYPES.DISMISS_LOCAL };
}

function* postAction( action ) {
	yield dismissLocal();

	try {
		const decision = yield apiFetch( {
			path: API_ROUTE,
			method: 'POST',
			data: { action },
		} );
		yield receiveDecision( decision );
		return { success: true };
	} catch ( error ) {
		yield setRequestingError( error );
		return { success: false };
	}
}

export function* snoozeRatingNotice() {
	return yield* postAction( 'snooze' );
}

export function* clickedRatingNotice() {
	return yield* postAction( 'clicked' );
}

export function* dismissRatingNoticeForever() {
	return yield* postAction( 'dismiss_forever' );
}
