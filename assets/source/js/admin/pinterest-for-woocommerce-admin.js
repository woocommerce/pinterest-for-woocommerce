/* global jQuery */

( function ( $ ) {
	'use strict';

	$( document ).on(
		'woocommerce-product-type-change',
		'body',
		function ( event, productType ) {
			$( '.hide_if_' + productType ).hide();
		}
	);
} )( jQuery );
