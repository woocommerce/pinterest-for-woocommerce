/**
 * Internal dependencies
 */
import { receiveFeedIssues, setRequestingError } from './actions';
import { fetch } from './controls';

/**
 * Request all settings values.
 */
export function* getFeedIssues() {
	try {
		const result = yield fetch( 'feed_issues' );
		yield receiveFeedIssues( result );
	} catch ( error ) {
		yield setRequestingError( error, 'feed_issues' );
	}
}

/**
 * Request all settings values.
 */
export function* getFeedState() {
	try {
		const result = yield fetch( 'feed_state' );
		yield receiveFeedIssues( result );
	} catch ( error ) {
		yield setRequestingError( error, 'feed_state' );
	}
}
