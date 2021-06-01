/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { isNil } from 'lodash';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	CheckboxControl,
	Icon,
	__experimentalText as Text,
} from '@wordpress/components';
import { SummaryList } from '@woocommerce/components';

const SyncOverview = () => {
	const SimpleSummaryNumber = ({ children, label, value }) => {
		return (
			<li className="woocommerce-summary__item-container">
				<div class="woocommerce-summary__item">
					<div className="woocommerce-summary__item-label">
						<Text variant="body.small">{ label }</Text>
					</div>

					<div className="woocommerce-summary__item-data">
						<div className="woocommerce-summary__item-value">
							<Text variant="title.small">
								{ ! isNil( value )
									? value
									: __( 'N/A', 'woocommerce-admin' ) }
							</Text>
						</div>
					</div>

					{ children }
				</div>
			</li>
		)
	}

	return (
		<Card className="woocommerce-table">
			<CardHeader>
				<Text variant="title.small" as="h2">
					{ __( 'Overview' ) }
				</Text>
			</CardHeader>
			<CardBody className="no-padding">
			<SummaryList>
				{ () => {
					return [
						<SimpleSummaryNumber
							key="active"
							value='145'
							label={ __( 'Active', 'pinterest-for-woocommerce' ) }
						/>,
						<SimpleSummaryNumber
							key="not-synced"
							value='40'
							label={ __( 'Not Synced', 'pinterest-for-woocommerce' ) }
						/>,
						<SimpleSummaryNumber
							key="with-warnings"
							value='9'
							label={ __( 'With Warnings', 'pinterest-for-woocommerce' ) }
						/>,
						<SimpleSummaryNumber
							key="with-errors"
							value='40'
							label={ __( 'With Errors', 'pinterest-for-woocommerce' ) }
						/>,
					];
				} }
			</SummaryList>
			</CardBody>
		</Card>
	)
};

export default SyncOverview;
