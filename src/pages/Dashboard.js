import { useState, useEffect } from '@wordpress/element';
import { useNavigate } from 'react-router-dom';
import StatusCard from '../components/StatusCard';
import OnboardingChecklist from '../components/OnboardingChecklist';
import { getStatus } from '../api/settings';

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
	const navigate = useNavigate();

	useEffect( () => {
		setLastAudit( readLastAudit() );
		getStatus()
			.then( ( data ) => {
				setStatus( data );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		const refresh = () => setLastAudit( readLastAudit() );
		window.addEventListener( 'adbot-last-audit', refresh );
		return () => window.removeEventListener( 'adbot-last-audit', refresh );
	}, [] );

	if ( loading ) {
		return <p>Loading...</p>;
	}

	const connected = status?.connected;
	const snippetActive = status?.snippetActive;
	const containerId = status?.snippetContainerId;

	return (
		<div className="adbot-dashboard">
			<h2>Dashboard</h2>
			<p>Overview of your Adbot tracking setup.</p>

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

			<div className="adbot-dashboard__actions">
				{ ! connected && (
					<button className="button button-primary" onClick={ () => navigate( '/connect' ) }>
						Connect Google Account
					</button>
				) }
				{ connected && ! snippetActive && (
					<button className="button button-primary" onClick={ () => navigate( '/connect' ) }>
						Install GTM Snippet
					</button>
				) }
				{ connected && snippetActive && (
					<button className="button button-primary" onClick={ () => navigate( '/audit' ) }>
						Run Audit
					</button>
				) }
			</div>
		</div>
	);
}
