/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { render, fireEvent } from '@testing-library/react';
import { useDispatch, useSelect } from '@wordpress/data';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import RatingsNotice from './index';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
	registerStore: jest.fn(),
	createReduxStore: jest.fn(),
	register: jest.fn(),
} ) );

jest.mock( '@woocommerce/tracks', () => ( {
	recordEvent: jest.fn(),
} ) );

const dispatchers = {
	snoozeRatingNotice: jest.fn(),
	clickedRatingNotice: jest.fn(),
	dismissRatingNoticeForever: jest.fn(),
};

const seedDecision = ( overrides = {} ) => {
	useSelect.mockImplementation( ( cb ) =>
		cb( () => ( {
			getRatingNoticeDecision: () => ( {
				should_show: true,
				channel: 'wporg',
				ask_count: 1,
				reason: 'ok',
				...overrides,
			} ),
			isRatingNoticeDecisionLoaded: () => true,
		} ) )
	);
};

describe( 'RatingsNotice', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( dispatchers );
	} );

	it( 'renders nothing when the decision is not loaded', () => {
		useSelect.mockImplementation( ( cb ) =>
			cb( () => ( {
				getRatingNoticeDecision: () => ( { should_show: true } ),
				isRatingNoticeDecisionLoaded: () => false,
			} ) )
		);

		const { container } = render( <RatingsNotice /> );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'renders nothing when should_show is false', () => {
		useSelect.mockImplementation( ( cb ) =>
			cb( () => ( {
				getRatingNoticeDecision: () => ( { should_show: false } ),
				isRatingNoticeDecisionLoaded: () => true,
			} ) )
		);

		const { container } = render( <RatingsNotice /> );
		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'fires pfw_rating_notice_shown once on mount when visible', () => {
		seedDecision();

		render( <RatingsNotice /> );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_rating_notice_shown',
			expect.objectContaining( {
				channel: 'wporg',
				ask_count: 1,
			} )
		);
	} );

	it( 'fires clicked event and dispatches the click action on CTA', () => {
		seedDecision();

		const { getByText } = render( <RatingsNotice /> );
		fireEvent.click( getByText( 'Rate us on WordPress.org' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_rating_notice_clicked',
			expect.objectContaining( { channel: 'wporg' } )
		);
		expect( dispatchers.clickedRatingNotice ).toHaveBeenCalled();
	} );

	it( 'fires snoozed event with button trigger on Remind me later', () => {
		seedDecision();

		const { getByText } = render( <RatingsNotice /> );
		fireEvent.click( getByText( 'Remind me later' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_rating_notice_snoozed',
			expect.objectContaining( { trigger: 'button' } )
		);
		expect( dispatchers.snoozeRatingNotice ).toHaveBeenCalled();
	} );

	it( 'exposes Don’t ask again only on the second ask', () => {
		seedDecision( { ask_count: 2 } );

		const { getByText } = render( <RatingsNotice /> );
		fireEvent.click( getByText( 'Don’t ask again' ) );

		expect( recordEvent ).toHaveBeenCalledWith(
			'pfw_rating_notice_dismissed_forever',
			expect.any( Object )
		);
		expect( dispatchers.dismissRatingNoticeForever ).toHaveBeenCalled();
	} );

	it( 'uses the woocommerce.com CTA when channel is wc', () => {
		seedDecision( { channel: 'wc' } );

		const { getByText } = render( <RatingsNotice /> );
		expect( getByText( 'Rate us on WooCommerce.com' ) ).toBeInTheDocument();
	} );
} );
