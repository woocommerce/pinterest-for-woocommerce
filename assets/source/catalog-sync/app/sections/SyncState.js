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

/**
 * Internal dependencies
 */
import Emoji from '../components/Emoji';

const SyncState = () => {
	const StateItem = () => {
		return (
			<div className="woocommerce-catalog-sync__state-item">
				<Text variant="muted" className="muted"><Emoji symbol="✅" label="checked" /> 31 March 2021, 07:12am</Text>
				<Text variant="subtitle">Feed pulled by Pinterest</Text>
				<Text variant="muted" className="muted">This feed contained 315 products.</Text>
			</div>
		);
	}

	return (
		<Card className="woocommerce-table">
			<CardHeader>
				<Text variant="title.small" as="h2">
					{ __( 'Feed Status', 'pinterest-for-woocommerce' ) }
				</Text>
			</CardHeader>
			<CardBody className="no-padding">
				<StateItem />
				<StateItem />
				<StateItem />
			</CardBody>
			<CardFooter className="align-items-start">
				<Button isLink>Load more</Button>
			</CardFooter>
		</Card>
	)
};

export default SyncState;
