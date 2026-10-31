/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * A product-card link that remains correct when All Products changes pages.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.product Product from WooCommerce's Store API.
 * @return {JSX.Element|null} The Save link, or nothing for a loading card without an image.
 */
export default function ProductPin( { product = {} } ) {
	const image = product.images?.[ 0 ]?.src;
	if (
		! product.id ||
		! product.permalink ||
		! image ||
		document.querySelector( '[data-pinterest-extension]' )
	) {
		return null;
	}

	const name = decodeEntities( product.name || '' );
	const parameters = new URLSearchParams( {
		url: product.permalink,
		media: image,
		description: name,
	} );

	return (
		<div className="wp-block-button">
			<a
				className="wp-block-button__link wp-element-button"
				data-pin-do="none"
				href={ `https://www.pinterest.com/pin/create/button/?${ parameters }` }
				target="_blank"
				rel="noopener noreferrer"
				aria-label={ sprintf(
					/* translators: %s: product name. */
					__(
						'Save to Pinterest: %s (opens in a new window)',
						'pinterest-for-woocommerce'
					),
					name
				) }
			>
				{ __( 'Save to Pinterest', 'pinterest-for-woocommerce' ) }
			</a>
		</div>
	);
}
