vi.mock( '@woocommerce/tracks' );
/**
 * External dependencies
 */
import { beforeEach, describe, expect, test, vi } from 'vitest';
import { recordEvent } from '@woocommerce/tracks';
import '@testing-library/jest-dom/vitest';
import { fireEvent, render } from '@testing-library/react';
import { getHistory, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import '.~/tests/custom-matchers';
import { expectKnownReactDeprecations } from '.~/tests/known-react-deprecations';
import WizardApp from './WizardApp';

recordEvent.mockName( 'recordEvent' );

//Needed to be able to render the Stepper component
vi.mock( '../steps/SetupAccount', () => ( { default: () => null } ) );
vi.mock( '../steps/ClaimWebsite', () => ( { default: () => null } ) );
vi.mock( '../steps/SetupTracking', () => ( { default: () => null } ) );

const stepOne = /Set up your business account/;
const stepTwo = /Claim your website/;

describe( 'WizardApp component', () => {
	expectKnownReactDeprecations( 'Link' );

	// Each test starts without a `step` in the URL and without recorded events.
	beforeEach( () => {
		getHistory().replace( '/' );
		recordEvent.mockClear();
	} );

	describe( 'First rendering', () => {
		let rendered;
		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = false;
			wcSettings.pinterest_for_woocommerce.isConnected = false;
			rendered = render( <WizardApp query={ getQuery() } /> );
		} );
		test( 'should show all options and first step should be clickable', () => {
			expect( rendered.getByText( stepOne ) ).toBeInTheDocument();
			expect( rendered.getByText( stepTwo ) ).toBeInTheDocument();

			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 1 );
		} );
	} );
	describe( 'First step is completed', () => {
		let rendered;

		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = true;
			wcSettings.pinterest_for_woocommerce.isConnected = true;
			rendered = render( <WizardApp query={ getQuery() } /> );
		} );

		test( 'should step 1 & 2 be clickable in the stepper', () => {
			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 2 );
			expect(
				rendered.getByRole( 'button', {
					name: stepOne,
				} )
			).toBeInTheDocument();
			expect(
				rendered.getByRole( 'button', {
					name: stepTwo,
				} )
			).toBeInTheDocument();
		} );
	} );
	describe( 'Stepper navigation', () => {
		let rendered;

		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = true;
			wcSettings.pinterest_for_woocommerce.isConnected = true;
			rendered = render( <WizardApp query={ getQuery() } /> );
		} );

		test( 'should event tracking be = setup-account after click', () => {
			const setUpButton = rendered.getByRole( 'button', {
				name: stepOne,
			} );

			fireEvent.click( setUpButton );

			expect( recordEvent ).toHaveBeenCalledWith( 'pfw_setup', {
				target: 'setup-account',
				trigger: 'wizard-stepper',
			} );
		} );
		test( 'should navigate to `step=setup-account`', () => {
			expect( window.location ).not.toContainURLSearchParam( 'step' );

			fireEvent.click(
				rendered.getByRole( 'button', {
					name: stepOne,
				} )
			);

			expect( window.location ).toContainURLSearchParam(
				'step',
				'setup-account'
			);
		} );

		test( 'should event tracking be = claim-website after click', () => {
			fireEvent.click(
				rendered.getByRole( 'button', {
					name: stepTwo,
				} )
			);

			expect( recordEvent ).toHaveBeenCalledWith( 'pfw_setup', {
				target: 'claim-website',
				trigger: 'wizard-stepper',
			} );
		} );

		test( 'should navigate to `step=claim-website`', () => {
			expect( window.location ).not.toContainURLSearchParam( 'step' );

			fireEvent.click(
				rendered.getByRole( 'button', {
					name: stepTwo,
				} )
			);

			expect( window.location ).toContainURLSearchParam(
				'step',
				'claim-website'
			);
		} );
	} );
} );
