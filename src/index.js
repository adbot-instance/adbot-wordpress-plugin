import { createRoot } from '@wordpress/element';
import { HashRouter, Routes, Route, Navigate } from 'react-router-dom';
import Layout from './components/Layout';
import Overview from './pages/Overview';
import Analytics from './pages/Analytics';
import TagManager, { TagManagerContainer } from './pages/TagManager';
import SearchConsole from './pages/SearchConsole';
import MerchantCenter from './pages/MerchantCenter';
import BusinessProfile from './pages/BusinessProfile';
import SettingsPage from './pages/SettingsPage';
import Connections from './pages/Connections';
import Audit from './pages/Audit';
import Setup from './pages/Setup';
import Settings from './pages/Settings';
import { OnboardingProvider } from './wizard/OnboardingProvider';
import WizardShell from './wizard/WizardShell';
import './index.scss';

/**
 * If this page load is the Google OAuth popup returning from the backend,
 * notify the window that opened it and close — rather than rendering the full
 * admin app inside the 600x700 popup. The backend appends `?adbot_oauth=success`
 * (or `?adbot_oauth=error&reason=…`) to the return URL. The opener still
 * confirms the connection by re-polling GET /auth/status, so the posted message
 * is only a nudge to poll immediately instead of waiting for the next tick.
 *
 * Returns true when it handled (and is closing) the popup, so we skip mounting.
 */
function finishOAuthPopupReturn() {
	let params;
	try {
		params = new URLSearchParams( window.location.search );
	} catch {
		return false;
	}
	const result = params.get( 'adbot_oauth' ); // 'success' | 'error' | null
	const legacyConnected = params.get( 'connected' ) === '1';
	if ( result !== 'success' && result !== 'error' && ! legacyConnected ) {
		return false;
	}
	const opener = window.opener;
	if ( ! opener || opener.closed ) {
		// Popup was blocked, so this is a full-page redirect back in the main
		// window. Let the app mount normally; StepConnect's poll detects it.
		return false;
	}
	try {
		opener.postMessage(
			{
				type: 'adbot:oauth-result',
				error:
					result === 'error'
						? params.get( 'reason' ) || 'oauth_failed'
						: null,
			},
			window.location.origin
		);
	} catch {
		// Same-origin opener; postMessage failure is non-fatal — opener still polls.
	}
	window.close();
	return true;
}

if ( ! finishOAuthPopupReturn() ) {
	const container = document.getElementById( 'adbot-admin-root' );

	if ( container ) {
		const root = createRoot( container );
		root.render(
			<OnboardingProvider>
				<WizardShell />
				<HashRouter>
					<Routes>
						<Route path="/" element={ <Layout /> }>
							{ /* One menu item per Google service, matching the
							   Shopify app: Overview, the five services, Settings. */ }
							<Route index element={ <Overview /> } />
							<Route path="analytics" element={ <Analytics /> } />
							<Route
								path="tag-manager"
								element={ <TagManager /> }
							>
								<Route
									index
									element={ <TagManagerContainer /> }
								/>
								<Route path="audit" element={ <Audit /> } />
								<Route path="fixes" element={ <Setup /> } />
							</Route>
							<Route
								path="search-console"
								element={ <SearchConsole /> }
							/>
							<Route
								path="merchant-center"
								element={ <MerchantCenter /> }
							/>
							<Route
								path="business-profile"
								element={ <BusinessProfile /> }
							/>
							<Route path="settings" element={ <SettingsPage /> }>
								<Route index element={ <Connections /> } />
								<Route
									path="general"
									element={ <Settings /> }
								/>
							</Route>
							{ /* Screens from 1.0.13 and earlier, kept so old links land. */ }
							<Route
								path="connect"
								element={ <Navigate to="/settings" replace /> }
							/>
							<Route
								path="audit"
								element={
									<Navigate to="/tag-manager/audit" replace />
								}
							/>
							<Route
								path="setup"
								element={
									<Navigate to="/tag-manager/fixes" replace />
								}
							/>
							<Route
								path="*"
								element={ <Navigate to="/" replace /> }
							/>
						</Route>
					</Routes>
				</HashRouter>
			</OnboardingProvider>
		);
	}
}
