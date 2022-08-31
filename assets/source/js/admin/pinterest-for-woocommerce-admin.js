/* global jQuery */

( function ( $ ) {
	'use strict';

	$( document.body ).on(
		'woocommerce-product-type-change',
		function ( productType ) {
			$( '.hide_if_' + productType ).hide();
		}
	);
} )( jQuery );
