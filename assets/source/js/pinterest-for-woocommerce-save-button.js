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
	// Placeholder markup that pinit.js has not turned into a button yet.
	const UNBUILT_PIN_SELECTOR =
		'.pinterest-for-woocommerce-image-wrapper a[data-pin-do]:not([data-pin-log])';

	// pinit.js is loaded async, so it can land after a placeholder appears.
	const BUILD_RETRY_DELAY = 400;
	const BUILD_MAX_RETRIES = 5;

	// Class prefix pinit.js writes into the stylesheet it injects at runtime.
	const PIN_STYLE_PATTERN = /_button_pin/;

	// The router disables stylesheets while it renders, so restore over a
	// short window rather than a single pass that could land too early.
	const STYLE_RESTORE_PASSES = 4;
	const STYLE_RESTORE_DELAY = 200;

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

	/**
	 * Whether a node added to the DOM brings a placeholder pinit.js has not
	 * built yet.
	 *
	 * @param {Node} node Added node to inspect.
	 * @return {boolean} True when the node holds an unbuilt placeholder.
	 */
	function containsUnbuiltPin( node ) {
		if ( node.nodeType !== 1 ) {
			return false;
		}

		return (
			node.matches( UNBUILT_PIN_SELECTOR ) ||
			!! node.querySelector( UNBUILT_PIN_SELECTOR )
		);
	}

	/**
	 * Re-enable the stylesheet pinit.js injects at runtime.
	 *
	 * The Interactivity API router disables every stylesheet that is missing
	 * from the page it just fetched. The Save button's styles are injected
	 * into the head by pinit.js after load, so they are never part of a
	 * server response and get switched off on the first client-side
	 * navigation, leaving the button as bare "Save" text.
	 */
	function restorePinStyles() {
		document.querySelectorAll( 'style' ).forEach( function ( style ) {
			if (
				style.sheet &&
				style.sheet.disabled &&
				PIN_STYLE_PATTERN.test( style.textContent )
			) {
				style.sheet.disabled = false;
			}
		} );
	}

	let restoreTimer = null;
	let restorePasses = 0;

	function runRestorePass() {
		restoreTimer = null;
		restorePinStyles();
		restorePasses--;

		if ( restorePasses > 0 ) {
			restoreTimer = window.setTimeout(
				runRestorePass,
				STYLE_RESTORE_DELAY
			);
		}
	}

	function scheduleRestorePinStyles() {
		restorePasses = STYLE_RESTORE_PASSES;

		if ( restoreTimer ) {
			return;
		}

		runRestorePass();
	}

	let retriesLeft = 0;
	let retryTimer = null;

	/**
	 * Ask pinit.js to render the placeholders it has not processed yet.
	 *
	 * pinit.js only scans the document when it loads, so markup inserted
	 * afterwards - client-side pagination, filtering or sorting of the
	 * Product Collection block, AJAX product grids - keeps its placeholder
	 * markup and never turns into a button. PinUtils.build() rescans the
	 * document and skips anything it has already built.
	 */
	function buildPins() {
		retryTimer = null;

		if ( ! document.querySelector( UNBUILT_PIN_SELECTOR ) ) {
			return;
		}

		const pinUtils = window.PinUtils;

		if ( pinUtils && typeof pinUtils.build === 'function' ) {
			try {
				pinUtils.build();
			} catch ( error ) {
				// A failed build must not break the rest of the page.
			}
			return;
		}

		if ( retriesLeft <= 0 ) {
			return;
		}

		retriesLeft--;
		retryTimer = window.setTimeout( buildPins, BUILD_RETRY_DELAY );
	}

	function scheduleBuildPins() {
		// Fresh content deserves a fresh budget of attempts.
		retriesLeft = BUILD_MAX_RETRIES;

		if ( retryTimer ) {
			return;
		}

		retryTimer = window.setTimeout( buildPins, 0 );
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
		let needsProcess = false;
		let needsBuild = false;

		for ( const mutation of mutations ) {
			if ( ! needsProcess && isPinterestRelatedMutation( mutation ) ) {
				needsProcess = true;
			}

			if ( ! needsBuild && mutation.type === 'childList' ) {
				for ( const node of mutation.addedNodes ) {
					if ( containsUnbuiltPin( node ) ) {
						needsBuild = true;
						break;
					}
				}
			}

			if ( needsProcess && needsBuild ) {
				break;
			}
		}

		if ( needsProcess || needsBuild ) {
			scheduleProcessWrappers();
			scheduleRestorePinStyles();
		}

		// Only newly inserted placeholders trigger a rebuild: while pinit.js
		// works through the initial page it mutates attributes, not nodes, so
		// we never race its own rendering pass.
		if ( needsBuild ) {
			scheduleBuildPins();
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
