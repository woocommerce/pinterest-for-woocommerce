/**
 * Internal dependencies
 */
import TYPES from './action-types';

export function receiveFeedIssues( feedIssues, requestId ) {
	return {
		type: TYPES.RECEIVE_FEEDISSUES,
		feedIssues,
		requestId,
	};
}

export function receiveFeedState( feedState ) {
	return {
		type: TYPES.RECEIVE_FEEDSTATE,
		feedState,
	};
}

export function setIsRequesting( isRequesting, requestId ) {
	return {
		type: TYPES.SET_IS_REQUESTING,
		isRequesting,
		requestId,
	};
}

export function setRequestingError( error, name, requestId ) {
	return {
		type: TYPES.SET_REQUESTING_ERROR,
		error,
		name,
		requestId,
	};
}

export function resetFeed() {
	return {
		type: TYPES.RESET_FEED,
	};
}
