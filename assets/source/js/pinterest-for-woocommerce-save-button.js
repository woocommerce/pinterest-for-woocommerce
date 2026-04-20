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
