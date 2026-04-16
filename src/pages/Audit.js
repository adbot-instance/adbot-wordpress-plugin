import { useState, useEffect } from '@wordpress/element';
import { runAudit } from '../api/audit';
import { getStatus } from '../api/settings';
import GapCard from '../components/GapCard';
import ScoreGauge from '../components/ScoreGauge';
import ContainerSelector from '../components/ContainerSelector';

export default function Audit() {
	const [ status, setStatus ] = useState( null );
	const [ auditResult, setAuditResult ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ auditing, setAuditing ] = useState( false );
	const [ selectedContainer, setSelectedContainer ] = useState( null );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		getStatus()
			.then( ( data ) => {
				setStatus( data );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	const handleRunAudit = async ( containerPath, containerMeta = null ) => {
		setAuditing( true );
		setError( null );
		const meta = containerMeta ?? selectedContainer;
		try {
			const result = await runAudit( containerPath );
			setAuditResult( result );
			try {
				localStorage.setItem(
					'adbot_last_audit',
					JSON.stringify( {
						score: result.score,
						containerId: meta?.containerId || '',
						at: new Date().toISOString(),
					} )
				);
				window.dispatchEvent( new CustomEvent( 'adbot-last-audit' ) );
			} catch ( e ) {
				// Storage may be unavailable.
			}
		} catch ( err ) {
			setError( err.message || 'Audit failed' );
		}
		setAuditing( false );
	};

	if ( loading ) {
		return <p>Loading...</p>;
	}

	if ( ! status?.connected ) {
		return (
			<div className="adbot-audit">
				<h2>Tracking Audit</h2>
				<p>Connect your Google account first to run an audit.</p>
			</div>
		);
	}

	return (
		<div className="adbot-audit">
			<h2>Tracking Audit</h2>
			<p>Audit your GTM container for tracking gaps and best-practice issues.</p>

			{ ! selectedContainer && (
				<div className="adbot-audit__select">
					<h3>Select a container to audit</h3>
					<ContainerSelector
						onSelect={ ( container ) => {
							setSelectedContainer( container );
							handleRunAudit( container.path, container );
						} }
						selectedPath={ selectedContainer?.path }
					/>
				</div>
			) }

			{ auditing && (
				<div className="adbot-audit__loading">
					<p>Running audit on <strong>{ selectedContainer?.name }</strong>... This may take a moment.</p>
				</div>
			) }

			{ error && (
				<div className="adbot-audit__error">
					<p className="adbot-error">{ error }</p>
					<button className="button" onClick={ () => {
						setSelectedContainer( null );
						setError( null );
					} }>
						Try Again
					</button>
				</div>
			) }

			{ auditResult && ! auditing && (
				<div className="adbot-audit__results">
					<div className="adbot-audit__summary">
						<ScoreGauge score={ auditResult.score } />
						<div className="adbot-audit__meta">
							<p><strong>Container:</strong> { selectedContainer?.name } ({ selectedContainer?.containerId })</p>
							{ auditResult.measurementId && (
								<p><strong>GA4 Measurement ID:</strong> { auditResult.measurementId }</p>
							) }
							<p><strong>Tags:</strong> { auditResult.tags?.length || 0 }</p>
							<p><strong>Triggers:</strong> { auditResult.triggers?.length || 0 }</p>
							<p>
								<strong>Snippet:</strong>{ ' ' }
								{ auditResult.snippetInstalled ? 'Installed' : 'Not detected' }
							</p>
						</div>
					</div>

					<h3>Issues Found ({ auditResult.gaps?.length || 0 })</h3>

					{ auditResult.gaps?.length === 0 && (
						<p className="adbot-success">No issues found. Your tracking setup looks great!</p>
					) }

					{ auditResult.gaps?.map( ( gap ) => (
						<GapCard key={ gap.id } gap={ gap } />
					) ) }

					{ auditResult.gaps?.length > 0 && (
						<div className="adbot-audit__actions">
							<button
								className="button button-primary"
								onClick={ () => {
									// Store audit result for the setup page.
									sessionStorage.setItem( 'adbot_audit', JSON.stringify( {
										...auditResult,
										containerPath: selectedContainer?.path,
										containerId: selectedContainer?.containerId,
									} ) );
									window.location.hash = '#/setup';
								} }
							>
								Fix Issues Automatically
							</button>
						</div>
					) }

					<button className="button" onClick={ () => handleRunAudit( selectedContainer.path ) }>
						Re-run Audit
					</button>
				</div>
			) }
		</div>
	);
}
