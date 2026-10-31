/**
 * External dependencies
 */
// eslint-disable-next-line import/no-unresolved -- WooCommerce provides this external module.
import { registerBlockComponent } from '@woocommerce/blocks-registry';

/**
 * Internal dependencies
 */
import ProductPin from './component';

registerBlockComponent( {
	blockName: 'pinterest-for-woocommerce/product-pin',
	context: 'woocommerce/all-products',
	component: ProductPin,
} );
