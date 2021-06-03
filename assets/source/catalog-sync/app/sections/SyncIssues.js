/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { compose } from '@wordpress/compose';
import { withSelect } from '@wordpress/data';
import {
	Icon,
} from '@wordpress/components';
import { TableCard } from '@woocommerce/components';

/**
 * Internal dependencies
 */
import { REPORTS_STORE_NAME } from '../data';

const SyncIssues = ({ feedIssues }) => {
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

	const getRows = ( data ) => {
		const statuses = {
			success: 'green',
			warning: 'yellow',
			error: 'red',
		}

		const icons = {
			success: 'yes-alt',
			warning: 'warning',
			error: 'warning'
		}

		return data.map( row => {
			return (
				[
					{ display: <Icon icon={ icons[ row.status ] } className={ `${statuses[ row.status ]}-text` } /> },
					{ display: row.product_name },
					{ display: row.issue_description },
					{ display: <a href={row.product_edit_link} target="_blank">{ __( 'Edit', 'pinterest-for-woocommerce' ) }</a> },
				]
			);
		});
	}

	return (
		<TableCard
			className="pin4wc-catalog-sync__issues"
			title={ __( 'Issues', 'pinterest-for-woocommerce' ) }
		    rows={ feedIssues && getRows( feedIssues?.lines ) }
		    headers={ headers }
			showMenu={ false }
		    query={ { page: 1 } }
		    rowsPerPage={ 5 }
		    totalRows={ 10 }
			isLoading={ ! feedIssues }
		/>
	)
};

export default compose(
	withSelect( ( select ) => {
		const { getFeedIssues } = select( REPORTS_STORE_NAME );

		return {
			feedIssues: getFeedIssues()
		}
	})
)(SyncIssues);
