import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useNavigate } from 'react-router-dom';
import StatusCard from '../components/StatusCard';
import GoogleSignInButton from '../components/GoogleSignInButton';
import OnboardingChecklist from '../components/OnboardingChecklist';
import { getStatus } from '../api/settings';
import { openOAuthPopup } from '../lib/oauthPopup';

function readLastAudit() {
	try {
		const raw = localStorage.getItem( 'adbot_last_audit' );
		if ( ! raw ) {
			return null;
		}
		return JSON.parse( raw );
	} catch ( e ) {
		return null;
	}
}

function shortDate( iso ) {
	const date = new Date( iso );
	return Number.isNaN( date.getTime() ) ? '' : date.toLocaleDateString();
}

function describeSearchConsole( status ) {
	const site = status?.gscSiteUrl;
	const submittedAt = status?.gscSitemapSubmittedAt;

	if ( submittedAt ) {
		return {
			value: 'Sitemap sent',
			status: 'success',
			description: `${ site } · submitted ${ shortDate( submittedAt ) }`,
		};
	}
	if ( site ) {
		return {
			value: 'Sitemap pending',
			status: 'warning',
			description: `${ site } · submit your sitemap from the Channels tab.`,
		};
	}
	return {
		value: 'Not connected',
		status: 'neutral',
		description:
			'Connect Search Console from the Channels tab to send your sitemap to Google.',
	};
}

function describeMerchantCenter( status ) {
	if ( ! status?.wooActive ) {
		// Not an error: most sites running this plugin are not stores.
		return {
			value: 'No store',
			status: 'neutral',
			description: 'Activate WooCommerce to publish products to Google Shopping.',
		};
	}
	if ( status?.gmcFeedRegisteredAt ) {
		return {
			value: 'Feed live',
			status: 'success',
			description: `Google fetches your product feed daily · registered ${ shortDate(
				status.gmcFeedRegisteredAt
			) }`,
		};
	}
	if ( status?.gmcAccountName ) {
		return {
			value: 'Feed pending',
			status: 'warning',
			description: 'Register your product feed from the Channels tab.',
		};
	}
	return {
		value: 'Not connected',
		status: 'neutral',
		description:
			'Connect Merchant Center from the Channels tab to publish your products.',
	};
}

export default function Dashboard() {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ lastAudit, setLastAudit ] = useState( null );
	const [ connecting, setConnecting ] = useState( false );
	const navigate = useNavigate();

	const refreshStatus = useCallback( () => {
		return getStatus()
			.then( ( data ) => {
				setStatus( data );
				return data;
			} )
			.catch( () => null );
	}, [] );

	useEffect( () => {
		setLastAudit( readLastAudit() );
		refreshStatus().finally( () => setLoading( false ) );
	}, [ refreshStatus ] );

	useEffect( () => {
		const refresh = () => setLastAudit( readLastAudit() );
		window.addEventListener( 'adbot-last-audit', refresh );
		return () => window.removeEventListener( 'adbot-last-audit', refresh );
	}, [] );

	useEffect( () => {
		const onFocus = () => {
			setTimeout( () => refreshStatus(), 800 );
		};
		window.addEventListener( 'focus', onFocus );
		return () => window.removeEventListener( 'focus', onFocus );
	}, [ refreshStatus ] );

	const handleConnectGoogle = async () => {
		setConnecting( true );
		try {
			await openOAuthPopup();
		} catch ( e ) {
			// Error surfaces on next status poll / Connect wizard.
		}
		setConnecting( false );
	};

	if ( loading ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}

	const connected = status?.connected;
	const snippetActive = status?.snippetActive;
	const containerId = status?.snippetContainerId;
	// Channel tiles. Values are resolved up front rather than inline: the
	// three-way state (not connected / pending / live) reads far better as a
	// short ladder than as a nested ternary inside JSX.
	const gsc = describeSearchConsole( status );
	const gmc = describeMerchantCenter( status );

	return (
		<div className="adbot-dashboard">
			<div className="adbot-dashboard__header">
				<div className="adbot-dashboard__intro">
					<h2 className="adbot-dashboard__heading">{ __( 'Dashboard', 'adbot' ) }</h2>
					<p className="adbot-dashboard__lede">
						{ __( 'Overview of your tracking setup.', 'adbot' ) }
					</p>
				</div>
				{ ! connected && (
					<div className="adbot-dashboard__header-actions">
						<GoogleSignInButton
							onClick={ handleConnectGoogle }
							loading={ connecting }
						/>
					</div>
				) }
			</div>

			<OnboardingChecklist />

			<div className="adbot-dashboard__cards">
				<StatusCard
					title="Google Account"
					value={ connected ? 'Connected' : 'Not Connected' }
					status={ connected ? 'success' : 'error' }
					description={
						connected
							? `Signed in as ${ status.account?.email || 'Unknown' }`
							: 'Connect your Google account to get started.'
					}
				/>

				<StatusCard
					title="GTM Snippet"
					value={ snippetActive ? 'Active' : 'Inactive' }
					status={ snippetActive ? 'success' : 'warning' }
					description={
						snippetActive
							? `Container ${ containerId } is injected on your site.`
							: 'No GTM snippet installed yet.'
					}
				/>

				<StatusCard
					title="Last Audit Score"
					value={
						lastAudit?.score != null
							? String( lastAudit.score )
							: '—'
					}
					status={
						lastAudit?.score == null
							? 'neutral'
							: lastAudit.score >= 80
								? 'success'
								: lastAudit.score >= 50
									? 'warning'
									: 'error'
					}
					description={
						lastAudit?.at
							? `Container ${ lastAudit.containerId || 'unknown' } · ${ new Date( lastAudit.at ).toLocaleString() }`
							: 'Run an audit from the Audit tab to see your tracking health score here.'
					}
				/>

				<StatusCard
					title="Search Console"
					value={ gsc.value }
					status={ gsc.status }
					description={ gsc.description }
				/>

				<StatusCard
					title="Merchant Center"
					value={ gmc.value }
					status={ gmc.status }
					description={ gmc.description }
				/>

				<StatusCard
					title="Plugin Version"
					value={ status?.pluginVersion || '1.0.0' }
					status="neutral"
					description={ `WordPress ${ status?.wpVersion || '' }` }
				/>
			</div>

			{ connected && (
				<div className="adbot-dashboard__actions">
					{ ! snippetActive ? (
						<button
							type="button"
							className="button button-primary"
							onClick={ () => navigate( '/connect' ) }
						>
							{ __( 'Install GTM Snippet', 'adbot' ) }
						</button>
					) : (
						<button
							type="button"
							className="button button-primary"
							onClick={ () => navigate( '/audit' ) }
						>
							{ __( 'Run Audit', 'adbot' ) }
						</button>
					) }
				</div>
			) }
		</div>
	);
}
