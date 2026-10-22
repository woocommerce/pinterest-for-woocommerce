/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';

// One delegated listener tracks every marked link, so links need no `onClick` prop that could be overwritten.
document.addEventListener(
	'click',
	( event ) => {
		const link = event.target.closest?.( '[data-pfw-doc-event]' );
		if ( link ) {
			recordEvent( link.dataset.pfwDocEvent, {
				link_id: link.dataset.pfwDocLinkId,
				context: link.dataset.pfwDocContext,
				href: link.getAttribute( 'href' ),
			} );
		}
	},
	true
);

/**
 * Clicking on an external documentation link.
 *
 * @event wcadmin_pfw_documentation_link_click
 *
 * @property {string} link_id Identifier of the link.
 * @property {string} context `'settings' | 'welcome-section' | 'wizard'` In which context the link was placed?
 * @property {string} href Href to which the user was navigated to.
 */

/**
 * Clicking on the link inside the notice.
 *
 * @event wcadmin_pfw_get_started_notice_link_click
 *
 * @property {string} link_id Identifier of the link.
 * @property {string} context What action was initiated.
 * @property {string} href Href to which the user was navigated to.
 *
 *
 */

/**
 * Creates properties for an external documentation link.
 * May take any other props to be extended and forwarded to a link element (`<a>`, `<Button isLink>`).
 *
 * Sets `target="_blank" rel="noopener"` and data attributes that make a click fire the track event.
 *
 *
 * @fires wcadmin_pfw_documentation_link_click on click, with given `linkId` and `context`.
 * @param {Object} props React props.
 * @param {string} props.href Href to used by link and in track event.
 * @param {string} props.linkId Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} props.context Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} [props.target='_blank']
 * @param {string} [props.rel='noopener']
 * @param {string} [props.eventName='pfw_documentation_link_click'] The name of the event to be recorded
 * @param {...import('react').AnchorHTMLAttributes} props.props
 * @return {Object} Documentation link props.
 */
function documentationLinkProps( {
	href,
	linkId,
	context,
	target = '_blank',
	rel = 'noopener',
	eventName = 'pfw_documentation_link_click',
	...props
} ) {
	return {
		href,
		target,
		rel,
		...props,
		'data-pfw-doc-event': eventName,
		'data-pfw-doc-link-id': linkId,
		'data-pfw-doc-context': context,
	};
}
export default documentationLinkProps;
