/**
 * Get settings from state tree.
 *
 * @param {Object} state - Reducer state
 */
export const getSettings = ( state ) => {
	return state.settings;
};

/**
 * Get a setting from state tree.
 *
 * @param {Object} state - Reducer state
 * @param {Array} name - Setting name
 */
export const getSetting = ( state, name ) => {
	return state.settings[ name ];
};

/**
 * Determine if a settings request resulted in an error.
 *
 * @param {Object} state - Reducer state
 * @param {string} name - Setting name
 */
export const getSettingsRequestingError = ( state, name ) => {
	return state.requestingErrors[ name ] || false;
};

/**
 * Determine if settings are being updated.
 *
 * @param {Object} state - Reducer state
 */
export const isSettingsUpdating = ( state ) => {
	return state.isUpdating || false;
};

/**
 * Determine if a settings update resulted in an error.
 *
 * @param {Object} state - Reducer state
 */
export const getSettingsUpdatingError = ( state ) => {
	return state.updatingError || false;
};

/**
 * Determine if the current domain was verified.
 *
 * @param {Object} state - Reducer state
 */
export const isDomainVerified = ( state ) => {
	if ( undefined === state?.settings ) {
		return;
	}

	if ( undefined === state?.settings?.account_data?.verified_domains ) {
		return false;
	}

	return state?.settings?.account_data?.verified_domains.includes(
		wcSettings.pin4wc.domainToVerify
	);
};

/**
 * Determine if a tracking advertiser and a tracking tag were configured.
 *
 * @param {Object} state - Reducer state
 */
export const isTrackingConfigured = ( state ) => {
	if ( undefined === state?.settings ) {
		return;
	}

	return !! (
		state?.settings?.tracking_advertiser && state?.settings?.tracking_tag
	);
};
