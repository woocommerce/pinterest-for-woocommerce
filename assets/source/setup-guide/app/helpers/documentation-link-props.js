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
 */

/**
 * Creates properties for an external documentation link.
 * May take any other props to be extended and forwarded to a link element (`<a>`, `<Button isLink>`).
 *
 * Sets `target="_blank" rel="noreferrer"` and onClick handler that fires track event.
 *
 * @fires pfw_documentation_link_click on click, with given `linkId` and `context`.
 *
 * @param {Object} props React props.
 * @param {string} props.href Href to used by link and in track event.
 * @param {string} props.linkId Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} props.context Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} [props.target='_blank']
 * @param {string} [props.rel='noreferrer']
 * @param {Function} props.onClick
 * @param {...import('react').AnchorHTMLAttributes} props.props
 */
export default function documentationLinkProps( {
	href,
	linkId,
	context,
	target = '_blank',
	rel = 'noreferrer',
	onClick,
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
				recordEvent( 'pfw_documentation_link_click', {
					link_id: linkId,
					context,
					href,
				} );
			}
		},
	};
}
