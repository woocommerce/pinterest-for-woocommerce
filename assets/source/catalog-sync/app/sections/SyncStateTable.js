/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Icon,
} from '@wordpress/components';
import { Table, TablePlaceholder } from '@woocommerce/components';

const SyncStateTable = ({ feedState }) => {
	feedState = 1;
	const defaultHeaderAttributes = {
		isLeftAligned: true,
		isSortable: false,
	}

	const headers = [
		{ key: 'property', label: __( 'Property', 'pinterest-for-woocommerce' ), ...defaultHeaderAttributes },
		{ key: 'state', label: __( 'State', 'pinterest-for-woocommerce' ),...defaultHeaderAttributes },
	];

	const rows = [
		[
			{ display: 'Feed setup:' },
			{ display: <><span className="green-text"><Icon icon="yes-alt" /> Initial setup completed</span> &nbsp; • &nbsp; 15 issues to resolve</> },
		],
		[
			{ display: 'XML feed:' },
			{ display: <><span className="green-text"><Icon icon="yes-alt" /> Up to date</span> &nbsp; • &nbsp; Last updated: 31 March 2021, 01:45pm, containing 315 products</> },
		],
		[
			{ display: 'Sync with Pinterest:' },
			{ display: <><span className="green-text"><Icon icon="yes-alt" /> Automatically pulled by Pinterest</span> &nbsp; • &nbsp; Last pulled: 31 March 2021, 01:45pm, containing 315 products</> },
		],
	];

	return (
		feedState
			? (
				<Table
					rows={ rows }
					headers={ headers }
					showMenu={ false }
				/>
			) : (
				<TablePlaceholder
					headers={ headers }
					numberOfRows={ 3 }
				/>
			)
	)
};

export default SyncStateTable;
