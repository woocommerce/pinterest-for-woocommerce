/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { Icon } from '@wordpress/components';
import { Table, TablePlaceholder } from '@woocommerce/components';
import DOMPurify from 'dompurify';

const SyncStateTable = ( { workflow } ) => {
	const defaultHeaderAttributes = {
		isLeftAligned: true,
		isSortable: false,
	};

	const headers = [
		{
			key: 'property',
			label: __( 'Property', 'pinterest-for-woocommerce' ),
			...defaultHeaderAttributes,
		},
		{
			key: 'state',
			label: __( 'State', 'pinterest-for-woocommerce' ),
			...defaultHeaderAttributes,
		},
	];

	const getRows = ( data ) => {
		const statuses = {
			success: 'success',
			pending: 'warning',
			warning: 'warning',
			error: 'error',
		};

		const icons = {
			success: 'yes-alt',
			pending: 'clock',
			warning: 'warning',
			error: 'warning',
		};

		return data.map( ( row ) => {
			return [
				{ display: `${ row.label }:` },
				{
					display: (
						<>
							<span
								className={ `${ statuses[ row.status ] }-text` }
							>
								<Icon icon={ icons[ row.status ] } />{ ' ' }
								{ row.status_label }
							</span>
							{ row.extra_info ? (
								<>
									{ ` \xa0 • \xa0 ` }
									{ DOMPurify.isSupported ? (
										<span
											dangerouslySetInnerHTML={ {
												__html: DOMPurify.sanitize(
													row.extra_info,
													{
														ALLOWED_TAGS: [ 'a' ],
														ALLOWED_ATTR: [
															'href',
															'target',
															'rel',
														],
														ALLOW_DATA_ATTR: false,
														ALLOW_ARIA_ATTR: false,
													}
												),
											} }
										/>
									) : (
										<span>
											{ decodeEntities( row.extra_info ) }
										</span>
									) }
								</>
							) : (
								''
							) }
						</>
					),
				},
			];
		} );
	};

	return workflow ? (
		<Table
			rows={ getRows( workflow ) }
			headers={ headers }
			showMenu={ false }
		/>
	) : (
		<TablePlaceholder headers={ headers } numberOfRows={ 3 } caption="" />
	);
};

export default SyncStateTable;
