/**
 * Internal dependencies
 */
import TYPES from './action-types';

const settingsReducer = (
	state = {
		settings: {},
		isUpdating: false,
		requestingErrors: {},
		updatedData: {},
	},
	action
) => {
	switch ( action.type ) {
		case TYPES.RECEIVE_SETTINGS:
			state = {
				...state,
				settings: {
					...state.settings,
					...action.settings,
				},
			};
			break;
		case TYPES.SET_IS_UPDATING:
			state = {
				...state,
				isUpdating: action.isUpdating,
			};
			break;
		case TYPES.SET_REQUESTING_ERROR:
			state = {
				...state,
				requestingErrors: {
					[ action.name ]: action.error,
				},
			};
			break;
		case TYPES.SET_UPDATING_ERROR:
			state = {
				...state,
				updatingError: action.error,
				isUpdating: false,
			};
			break;
		case TYPES.SET_UPDATED_DATA:
			state = {
				...state,
				updatedData: action.reset
					? {}
					: { ...state.updatedData, ...action.data },
			};
			break;
	}
	return state;
};

export default settingsReducer;
