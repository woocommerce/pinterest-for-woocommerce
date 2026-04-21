/**
 * Internal dependencies
 */
import {
	receiveDecision,
	setIsRequesting,
	setRequestingError,
} from './actions';
import { fetchDecision } from './controls';

/**
 * Load the initial decision from the REST endpoint.
 */
export function* getRatingNoticeDecision() {
	try {
		yield setIsRequesting( true );
		const decision = yield fetchDecision();
		yield receiveDecision( decision );
	} catch ( error ) {
		yield setRequestingError( error );
	} finally {
		yield setIsRequesting( false );
	}
}

export const shouldShowRatingNotice = getRatingNoticeDecision;
export const isRatingNoticeDecisionLoaded = getRatingNoticeDecision;
