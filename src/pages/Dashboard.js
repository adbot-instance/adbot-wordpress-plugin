import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { useNavigate } from 'react-router-dom';
import StatusCard from '../components/StatusCard';
import GoogleSignInButton from '../components/GoogleSignInButton';
import OnboardingChecklist from '../components/OnboardingChecklist';
import { getStatus } from '../api/settings';
import { initiateOAuth } from '../api/auth';

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
			const { url } = await initiateOAuth();
			const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );
			if ( ! popup ) {
				window.location.href = url;
			}
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
