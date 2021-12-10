/**
 * External dependencies
 */
import { getPath } from '@woocommerce/navigation';

/**
 * Returns the formatted context based on the path.
 *
 * @return {string} The path in snake case format
 * @throws Exception if getPath is not working, returns an empty string as a result
 */
export default function getContextByPath() {
	try {
		const path = getPath();
		return path.substring( 1 ).replace( /\//g, '_' );
	} catch ( e ) {
		return '';
	}
}
