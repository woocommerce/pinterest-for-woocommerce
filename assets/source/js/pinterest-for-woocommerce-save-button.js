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

					// Mark as processed so it only runs once.
					wrapper.dataset.srLabeled = 'true';
				}
			} );
	}

	// Pinterest renders its button asynchronously, so keep retrying for a short
	// window instead of giving up after the first pass.
	const POLL_INTERVAL = 2000; // 2 seconds.
	const POLL_TIMEOUT = 10000; // 10 seconds.

	const deadline = Date.now() + POLL_TIMEOUT;

	function hasPendingWrappers() {
		return (
			document.querySelector(
				'.pinterest-for-woocommerce-image-wrapper:not([data-sr-labeled])'
			) !== null
		);
	}

	( function poll() {
		processWrappers();

		if ( ! hasPendingWrappers() || Date.now() >= deadline ) {
			return;
		}

		window.setTimeout( poll, POLL_INTERVAL );
	} )();
} );
