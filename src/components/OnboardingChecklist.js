import { useOnboarding } from '../wizard/OnboardingProvider';

const STEP_LABELS = [
	[ 'connect', 'Connect Google account' ],
	[ 'property', 'Pick GTM container' ],
	[ 'audit', 'Run tracking audit' ],
	[ 'report', 'Review report' ],
	[ 'pay', 'Unlock fixes' ],
	[ 'apply', 'Apply fixes' ],
];

export default function OnboardingChecklist() {
	const { state, resume } = useOnboarding();

	if ( ! state || state.step === 'done' ) return null;

	const currentIdx = STEP_LABELS.findIndex( ( [ k ] ) => k === state.step );

	return (
		<div className="adbot-checklist">
			<div className="adbot-checklist__header">
				<strong>Finish your tracking setup</strong>
				<button type="button" className="adbot-btn adbot-btn--primary" onClick={ resume }>
					Resume setup
				</button>
			</div>
			<ol className="adbot-checklist__steps">
				{ STEP_LABELS.map( ( [ key, label ], i ) => {
					const done = state.completedSteps?.includes( key ) || i < currentIdx;
					const active = i === currentIdx;
					return (
						<li
							key={ key }
							className={
								'adbot-checklist__step' +
								( done ? ' is-done' : '' ) +
								( active ? ' is-active' : '' )
							}
						>
							<span className="adbot-checklist__bullet">{ done ? '✓' : i + 1 }</span>
							{ label }
						</li>
					);
				} ) }
			</ol>
		</div>
	);
}
