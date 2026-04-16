import { useState, useEffect } from '@wordpress/element';
import { generatePlan, executePlan, publishChanges } from '../api/setup';

export default function Setup() {
	const [ auditData, setAuditData ] = useState( null );
	const [ plan, setPlan ] = useState( null );
	const [ results, setResults ] = useState( null );
	const [ published, setPublished ] = useState( false );
	const [ loading, setLoading ] = useState( false );
	const [ step, setStep ] = useState( 'plan' ); // plan | execute | publish | done
	const [ error, setError ] = useState( null );

	useEffect( () => {
		const stored = sessionStorage.getItem( 'adbot_audit' );
		if ( stored ) {
			try {
				setAuditData( JSON.parse( stored ) );
			} catch ( e ) {
				// Ignore parse errors.
			}
		}
	}, [] );

	const handleGeneratePlan = async () => {
		if ( ! auditData ) return;
		setLoading( true );
		setError( null );
		try {
			const result = await generatePlan( auditData.gaps, auditData.measurementId );
			setPlan( result.operations );
			setStep( 'execute' );
		} catch ( err ) {
			setError( err.message || 'Failed to generate plan' );
		}
		setLoading( false );
	};

	const handleExecute = async () => {
		if ( ! plan || ! auditData ) return;
		setLoading( true );
		setError( null );
		try {
			const result = await executePlan(
				auditData.containerPath,
				auditData.workspacePath,
				plan
			);
			setResults( result.results );
			setStep( 'publish' );
		} catch ( err ) {
			setError( err.message || 'Execution failed' );
		}
		setLoading( false );
	};

	const handlePublish = async () => {
		if ( ! auditData ) return;
		setLoading( true );
		setError( null );
		try {
			await publishChanges( auditData.containerPath, auditData.workspacePath );
			setPublished( true );
			setStep( 'done' );
			sessionStorage.removeItem( 'adbot_audit' );
		} catch ( err ) {
			setError( err.message || 'Publish failed' );
		}
		setLoading( false );
	};

	if ( ! auditData ) {
		return (
			<div className="adbot-setup">
				<h2>Fix Issues</h2>
				<p>Run an audit first to identify issues that can be fixed automatically.</p>
				<button className="button button-primary" onClick={ () => { window.location.hash = '#/audit'; } }>
					Go to Audit
				</button>
			</div>
		);
	}

	return (
		<div className="adbot-setup">
			<h2>Fix Issues</h2>
			<p>
				Container: <strong>{ auditData.containerId }</strong>
				{ auditData.measurementId && <> | GA4: <strong>{ auditData.measurementId }</strong></> }
			</p>

			{ error && <p className="adbot-error">{ error }</p> }

			{ step === 'plan' && (
				<div className="adbot-setup__step">
					<h3>Step 1: Generate Fix Plan</h3>
					<p>We found { auditData.gaps?.length || 0 } issues. Generate a plan to fix them.</p>
					<ul>
						{ auditData.gaps?.map( ( gap ) => (
							<li key={ gap.id }>
								<strong>{ gap.title }</strong> — { gap.severity }
							</li>
						) ) }
					</ul>
					<button
						className="button button-primary"
						onClick={ handleGeneratePlan }
						disabled={ loading }
					>
						{ loading ? 'Generating...' : 'Generate Plan' }
					</button>
				</div>
			) }

			{ step === 'execute' && plan && (
				<div className="adbot-setup__step">
					<h3>Step 2: Review & Execute</h3>
					<p>The following changes will be made to your GTM container:</p>
					<ul>
						{ plan.map( ( op ) => (
							<li key={ op.id }>
								<strong>{ op.name }</strong> — { op.description }
							</li>
						) ) }
					</ul>
					<button
						className="button button-primary"
						onClick={ handleExecute }
						disabled={ loading }
					>
						{ loading ? 'Executing...' : 'Execute Plan' }
					</button>
				</div>
			) }

			{ step === 'publish' && results && (
				<div className="adbot-setup__step">
					<h3>Step 3: Publish</h3>
					<p>Execution results:</p>
					<ul>
						{ results.map( ( r ) => (
							<li key={ r.operationId } className={ r.success ? 'adbot-success' : 'adbot-error' }>
								{ r.name } — { r.success ? ( r.action || 'done' ) : r.error }
							</li>
						) ) }
					</ul>
					<button
						className="button button-primary"
						onClick={ handlePublish }
						disabled={ loading }
					>
						{ loading ? 'Publishing...' : 'Publish to Live' }
					</button>
				</div>
			) }

			{ step === 'done' && (
				<div className="adbot-setup__step">
					<h3>Done!</h3>
					<p className="adbot-success">
						Changes have been published to your live GTM container.
					</p>
					<button className="button" onClick={ () => { window.location.hash = '#/audit'; } }>
						Re-run Audit to Verify
					</button>
				</div>
			) }
		</div>
	);
}
