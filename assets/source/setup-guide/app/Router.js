/**
 * WordPress dependencies
 */
import {
	getQuery,
} from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import WizardApp from './views/WizardApp';
import SettingsApp from './views/SettingsApp';

import './style.scss';

const Router = () => {
	const query = getQuery();

	return 'wizard' === query?.view ? <WizardApp /> : <SettingsApp />;
};

export default Router;
