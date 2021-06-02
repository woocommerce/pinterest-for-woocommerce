/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Icon,
} from '@wordpress/components';
import { Table, TablePlaceholder } from '@woocommerce/components';

const SyncStateTable = ({ workflow }) => {
	const defaultHeaderAttributes = {
		isLeftAligned: true,
		isSortable: false,
	}

	const headers = [
		{ key: 'property', label: __( 'Property', 'pinterest-for-woocommerce' ), ...defaultHeaderAttributes },
		{ key: 'state', label: __( 'State', 'pinterest-for-woocommerce' ),...defaultHeaderAttributes },
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
					{ display: `${row.label}:` },
					{ display: (
						<>
							<span className={ `${statuses[ row.status ]}-text` }>
								<Icon icon={ icons[ row.status ] } /> {row.status_label}
							</span>
							{ row.extra_info ? ` \xa0 • \xa0 ${row.extra_info }` : ''}
						</>
					 ) },
				]
			);
		});
	}

	return (
		workflow
			? (
				<Table
					rows={ getRows( workflow ) }
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
