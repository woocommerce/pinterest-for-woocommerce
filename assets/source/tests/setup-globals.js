/**
 * External dependencies
 */
import { afterAll, afterEach, beforeAll, beforeEach } from 'vitest';
// eslint-disable-next-line testing-library/no-manual-cleanup -- Vitest runs without globals, so Testing Library cannot register its cleanup itself.
import { cleanup } from '@testing-library/react';

/*
 * Testing Library sets itself up through the test globals, which Vitest does
 * not expose here. Do what it would have done: unmount after each test, and
 * let React report updates that happen outside `act()`.
 */
afterEach( cleanup );
// Vitest skips the remaining `afterEach` hooks once one fails, so also start clean.
beforeEach( cleanup );

let previousActEnvironment;

beforeAll( () => {
	previousActEnvironment = globalThis.IS_REACT_ACT_ENVIRONMENT;
	globalThis.IS_REACT_ACT_ENVIRONMENT = true;
} );

afterAll( () => {
	globalThis.IS_REACT_ACT_ENVIRONMENT = previousActEnvironment;
} );

// jsdom has no `matchMedia`, and `@wordpress/viewport` calls it on import.
window.matchMedia = () => ( {
	matches: false,
	addListener: () => {},
	addEventListener: () => {},
	removeListener: () => {},
	removeEventListener: () => {},
} );

globalThis.wcSettings = {
	pinterest_for_woocommerce: {
		apiRoute: '/pinterest/v1',
		optionsName: 'pinterest_for_woocommerce',
		claimWebsiteErrorStatus: [],
		pluginVersion: '1.2.3',
		pinterestLinks: {
			adsManager: 'https://example.com',
			preLaunchNotice: 'https://example.com',
			adsAvailability: 'https://example.com',
		},
	},
};
