/**
 * Internal dependencies
 */
import {
	receiveFeedIssues,
	receiveFeedState,
	setRequestingError,
	setIsRequesting,
} from './actions';
import { fetch } from './controls';

/**
 * Request current feed issues.
 *
 * @param {Object} query
 */
export function* getFeedIssues( query = {} ) {
	const requestId = {};
	try {
		yield setIsRequesting( true, requestId );
		yield setRequestingError( false, 'feed_issues', requestId );
		const data = {
			paged: query.paged || 1,
			per_page: query.per_page || 25,
		};

		const result = yield fetch( 'feed_issues', data );
		yield receiveFeedIssues( result, requestId );
	} catch ( error ) {
		yield setRequestingError( error, 'feed_issues', requestId );
	} finally {
		yield setIsRequesting( false, requestId );
	}
}

/**
 * Request current feed state.
 */
export function* getFeedState() {
	try {
		yield setRequestingError( false, 'feed_state' );
		const result = yield fetch( 'feed_state' );
		yield receiveFeedState( result );
	} catch ( error ) {
		yield setRequestingError( error, 'feed_state' );
	}
}
