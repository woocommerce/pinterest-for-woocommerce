/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';

/**
 * Clicking on an external documentation link.
 *
 * @event wcadmin_pfw_documentation_link_click
 *
 * @property {string} link_id Identifier of the link.
 * @property {string} context What action was initiated.
 * @property {string} href Href to which the user was navigated to.
 * @property {string} eventName (Optional) The name of the event to be recorded, documentation_link_click by default
 */

/**
 * Creates properties for an external documentation link.
 * May take any other props to be extended and forwarded to a link element (`<a>`, `<Button isLink>`).
 *
 * Sets `target="_blank" rel="noopener"` and `onClick` handler that fires track event.
 *
 * Please be careful not to overwrite the `onClick` handler coincidently.
 *
 * Notice documentation_link_click is the default eventName and pfw_ is not required
 *
 * @fires wcadmin_pfw_documentation_link_click on click, with given `linkId` and `context`.
 * @param {Object} props React props.
 * @param {string} props.href Href to used by link and in track event.
 * @param {string} props.linkId Forwarded to {@see wcadmin_pfw_documentation_link_click}
 * @param {string} props.context Forwarded to {@see wcadmin_pfw_documentation_link_click}
 * @param {string} [props.target='_blank']
 * @param {string} [props.rel='noopener']
 * @param {Function} [props.onClick] onClick event handler to be decorated with firing Track event.
 * @param {string} props.eventName The name of the event to be recorded
 * @param {...import('react').AnchorHTMLAttributes} props.props
 * @return {{herf: string, target: string, rel: string, onClick: Function, props}} Documentation link props.
 */
function documentationLinkProps( {
	href,
	linkId,
	context,
	target = '_blank',
	rel = 'noopener',
	onClick,
	eventName = 'documentation_link_click',
	...props
} ) {
	return {
		href,
		target,
		rel,
		...props,
		onClick: ( event ) => {
			if ( onClick ) {
				onClick( event );
			}
			if ( ! event.defaultPrevented ) {
				recordEvent( `pfw_${ eventName }`, {
					link_id: linkId,
					context,
					href,
				} );
			}
		},
	};
}
export default documentationLinkProps;
