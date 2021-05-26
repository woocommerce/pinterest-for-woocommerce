/**
 * Internal dependencies
 */
import { receiveSettings, setRequestingError } from './actions';
import { batchFetch } from './controls';

/**
 * Request an option value.
 *
 * @param {string} name - Setting name
 */
export function* getSetting( name ) {
	try {
		const result = yield batchFetch( name );
		yield receiveSettings( result );
	} catch ( error ) {
		yield setRequestingError( error, name );
	}
}
