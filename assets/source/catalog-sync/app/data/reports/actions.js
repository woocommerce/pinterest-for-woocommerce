/**
 * Internal dependencies
 */
import TYPES from './action-types';

export function receiveFeedIssues( feedIssues ) {
	return {
		type: TYPES.RECEIVE_FEEDISSUES,
		feedIssues,
	};
}

export function receiveFeedState( feedState ) {
	return {
		type: TYPES.RECEIVE_FEEDSTATE,
		feedState,
	};
}

export function setRequestingError( error, name ) {
	return {
		type: TYPES.SET_REQUESTING_ERROR,
		error,
		name,
	};
}
