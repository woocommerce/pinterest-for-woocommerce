/**
 * Internal dependencies
 */
import TYPES from './action-types';

const defaultState = {
	decision: {
		should_show: false,
		channel: 'wporg',
		ask_count: 0,
		reason: '',
	},
	isRequesting: false,
	decisionLoaded: false,
	requestingError: null,
};

const ratingsNoticeReducer = ( state = defaultState, action ) => {
	switch ( action.type ) {
		case TYPES.RECEIVE_DECISION:
			return {
				...state,
				decision: { ...state.decision, ...action.decision },
				decisionLoaded: true,
			};
		case TYPES.SET_IS_REQUESTING:
			return {
				...state,
				isRequesting: action.isRequesting,
			};
		case TYPES.SET_REQUESTING_ERROR:
			return {
				...state,
				requestingError: action.error,
			};
		case TYPES.DISMISS_LOCAL:
			return {
				...state,
				decision: { ...state.decision, should_show: false },
			};
		default:
			return state;
	}
};

export default ratingsNoticeReducer;
