import { render } from '@wordpress/element';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Dashboard from './pages/Dashboard';
import Connect from './pages/Connect';
import Audit from './pages/Audit';
import Setup from './pages/Setup';
import Settings from './pages/Settings';
import './index.scss';

const root = document.getElementById( 'adbot-admin-root' );

if ( root ) {
	render(
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
		</HashRouter>,
		root
	);
}
