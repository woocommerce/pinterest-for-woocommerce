/**
 * Internal dependencies
 */
import TYPES from './action-types';

const reportsReducer = (
	state = { requestingErrors: {} },
	{ type, feedIssues, feedState, error, name, isRequesting }
) => {
	switch ( type ) {
		case TYPES.RECEIVE_FEEDISSUES:
			state = {
				...state,
				feedIssues
			};
			break;
		case TYPES.RECEIVE_FEEDSTATE:
			state = {
				...state,
				feedState
			};
			break;
		case TYPES.SET_IS_REQUESTING:
			state = {
				...state,
				isRequesting
			};
			break;
		case TYPES.SET_REQUESTING_ERROR:
			state = {
				...state,
				requestingErrors: {
					[ name ]: error,
				},
			};
			break;
	}

	return state;
};

export default reportsReducer;
