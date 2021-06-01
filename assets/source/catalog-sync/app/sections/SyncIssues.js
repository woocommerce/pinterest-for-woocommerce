/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	CheckboxControl,
	Icon,
	__experimentalText as Text,
} from '@wordpress/components';
import { TableCard } from '@woocommerce/components';

const SyncIssues = () => {
	const defaultHeaderAttributes = {
		isLeftAligned: true,
		isSortable: false,
	}
	const headers = [
		{ key: 'type', label: __( 'Type', 'pinterest-for-woocommerce' ), ...defaultHeaderAttributes },
		{ key: 'affected-product', label: __( 'Affected Product', 'pinterest-for-woocommerce' ), ...defaultHeaderAttributes },
		{ key: 'issue', label: __( 'Issue', 'pinterest-for-woocommerce' ), ...defaultHeaderAttributes },
		{ key: 'edit', ...defaultHeaderAttributes },
	];

	const rows = [
		[
			{ display: <Icon icon="warning" className="red-warning" /> },
			{ display: 'Pink marble tee' },
			{ display: 'Description is missing from product metadata.' },
			{ display: <a href="#">Edit</a> },
		],
		[
			{ display: <Icon icon="warning" className="yellow-warning" /> },
			{ display: 'Yellow slippers #3' },
			{ display: 'The condition of the item is missing. The column is optional but we encourage users to specify...' },
			{ display: <a href="#">Edit</a> },
		],
		[
			{ display: <Icon icon="warning" className="yellow-warning" /> },
			{ display: 'Yellow slippers #3' },
			{ display: 'google_product_category is invalid. The column is optional but we encourage users to follow...' },
			{ display: <a href="#">Edit</a> },
		],
		[
			{ display: <Icon icon="warning" className="red-warning" /> },
			{ display: 'Blue Bob Cake' },
			{ display: 'Your product link doesn\'t match the verified website associated with your account. Make sure...' },
			{ display: <a href="#">Edit</a> },
		],
		[
			{ display: <Icon icon="warning" className="yellow-warning" /> },
			{ display: 'Rainbow mix' },
			{ display: 'Sale price is improperly formatted or the sale price provided is higher than the original price of...' },
			{ display: <a href="#">Edit</a> },
		],
	];

	return (
		<TableCard
			title={ __( 'Issues', 'pinterest-for-woocommerce' ) }
		    rows={ rows }
		    headers={ headers }
			showMenu={ false }
		    query={ { page: 1 } }
		    rowsPerPage={ 5 }
		    totalRows={ 10 }
		/>
	)
};

export default SyncIssues;
