/**
 * External dependencies
 */
import { recordEvent } from '@woocommerce/tracks';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import {
	Button,
	ExternalLink,
	Notice,
	__experimentalText as Text, // eslint-disable-line @wordpress/no-unsafe-wp-apis --- __experimentalText is used throughout this plugin.
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { RATINGS_NOTICE_STORE_NAME } from './data';
import './index.scss';

const REVIEW_URLS = {
	wporg: 'https://wordpress.org/support/plugin/pinterest-for-woocommerce/reviews/#new-post',
	wc: 'https://woocommerce.com/products/pinterest-for-woocommerce/',
};

/**
 * In-plugin ratings notice. Rendered on the Catalog Sync landing when the
 * server decides the current merchant is a healthy, active user.
 *
 * @fires wcadmin_pfw_rating_notice_shown
 * @fires wcadmin_pfw_rating_notice_clicked
 * @fires wcadmin_pfw_rating_notice_snoozed
 * @fires wcadmin_pfw_rating_notice_dismissed_forever
 *
 * @return {JSX.Element|null} The notice or null when suppressed.
 */
const RatingsNotice = () => {
	const { decision, loaded } = useSelect( ( select ) => {
		const store = select( RATINGS_NOTICE_STORE_NAME );
		return {
			decision: store.getRatingNoticeDecision(),
			loaded: store.isRatingNoticeDecisionLoaded(),
		};
	}, [] );

	const {
		snoozeRatingNotice,
		clickedRatingNotice,
		dismissRatingNoticeForever,
	} = useDispatch( RATINGS_NOTICE_STORE_NAME );

	const shownEventFired = useRef( false );
	useEffect( () => {
		if ( loaded && decision?.should_show && ! shownEventFired.current ) {
			shownEventFired.current = true;
			recordEvent( 'pfw_rating_notice_shown', {
				channel: decision.channel,
				ask_count: decision.ask_count,
			} );
		}
	}, [ loaded, decision ] );

	if ( ! loaded || ! decision?.should_show ) {
		return null;
	}

	const isSecondAsk = decision.ask_count >= 2;
	const reviewUrl = REVIEW_URLS[ decision.channel ] || REVIEW_URLS.wporg;

	const trackMeta = {
		channel: decision.channel,
		ask_count: decision.ask_count,
	};

	const handleClicked = () => {
		recordEvent( 'pfw_rating_notice_clicked', trackMeta );
		clickedRatingNotice();
	};

	const handleSnooze = ( trigger ) => {
		recordEvent( 'pfw_rating_notice_snoozed', {
			...trackMeta,
			trigger,
		} );
		snoozeRatingNotice();
	};

	const handleDismissForever = () => {
		recordEvent( 'pfw_rating_notice_dismissed_forever', trackMeta );
		dismissRatingNoticeForever();
	};

	const ctaLabel =
		decision.channel === 'wc'
			? __(
					'Rate us on WooCommerce.com',
					'pinterest-for-woocommerce'
			  )
			: __( 'Rate us on WordPress.org', 'pinterest-for-woocommerce' );

	return (
		<Notice
			status="info"
			isDismissible={ true }
			onRemove={ () => handleSnooze( 'x' ) }
			className="pinterest-for-woocommerce-ratings-notice"
		>
			<Text className="pinterest-for-woocommerce-ratings-notice__headline">
				{ __(
					'Enjoying Pinterest for WooCommerce?',
					'pinterest-for-woocommerce'
				) }
			</Text>
			<Text>
				{ __(
					'If this extension has been useful for your store, please take a minute to leave a review. It helps other merchants find the plugin and helps us keep improving it.',
					'pinterest-for-woocommerce'
				) }
			</Text>
			<div className="pinterest-for-woocommerce-ratings-notice__actions">
				<ExternalLink
					href={ reviewUrl }
					className="components-button is-primary"
					onClick={ handleClicked }
				>
					{ ctaLabel }
				</ExternalLink>
				<Button
					variant="tertiary"
					onClick={ () => handleSnooze( 'button' ) }
				>
					{ __( 'Remind me later', 'pinterest-for-woocommerce' ) }
				</Button>
				{ isSecondAsk && (
					<Button
						variant="link"
						onClick={ handleDismissForever }
						className="pinterest-for-woocommerce-ratings-notice__dismiss-forever"
					>
						{ __(
							'Don’t ask again',
							'pinterest-for-woocommerce'
						) }
					</Button>
				) }
			</div>
		</Notice>
	);
};

export default RatingsNotice;
