/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withSelect } from '@wordpress/data';
import {
	Card,
	CardHeader,
	CardBody,
	__experimentalText as Text,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { REPORTS_STORE_NAME } from '../data';
import SyncStateSummary from './SyncStateSummary';
import SyncStateTable from './SyncStateTable';

const SyncState = ({ feedState }) => {
	return (
		<Card className="woocommerce-table pin4wc-catalog-sync__state">
			<CardHeader>
				<Text variant="title.small" as="h2">
					{ __( 'Overview', 'pinterest-for-woocommerce' ) }
				</Text>
			</CardHeader>
			<CardBody className="no-padding">
				<SyncStateSummary {...{ feedState } } />
				<SyncStateTable {...{ feedState } } />
			</CardBody>
		</Card>
	)
};

export default compose(
	withSelect( ( select ) => {
		const { getFeedState } = select( REPORTS_STORE_NAME );

		return {
			feedState: getFeedState()
		}
	})
)(SyncState);
