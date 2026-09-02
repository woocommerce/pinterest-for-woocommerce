/**
 * Tests for the Save to Pinterest button script.
 *
 * The script has no exports: requiring it registers the listeners, and the
 * tests drive it through the DOM the same way a browser would.
 */

const SCRIPT_PATH = './pinterest-for-woocommerce-save-button';

/**
 * Markup rendered server side by SaveToPinterest::render_pin().
 *
 * @param {string} label Screen reader label.
 * @return {string} Wrapper markup with an unbuilt Pinterest placeholder.
 */
function unbuiltWrapper( label ) {
	return `<div class="pinterest-for-woocommerce-image-wrapper"><span class="screen-reader-text">${ label }</span><a data-pin-do="buttonPin" href="https://www.pinterest.com/pin/create/button/"></a></div>`;
}

/**
 * Simulate what pinit.js does to a placeholder once it builds it.
 *
 * @param {Element} wrapper Wrapper holding the placeholder.
 */
function markAsBuilt( wrapper ) {
	wrapper
		.querySelector( 'a[data-pin-do]' )
		.setAttribute( 'data-pin-log', 'button_pinit' );
}

/**
 * Wait long enough for MutationObserver callbacks, rAF and retry timers.
 *
 * @param {number} ms Milliseconds to wait.
 * @return {Promise} Resolves after the delay.
 */
function flush( ms = 50 ) {
	return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
}

describe( 'Save to Pinterest button', () => {
	// Load the script once: its DOMContentLoaded listener registers a
	// MutationObserver on the body, and loading it per test would stack one
	// observer per copy and inflate build() call counts.
	beforeAll( () => {
		// The shared preset enables fake timers; switch to real ones so the
		// MutationObserver callbacks, rAF and retry timers run on their own.
		jest.useRealTimers();
		require( SCRIPT_PATH );
		document.dispatchEvent( new window.Event( 'DOMContentLoaded' ) );
	} );

	beforeEach( () => {
		document.body.innerHTML = '';
		document.head
			.querySelectorAll( 'style' )
			.forEach( ( s ) => s.remove() );
		delete window.PinUtils;
	} );

	/**
	 * Stand in for the stylesheet pinit.js injects into the head at runtime.
	 *
	 * @param {string} css Stylesheet body.
	 * @return {HTMLStyleElement} The appended style element.
	 */
	function addStyle( css ) {
		const style = document.createElement( 'style' );
		style.textContent = css;
		document.head.appendChild( style );
		return style;
	}

	/**
	 * Add an unbuilt page of products, as a pagination swap would.
	 */
	function swapInNewPage() {
		const grid = document.createElement( 'div' );
		grid.innerHTML = unbuiltWrapper( 'Save Hoodie to Pinterest' );
		document.body.appendChild( grid );
	}

	it( 'rebuilds pins added to the DOM after the initial render', async () => {
		document.body.innerHTML = unbuiltWrapper( 'Save Shirt to Pinterest' );
		window.PinUtils = { build: jest.fn() };

		markAsBuilt(
			document.querySelector( '.pinterest-for-woocommerce-image-wrapper' )
		);
		await flush();

		window.PinUtils.build.mockClear();

		// Pagination replaces the product grid with a fresh, unbuilt page.
		const grid = document.createElement( 'div' );
		grid.innerHTML = unbuiltWrapper( 'Save Hoodie to Pinterest' );
		document.body.appendChild( grid );
		await flush();

		expect( window.PinUtils.build ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'rebuilds when the wrapper itself is the added node', async () => {
		window.PinUtils = { build: jest.fn() };

		// Some grids append the wrapper directly rather than a parent block.
		const wrapper = document.createElement( 'div' );
		wrapper.className = 'pinterest-for-woocommerce-image-wrapper';
		wrapper.innerHTML =
			'<span class="screen-reader-text">Save Hoodie to Pinterest</span><a data-pin-do="buttonPin" href="https://www.pinterest.com/pin/create/button/"></a>';
		document.body.appendChild( wrapper );
		await flush();

		expect( window.PinUtils.build ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not rebuild when the added markup holds no unbuilt pin', async () => {
		document.body.innerHTML = unbuiltWrapper( 'Save Shirt to Pinterest' );
		window.PinUtils = { build: jest.fn() };

		markAsBuilt(
			document.querySelector( '.pinterest-for-woocommerce-image-wrapper' )
		);
		await flush();

		window.PinUtils.build.mockClear();

		document.body.appendChild( document.createElement( 'p' ) );
		await flush();

		expect( window.PinUtils.build ).not.toHaveBeenCalled();
	} );

	it( 'waits for pinit.js when PinUtils is not available yet', async () => {
		const grid = document.createElement( 'div' );
		grid.innerHTML = unbuiltWrapper( 'Save Hoodie to Pinterest' );
		document.body.appendChild( grid );
		await flush();

		// pinit.js is loaded async/defer, so it can land after the swap.
		window.PinUtils = { build: jest.fn() };
		await flush( 500 );

		expect( window.PinUtils.build ).toHaveBeenCalled();
	} );

	it( 'stops retrying when pinit.js never loads', async () => {
		const grid = document.createElement( 'div' );
		grid.innerHTML = unbuiltWrapper( 'Save Hoodie to Pinterest' );
		document.body.appendChild( grid );

		// Long enough for the whole retry budget to be spent.
		await flush( 2600 );

		window.PinUtils = { build: jest.fn() };
		await flush( 600 );

		expect( window.PinUtils.build ).not.toHaveBeenCalled();
	}, 10000 );

	it( 're-enables the Pinterest stylesheet the router switched off', async () => {
		const pinStyle = addStyle(
			'.PIN_1788176069676_button_pin{background-image:url(logo.svg)}'
		);
		window.PinUtils = { build: jest.fn() };

		// The Interactivity API router disables every stylesheet missing from
		// the page it fetched, including pinit.js's runtime one.
		pinStyle.sheet.disabled = true;
		swapInNewPage();
		await flush( 150 );

		expect( pinStyle.sheet.disabled ).toBe( false );
	} );

	it( 'leaves unrelated disabled stylesheets alone', async () => {
		const otherStyle = addStyle( '.some-theme-thing{color:red}' );
		window.PinUtils = { build: jest.fn() };

		otherStyle.sheet.disabled = true;
		swapInNewPage();
		await flush( 150 );

		expect( otherStyle.sheet.disabled ).toBe( true );
	} );

	it( 'labels pins built after a pagination swap', async () => {
		window.PinUtils = {
			build: jest.fn( () => {
				document
					.querySelectorAll(
						'.pinterest-for-woocommerce-image-wrapper'
					)
					.forEach( markAsBuilt );
			} ),
		};

		const grid = document.createElement( 'div' );
		grid.innerHTML = unbuiltWrapper( 'Save Hoodie to Pinterest' );
		document.body.appendChild( grid );
		await flush( 200 );

		const pinLink = document.querySelector( 'a[data-pin-do]' );
		expect( pinLink.querySelector( '.screen-reader-text' ) ).not.toBeNull();
		expect( pinLink.getAttribute( 'aria-haspopup' ) ).toBe( 'dialog' );
	} );
} );
