/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { useEffect } from '@wordpress/element';

let owners = 0;

const trackDocumentationClick = ( event ) => {
	const link = event
		.composedPath()
		.find( ( node ) => node.hasAttribute?.( 'data-pfw-doc-event' ) );
	if ( ! link ) {
		return;
	}

	const eventName = link.getAttribute( 'data-pfw-doc-event' );
	const payload = {
		link_id: link.dataset.pfwDocLinkId,
		context: link.dataset.pfwDocContext,
		href: link.getAttribute( 'href' ) ?? undefined,
	};
	// A task runs after all click handlers; a microtask can run between native listeners.
	setTimeout( () => {
		if ( ! event.defaultPrevented ) {
			recordEvent( eventName, payload );
		}
	}, 0 );
};

/**
 * Share one click listener while Pinterest admin pages are mounted.
 */
export const useDocumentationLinkTracking = () => {
	useEffect( () => {
		if ( ! owners++ ) {
			document.addEventListener( 'click', trackDocumentationClick, true );
		}
		return () => {
			if ( ! --owners ) {
				document.removeEventListener(
					'click',
					trackDocumentationClick,
					true
				);
			}
		};
	}, [] );
};

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
 * Marks links for delegated tracking without defining an onClick handler.
 * The containing admin page calls useDocumentationLinkTracking.
 *
 *
 * @fires wcadmin_pfw_documentation_link_click on click, with given `linkId` and `context`.
 * @param {Object} props React props.
 * @param {string} props.href Href to used by link and in track event.
 * @param {string} props.linkId Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} props.context Forwarded to {@link wcadmin_pfw_documentation_link_click}
 * @param {string} [props.target='_blank']
 * @param {string} [props.rel='noopener']
 * @param {Function} [props.onClick] onClick event handler forwarded unchanged.
 * @param {string} [props.eventName='pfw_documentation_link_click'] The name of the event to be recorded
 * @param {...import('react').AnchorHTMLAttributes} props.props
 * @return {Object} Documentation link props and tracking attributes.
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
