/**
 * Disable the Save to Pinterest button if the Pinterest browser extension is detected.
 */
// eslint-disable-next-line @wordpress/no-global-event-listener
window.addEventListener( 'load', function () {
	const disableSaveButton = () => {
		document
			.querySelectorAll( '.pinterest-for-woocommerce-image-wrapper' )
			.forEach( function ( button ) {
				button.style.display = 'none';
			} );
	};

	const checkForPinterestExtension = () => {
		const pinterestElement = document.querySelector(
			'[data-pinterest-extension]'
		);
		if ( pinterestElement ) {
			disableSaveButton();
		}
	};

	checkForPinterestExtension();
} );

// eslint-disable-next-line @wordpress/no-global-event-listener
document.addEventListener( 'DOMContentLoaded', function () {
	function processWrappers() {
		document
			.querySelectorAll( '.pinterest-for-woocommerce-image-wrapper' )
			.forEach( function ( wrapper ) {
				// Skip if already processed.
				if ( wrapper.dataset.srLabeled ) return;

				const pinLink = wrapper.querySelector( 'a' );
				const srSpan = wrapper.querySelector( '.screen-reader-text' );

				// Check that both elements exist AND Pinterest has finished adding its attribute.
				const isPinterestReady =
					pinLink &&
					pinLink.getAttribute( 'data-pin-log' ) === 'button_pinit';

				if ( isPinterestReady && srSpan ) {
					// Move the span inside the processed <a> tag.
					pinLink.appendChild( srSpan );

					// Add aria-haspopup to <a> tag.
					pinLink.setAttribute( 'aria-haspopup', 'dialog' );

					// Mark as processed so it only runs once.
					wrapper.dataset.srLabeled = 'true';
				}
			} );
	}

	// Run initially in case Pinterest rendered on page load.
	processWrappers();

	// Observe mutations for dynamic elements or asynchronous Pinterest rendering.
	const observer = new window.MutationObserver( function ( mutations ) {
		let shouldProcess = false;

		for ( const mutation of mutations ) {
			// Check for added DOM nodes or attribute changes made by Pinterest.
			if (
				mutation.type === 'childList' &&
				mutation.addedNodes.length > 0
			) {
				shouldProcess = true;
				break;
			} else if (
				mutation.type === 'attributes' &&
				mutation.attributeName === 'data-pin-log'
			) {
				shouldProcess = true;
				break;
			}
		}

		if ( shouldProcess ) {
			// Defer execution briefly to ensure DOM paint completes.
			window.requestAnimationFrame( processWrappers );
		}
	} );

	// Observe child node additions and attribute changes across the document.
	observer.observe( document.body, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: [ 'data-pin-log' ],
	} );
} );
