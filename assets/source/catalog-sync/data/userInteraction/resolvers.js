/**
 * Internal dependencies
 */
import {
	receiveUserInteractions,
	setRequestingError,
	setIsRequesting,
} from './actions';
import { fetch } from './controls';

/**
 * Request current feed state.
 */
export function* getUserInteractions() {
	try {
		yield setRequestingError( false, 'user_interaction' );
		yield setIsRequesting( true );
		const result = yield fetch( 'user_interaction' );
		yield receiveUserInteractions( result );
	} catch ( error ) {
		yield setRequestingError( error, 'user_interaction' );
	} finally {
		yield setIsRequesting( false );
	}
}
