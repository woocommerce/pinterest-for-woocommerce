/**
 * Get settings from state tree.
 *
 * @param {Object} state - Reducer state
 */
export const getSettings = ( state ) => {
	return state.settings;
};

/**
 * Get setting from state tree.
 *
 * @param {Object} state - Reducer state
 * @param {Array} name - Setting name
 */
export const getSetting = ( state, name ) => {
	return state.settings[ name ];
};

/**
 * Determine if an options request resulted in an error.
 *
 * @param {Object} state - Reducer state
 * @param {string} name - Setting name
 */
export const getSettingsRequestingError = ( state, name ) => {
	return state.requestingErrors[ name ] || false;
};

/**
 * Determine if options are being updated.
 *
 * @param {Object} state - Reducer state
 */
export const isSettingsUpdating = ( state ) => {
	return state.isUpdating || false;
};

/**
 * Determine if an options update resulted in an error.
 *
 * @param {Object} state - Reducer state
 */
export const getSettingsUpdatingError = ( state ) => {
	return state.updatingError || false;
};
