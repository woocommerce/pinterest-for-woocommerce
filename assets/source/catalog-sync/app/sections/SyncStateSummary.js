/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	SummaryList,
	SummaryNumber,
	SummaryListPlaceholder
} from '@woocommerce/components';

const SyncStateSummary = ({ feedState }) => {
	feedState = 1;

	const summaryItems = [
		<SummaryNumber
			key="active"
			value='145'
			label={ __( 'Active', 'pinterest-for-woocommerce' ) }
		/>,
		<SummaryNumber
			key="not-synced"
			value='40'
			label={ __( 'Not Synced', 'pinterest-for-woocommerce' ) }
		/>,
		<SummaryNumber
			key="with-warnings"
			value='9'
			label={ __( 'With Warnings', 'pinterest-for-woocommerce' ) }
		/>,
		<SummaryNumber
			key="with-errors"
			value='40'
			label={ __( 'With Errors', 'pinterest-for-woocommerce' ) }
		/>,
	];

	return (
		feedState
		? <SummaryList>{ () => summaryItems }</SummaryList>
		: <SummaryListPlaceholder numberOfItems={ summaryItems.length } />
	)
};

export default SyncStateSummary;
