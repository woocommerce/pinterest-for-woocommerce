/**
 * External dependencies
 */
import { render, useEffect, useState } from '@wordpress/element';
import { getHistory, getQuery } from '@woocommerce/navigation';

/**
 * Internal dependencies
 */
import LandingPageApp from './app/views/LandingPageApp';
import SettingsApp from './app/views/SettingsApp';
import WizardApp from './app/views/WizardApp';
import './app/style.scss';

const App = () => {
	const [view, setView] = useState(getQuery()?.view);

	useEffect(() => {
		const currentUrl = new URL(window.location.href);
		const currentView = currentUrl.searchParams.get('view');

		setView(currentView);
	});

	getHistory().listen(() => {
		setView(getQuery()?.view);
	});

	return view === 'settings' ||
		getQuery()?.page === 'pinterest-for-woocommerce-setup-guide' ? (
		<SettingsApp />
	) : view === 'wizard' ? (
		<WizardApp />
	) : (
		<LandingPageApp />
	);
};

const appRoot = document.getElementById('pin4wc-setup-guide');

if (appRoot) {
	render(<App />, appRoot);
}

export default App;
