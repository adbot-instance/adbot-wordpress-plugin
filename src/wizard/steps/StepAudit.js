import { useEffect, useRef, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { runAudit, getContainers } from '../../api/audit';
import { getStatus, installSnippet } from '../../api/settings';

const CHECKS = [
	'Reading your GTM container…',
	'Discovering the matching GA4 property…',
	'Checking the site for the GTM snippet…',
	'Analyzing tracking gaps…',
	'Scoring your setup…',
];

export default function StepAudit() {
	const { advance, setAuditId } = useOnboarding();
	const [ activeIdx, setActiveIdx ] = useState( 0 );
	const [ error, setError ] = useState( null );
	const started = useRef( false );

	useEffect( () => {
		if ( started.current ) return;
		started.current = true;

		const tick = setInterval( () => {
			setActiveIdx( ( i ) => Math.min( i + 1, CHECKS.length - 1 ) );
		}, 1400 );

		( async () => {
			try {
				const status = await getStatus();
				const containerId = status?.snippetContainerId;
				let path = status?.snippetContainerPath;

				if ( ! containerId ) {
					throw new Error( 'No GTM container selected. Go back and pick one.' );
				}

				// Backfill the path if it wasn't stored on install (pre-1.1 sites).
				if ( ! path ) {
					const accounts = await getContainers();
					accounts.forEach( ( a ) =>
						a.containers.forEach( ( c ) => {
							// containerId here is the stored GTM-XXXX public id.
							if ( c.publicId === containerId ) path = c.path;
						} )
					);
					if ( path ) {
						// Persist for next time so we don't hit this again.
						try {
							await installSnippet( containerId, path );
						} catch ( e ) {
							// Non-fatal — we still have `path` for this run.
						}
					}
				}

				if ( ! path ) {
					throw new Error(
						'Could not find the GTM container in your account. Go back and pick one.'
					);
				}

				const result = await runAudit( path );
				clearInterval( tick );
				setActiveIdx( CHECKS.length );
				localStorage.setItem(
					'adbot_last_audit',
					JSON.stringify( {
						score: result.score,
						containerId,
						at: new Date().toISOString(),
					} )
				);
				window.dispatchEvent( new CustomEvent( 'adbot-last-audit' ) );

				sessionStorage.setItem(
					'adbot_audit',
					JSON.stringify( {
						...result,
						containerPath: path,
						containerId,
					} )
				);

				if ( result.auditId ) {
					await setAuditId( result.auditId );
				}

				setTimeout( () => advance( 'report' ), 500 );
			} catch ( err ) {
				clearInterval( tick );
				// eslint-disable-next-line no-console
				console.error( 'Adbot audit error:', err );
				const msg =
					err?.message ||
					err?.error ||
					( typeof err === 'string' ? err : '' ) ||
					'Audit failed (see console for details).';
				setError( msg );
			}
		} )();

		return () => clearInterval( tick );
	}, [ advance, setAuditId ] );

	if ( error ) {
		return (
			<div className="adbot-step">
				<h1 className="adbot-step__title">Audit hit a snag</h1>
				<p className="adbot-error">{ error }</p>
				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ () => window.location.reload() }
					>
						Retry audit
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 3 of 6</div>
			<h1 className="adbot-step__title">Running your tracking audit</h1>
			<p className="adbot-step__lede">
				This usually takes 10–20 seconds. Hang tight.
			</p>
			<ul className="adbot-audit-progress">
				{ CHECKS.map( ( label, i ) => (
					<li
						key={ label }
						className={
							'adbot-audit-progress__item' +
							( i < activeIdx ? ' is-done' : '' ) +
							( i === activeIdx ? ' is-active' : '' )
						}
					>
						<span className="adbot-audit-progress__marker">
							{ i < activeIdx ? '✓' : i === activeIdx ? '•' : '' }
						</span>
						{ label }
					</li>
				) ) }
			</ul>
		</div>
	);
}
