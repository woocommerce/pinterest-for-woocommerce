/**
 * Internal dependencies
 */
import TYPES from './action-types';

const reportsReducer = (
	state = {
		feedIssues: {},
		feedState: {},
		isRequesting: false,
		requestingErrors: {},
	},
	action
) => {
	// Only the latest issues request may update its rows, error or loading state.
	if (
		action.requestId &&
		! ( action.type === TYPES.SET_IS_REQUESTING && action.isRequesting ) &&
		action.requestId !== state.requestId
	) {
		return state;
	}

	switch ( action.type ) {
		case TYPES.RECEIVE_FEEDISSUES:
			state = {
				...state,
				feedIssues: action.feedIssues,
			};
			break;
		case TYPES.RECEIVE_FEEDSTATE:
			state = {
				...state,
				feedState: action.feedState,
			};
			break;
		case TYPES.RESET_FEED:
			state = {
				...state,
				feedIssues: {},
				requestId: null,
				isRequesting: false,
				feedState: {},
			};
			break;
		case TYPES.SET_IS_REQUESTING:
			state = {
				...state,
				isRequesting: action.isRequesting,
				...( action.isRequesting
					? { requestId: action.requestId }
					: {} ),
			};
			break;
		case TYPES.SET_REQUESTING_ERROR:
			state = {
				...state,
				requestingErrors: {
					...state.requestingErrors,
					[ action.name ]: action.error,
				},
			};
			break;
	}

	return state;
};

export default reportsReducer;
