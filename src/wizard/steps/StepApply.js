import { useEffect, useRef, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { generatePlan, executePlan, publishChanges } from '../../api/setup';
import GapCard from '../../components/GapCard';

const PHASES = [ 'Planning fixes…', 'Writing to GTM…', 'Publishing new version…' ];

export default function StepApply() {
	const { advance } = useOnboarding();
	const [ phase, setPhase ] = useState( 0 );
	const [ error, setError ] = useState( null );
	const [ done, setDone ] = useState( false );
	const [ audit, setAudit ] = useState( null );
	const started = useRef( false );

	useEffect( () => {
		if ( started.current ) return;
		started.current = true;

		( async () => {
			try {
				const raw = sessionStorage.getItem( 'adbot_audit' );
				if ( ! raw ) throw new Error( 'Audit data missing. Re-run the audit.' );
				const a = JSON.parse( raw );
				setAudit( a );

				setPhase( 0 );
				const plan = await generatePlan( a.gaps, a.measurementId );

				setPhase( 1 );
				await executePlan( a.containerPath, a.workspacePath, plan.operations );

				setPhase( 2 );
				await publishChanges( a.containerPath, a.workspacePath );

				setPhase( 3 );
				setDone( true );
				sessionStorage.removeItem( 'adbot_audit' );
			} catch ( err ) {
				setError( err.message || 'Apply failed' );
			}
		} )();
	}, [] );

	const handleFinish = async () => {
		await advance( 'done', { markCompleted: 'apply' } );
	};

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 6 of 6</div>
			<h1 className="adbot-step__title">
				{ done ? 'All fixes published ✓' : 'Applying fixes to your GTM' }
			</h1>

			<ul className="adbot-audit-progress">
				{ PHASES.map( ( label, i ) => (
					<li
						key={ label }
						className={
							'adbot-audit-progress__item' +
							( i < phase || done ? ' is-done' : '' ) +
							( i === phase && ! done ? ' is-active' : '' )
						}
					>
						<span className="adbot-audit-progress__marker">
							{ i < phase || done ? '✓' : i === phase ? '•' : '' }
						</span>
						{ label }
					</li>
				) ) }
			</ul>

			{ error && <p className="adbot-error">{ error }</p> }

			{ done && audit && (
				<>
					<h3 className="adbot-step__subtitle">What was fixed</h3>
					{ audit.gaps?.map( ( gap ) => <GapCard key={ gap.id } gap={ gap } /> ) }
					<div className="adbot-step__actions">
						<button
							type="button"
							className="adbot-btn adbot-btn--primary"
							onClick={ handleFinish }
						>
							Go to dashboard
						</button>
					</div>
				</>
			) }
		</div>
	);
}
