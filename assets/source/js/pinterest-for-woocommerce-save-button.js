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
document.addEventListener( 'keydown', ( event ) => {
	// Check if target is a Pinterest span and Enter was pressed.
	const isPinSpan = event.target.matches( 'span[data-pin-log]' );

	if ( isPinSpan && event.key === 'Enter' ) {
		event.preventDefault();

		// Programmatically trigger Pinterest's click event.
		event.target.click();
	}
} );

// eslint-disable-next-line @wordpress/no-global-event-listener
document.addEventListener( 'DOMContentLoaded', function () {
	function processWrappers() {
		document
			.querySelectorAll( '.pinterest-for-woocommerce-image-wrapper' )
			.forEach( function ( wrapper ) {
				// Skip if already processed.
				if ( wrapper.dataset.srLabeled ) return;

				const pinLink =
					wrapper.querySelector( 'a' ) ||
					wrapper.querySelector( 'span[data-pin-log]' );
				const srSpan = wrapper.querySelector( '.screen-reader-text' );

				// Check that both elements exist AND Pinterest has finished adding its attribute.
				const isPinterestReady =
					pinLink &&
					( pinLink.getAttribute( 'data-pin-log' ) ===
						'button_pinit' ||
						pinLink.getAttribute( 'data-pin-log' ) ===
							'button_pinit_bookmarklet' );

				if ( isPinterestReady && srSpan ) {
					// Move the span inside the processed <a> tag.
					pinLink.appendChild( srSpan );

					if ( pinLink.tagName.toLowerCase() === 'span' ) {
						pinLink.setAttribute( 'aria-haspopup', 'dialog' );
						pinLink.setAttribute( 'role', 'link' );
						pinLink.setAttribute( 'tabindex', '0' );
					}

					// Mark as processed so it only runs once.
					wrapper.dataset.srLabeled = 'true';
				}
			} );
	}

	// Run initially in case Pinterest rendered on page load.
	processWrappers();

	/**
	 * Whether a mutation is related to our Save button wrappers/links.
	 *
	 * Pinterest often rewrites the <a> inside an existing wrapper (childList on
	 * the wrapper) or inserts a ready-made link with data-pin-log already set
	 * (no attribute mutation). Matching only newly added wrappers misses that.
	 *
	 * @param {MutationRecord} mutation Mutation to inspect.
	 * @return {boolean} True when processWrappers should run.
	 */
	function isPinterestRelatedMutation( mutation ) {
		if (
			mutation.type === 'attributes' &&
			mutation.attributeName === 'data-pin-log' &&
			mutation.target &&
			typeof mutation.target.closest === 'function' &&
			mutation.target.closest(
				'.pinterest-for-woocommerce-image-wrapper'
			)
		) {
			return true;
		}

		if (
			mutation.type !== 'childList' ||
			mutation.addedNodes.length === 0
		) {
			return false;
		}

		// Pinterest rewrote nodes inside an existing wrapper.
		if (
			mutation.target &&
			mutation.target.nodeType === 1 &&
			typeof mutation.target.closest === 'function' &&
			mutation.target.closest(
				'.pinterest-for-woocommerce-image-wrapper'
			)
		) {
			return true;
		}

		for ( const node of mutation.addedNodes ) {
			if ( node.nodeType !== 1 ) {
				continue;
			}

			if (
				node.matches( '.pinterest-for-woocommerce-image-wrapper' ) ||
				node.querySelector(
					'.pinterest-for-woocommerce-image-wrapper'
				) ||
				node.matches( 'a[data-pin-do], a[data-pin-log]' ) ||
				node.querySelector( 'a[data-pin-do], a[data-pin-log]' )
			) {
				return true;
			}
		}

		return false;
	}

	let processScheduled = false;

	function scheduleProcessWrappers() {
		if ( processScheduled ) {
			return;
		}

		processScheduled = true;

		const run = () => {
			processScheduled = false;
			processWrappers();
		};

		// Defer briefly so DOM paint can complete; fall back when rAF is missing.
		if ( typeof window.requestAnimationFrame === 'function' ) {
			window.requestAnimationFrame( run );
		} else {
			window.setTimeout( run, 0 );
		}
	}

	// Observe mutations for dynamic elements or asynchronous Pinterest rendering.
	const observer = new window.MutationObserver( function ( mutations ) {
		for ( const mutation of mutations ) {
			if ( isPinterestRelatedMutation( mutation ) ) {
				scheduleProcessWrappers();
				break;
			}
		}
	} );

	// Observe child node additions and attribute changes across the document.
	// Body-level observation is required so newly inserted wrappers (AJAX
	// product grids, etc.) are still detected; isPinterestRelatedMutation
	// keeps processWrappers from running on unrelated DOM noise.
	observer.observe( document.body, {
		childList: true,
		subtree: true,
		attributes: true,
		attributeFilter: [ 'data-pin-log' ],
	} );
} );
