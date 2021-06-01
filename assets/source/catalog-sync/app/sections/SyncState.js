/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	CheckboxControl,
	Icon,
	__experimentalText as Text,
} from '@wordpress/components';
import { Table } from '@woocommerce/components';

const SyncState = () => {
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
			{ display: <><span className="green-text"><Icon icon="yes-alt" /> Successfully configured</span></> },
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
		<Card className="woocommerce-table pin4wc-catalog-sync__state">
			<CardHeader>
				<Text variant="title.small" as="h2">
					{ __( 'Feed Status', 'pinterest-for-woocommerce' ) }
				</Text>
			</CardHeader>
			<CardBody className="no-padding">
				<Table
					rows={ rows }
					headers={ headers }
					showMenu={ false }
				/>
			</CardBody>
		</Card>
	)
};

export default SyncState;
