/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import LandingPageApp from './app/views/LandingPageApp';
import WizardApp from './app/views/WizardApp';
import ConnectionApp from './app/views/ConnectionApp';
import SettingsApp from './app/views/SettingsApp';
import CatalogSyncApp from '../catalog-sync/app/App';

import './app/style.scss';

addFilter(
	'woocommerce_admin_pages_list',
	'woocommerce-marketing',
	( pages ) => {
		pages.push( {
			container: LandingPageApp,
			path: '/pinterest/landing',
			breadcrumbs: [ 'Pinterest' ],
			wpOpenMenu: 'toplevel_page_woocommerce-marketing',
			navArgs: {
				id: 'pinterest-for-woocommerce-landing-page',
			},
		} );

		pages.push( {
			container: WizardApp,
			path: '/pinterest/onboarding',
			breadcrumbs: [ 'Onboarding Guide' ],
			navArgs: {
				id: 'pinterest-for-woocommerce-setup-guide',
			},
		} );

		pages.push( {
			container: ConnectionApp,
			path: '/pinterest/connection',
			breadcrumbs: [ 'Connection' ],
			wpOpenMenu: 'toplevel_page_woocommerce-marketing',
			navArgs: {
				id: 'pinterest-for-woocommerce-connection',
			},
		} );

		pages.push( {
			container: SettingsApp,
			path: '/pinterest/settings',
			breadcrumbs: [ 'Settings' ],
			wpOpenMenu: 'toplevel_page_woocommerce-marketing',
			navArgs: {
				id: 'pinterest-for-woocommerce-settings',
			},
		} );

		pages.push( {
			container: CatalogSyncApp,
			path: '/pinterest/catalog',
			breadcrumbs: [ 'Products Catalog' ],
			wpOpenMenu: 'toplevel_page_woocommerce-marketing',
			navArgs: {
				id: 'pinterest-for-woocommerce-catalog',
			},
		} );

		return pages;
	}
);
