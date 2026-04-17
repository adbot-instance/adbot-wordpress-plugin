import { createRoot } from '@wordpress/element';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Connect from './pages/Connect';
import Audit from './pages/Audit';
import Setup from './pages/Setup';
import Settings from './pages/Settings';
import { OnboardingProvider } from './wizard/OnboardingProvider';
import WizardShell from './wizard/WizardShell';
import './index.scss';

const container = document.getElementById( 'adbot-admin-root' );

if ( container ) {
	const root = createRoot( container );
	root.render(
		<OnboardingProvider>
			<WizardShell />
			<HashRouter>
				<Routes>
					<Route path="/" element={ <Layout /> }>
						<Route index element={ <Dashboard /> } />
						<Route path="connect" element={ <Connect /> } />
						<Route path="audit" element={ <Audit /> } />
						<Route path="setup" element={ <Setup /> } />
						<Route path="settings" element={ <Settings /> } />
						<Route path="*" element={ <Navigate to="/" replace /> } />
					</Route>
				</Routes>
			</HashRouter>
		</OnboardingProvider>
	);
}
