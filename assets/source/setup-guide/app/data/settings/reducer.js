/**
 * Internal dependencies
 */
import TYPES from './action-types';

const settingsReducer = (
	state = { isUpdating: false, requestingErrors: {} },
	{ type, settings, error, isUpdating, name }
) => {
	switch ( type ) {
		case TYPES.RECEIVE_SETTINGS:
			state = {
				...state,
				...settings,
			};
			break;
		case TYPES.SET_IS_UPDATING:
			state = {
				...state,
				isUpdating,
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
		case TYPES.SET_UPDATING_ERROR:
			state = {
				...state,
				error,
				updatingError: error,
				isUpdating: false,
			};
			break;
	}
	return state;
};

export default settingsReducer;
