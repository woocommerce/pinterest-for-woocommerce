jest.mock( '@woocommerce/tracks' );
jest.mock( '../data/settings/selectors', () => ( {
	...jest.requireActual( '../data/settings/selectors' ), // import and retain the original functionalities
	isTrackingConfigured: jest.fn().mockReturnValue( false ),
	isDomainVerified: jest.fn().mockReturnValue( false ),
} ) );
/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import '@testing-library/jest-dom';
import { fireEvent, render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import WizardApp from './WizardApp';
import '../../../tests/custom-matchers';
import {
	isDomainVerified,
	isTrackingConfigured,
} from '../data/settings/selectors';

recordEvent.mockName( 'recordEvent' );

//Needed to be able to render the Stepper component
jest.mock( '../steps/SetupAccount', () => () => null );
jest.mock( '../steps/ClaimWebsite', () => () => null );
jest.mock( '../steps/SetupTracking', () => () => null );

afterEach( () => {
	jest.clearAllMocks();
} );

describe( 'WizardApp component', () => {
	describe( 'First rendering', () => {
		let rendered;
		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = false;
			wcSettings.pinterest_for_woocommerce.isConnected = false;
			rendered = render( <WizardApp /> );
		} );
		test( 'should show all options and not clickables button in the Stepper', () => {
			expect(
				rendered.getByText( /Set up your business account/ )
			).toBeInTheDocument();
			expect(
				rendered.getByText( /Claim your website/ )
			).toBeInTheDocument();
			expect(
				rendered.getByText( /Track conversions/ )
			).toBeInTheDocument();

			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 0 );
		} );
	} );
	describe( 'First step is completed', () => {
		let rendered;

		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = true;
			wcSettings.pinterest_for_woocommerce.isConnected = true;
			rendered = render( <WizardApp /> );
		} );

		test( 'should only the first step button be shown in the Stepper', () => {
			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 1 );
			expect(
				rendered.getByRole( 'button', {
					name: /Set up your business account/,
				} )
			).toBeInTheDocument();
		} );
	} );
	describe( 'Second step is completed', () => {
		let rendered;

		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = true;
			wcSettings.pinterest_for_woocommerce.isConnected = true;
			isDomainVerified.mockImplementation( () => true );
			rendered = render( <WizardApp /> );
		} );

		test( 'should only the first 2 steps button be shown in the Stepper', () => {
			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 2 );

			const setUpButton = rendered.getByRole( 'button', {
				name: /Set up your business account/,
			} );
			const claimButton = rendered.getByRole( 'button', {
				name: /Claim your website/,
			} );

			expect( setUpButton ).toBeInTheDocument();
			expect( claimButton ).toBeInTheDocument();
		} );
		test( 'should event tracking be = setup-account after click', () => {
			const setUpButton = rendered.getByRole( 'button', {
				name: /Set up your business account/,
			} );

			fireEvent.click( setUpButton );

			expect( recordEvent ).toHaveBeenCalledWith( 'pfw_setup', {
				target: 'setup-account',
				trigger: 'wizard-stepper',
			} );
		} );
		test( 'should navigate to `step=setup-account`', () => {
			expect( window.location ).toContainURLSearchParam(
				'step',
				'setup-account'
			);
		} );
	} );

	describe( 'Third step is completed', () => {
		let rendered;

		beforeEach( () => {
			wcSettings.pinterest_for_woocommerce.isBusinessConnected = true;
			wcSettings.pinterest_for_woocommerce.isConnected = true;
			isDomainVerified.mockImplementation( () => true );
			isTrackingConfigured.mockImplementation( () => true );
			rendered = render( <WizardApp /> );
		} );

		test( 'should all three steps be clickable buttons', () => {
			expect( rendered.queryAllByRole( 'button' ).length ).toBe( 3 );

			expect(
				rendered.getByRole( 'button', {
					name: /Set up your business account/,
					exact: false,
				} )
			).toBeInTheDocument();
			expect(
				rendered.getByRole( 'button', {
					name: /Claim your website/,
					exact: false,
				} )
			).toBeInTheDocument();
			expect(
				rendered.getByRole( 'button', {
					name: /Track conversions/,
					exact: false,
				} )
			).toBeInTheDocument();
		} );

		test( 'should event tracking be = claim-website after click', () => {
			fireEvent.click(
				rendered.getByRole( 'button', {
					name: /Claim your website/,
				} )
			);

			expect( recordEvent ).toHaveBeenCalledWith( 'pfw_setup', {
				target: 'claim-website',
				trigger: 'wizard-stepper',
			} );
		} );

		test( 'should navigate to `step=claim-website', () => {
			expect( window.location ).toContainURLSearchParam(
				'step',
				'claim-website'
			);
		} );

		test( 'should event tracking be = setup-tracking after click', () => {
			fireEvent.click(
				rendered.getByRole( 'button', {
					name: /Track conversions/,
				} )
			);

			expect( recordEvent ).toHaveBeenCalledWith( 'pfw_setup', {
				target: 'setup-tracking',
				trigger: 'wizard-stepper',
			} );
		} );
	} );
} );
