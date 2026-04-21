/**
 * Decision payload returned by the REST endpoint.
 *
 * @param {Object} state Reducer state.
 * @return {Object} Decision object.
 */
export const getRatingNoticeDecision = ( state ) => state.decision;

/**
 * Whether the notice should currently be shown.
 *
 * @param {Object} state Reducer state.
 * @return {boolean} True if the notice should be shown.
 */
export const shouldShowRatingNotice = ( state ) =>
	Boolean( state.decision?.should_show );

/**
 * Whether the decision has been loaded from the server.
 *
 * @param {Object} state Reducer state.
 * @return {boolean} True when the initial fetch has completed.
 */
export const isRatingNoticeDecisionLoaded = ( state ) =>
	Boolean( state.decisionLoaded );
