/**
 * External dependencies
 */
import { decodeEntities } from '@wordpress/html-entities';
import { __, sprintf } from '@wordpress/i18n';

/**
 * The shared Save placeholder, replaced as a whole when the current product changes.
 *
 * @param {Object} props         Component props.
 * @param {Object} props.product Product from WooCommerce's Store API.
 * @return {JSX.Element|null} The Save placeholder, or nothing for a loading card without an image.
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

	// pinit.js replaces the anchor and moves the label. Remount the wrapper when
	// its data changes, so React never updates nodes owned by Pinterest.
	return (
		<div
			key={ parameters.toString() }
			className="pinterest-for-woocommerce-image-wrapper"
		>
			<span className="screen-reader-text">
				{ sprintf(
					/* translators: %s: product name. */
					__( '%s to Pinterest', 'pinterest-for-woocommerce' ),
					name
				) }
				<span className="pinterest-for-woocommerce-new-window-hint">
					{ ' ' }
					{ __(
						'(opens in a new window)',
						'pinterest-for-woocommerce'
					) }
				</span>
			</span>
			{ /* pinit.js replaces the empty placeholder with the accessible shared widget. */ }
			{ /* eslint-disable-next-line jsx-a11y/anchor-has-content */ }
			<a
				data-pin-do="buttonPin"
				href={ `https://www.pinterest.com/pin/create/button/?${ parameters }` }
			/>
		</div>
	);
}
