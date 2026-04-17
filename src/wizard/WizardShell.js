import { useOnboarding } from './OnboardingProvider';
import StepWelcome from './steps/StepWelcome';
import StepConnect from './steps/StepConnect';
import StepProperty from './steps/StepProperty';
import StepAudit from './steps/StepAudit';
import StepReport from './steps/StepReport';
import StepPay from './steps/StepPay';
import StepApply from './steps/StepApply';

const STEP_LABELS = [
	[ 'welcome', 'Welcome' ],
	[ 'connect', 'Connect Google' ],
	[ 'property', 'Find property' ],
	[ 'audit', 'Audit tracking' ],
	[ 'report', 'Review report' ],
	[ 'pay', 'Unlock fixes' ],
	[ 'apply', 'Apply fixes' ],
];

const COMPONENTS = {
	welcome: StepWelcome,
	connect: StepConnect,
	property: StepProperty,
	audit: StepAudit,
	report: StepReport,
	pay: StepPay,
	apply: StepApply,
};

export default function WizardShell() {
	const { state, loading, skipped, skip, back, canGoBack } = useOnboarding();

	if ( loading || ! state ) {
		return null;
	}

	if ( state.step === 'done' || skipped ) {
		return null;
	}

	const StepComponent = COMPONENTS[ state.step ] || StepWelcome;
	const currentIdx = STEP_LABELS.findIndex( ( [ k ] ) => k === state.step );

	return (
		<div className="adbot-wizard">
			<div className="adbot-wizard__backdrop" />
			<div className="adbot-wizard__frame">
				<aside className="adbot-wizard__rail">
					<div className="adbot-wizard__brand">
						<span className="adbot-wizard__brand-dot" />
						<span>Adbot</span>
					</div>
					<ol className="adbot-wizard__steps">
						{ STEP_LABELS.map( ( [ key, label ], i ) => {
							const isDone = state.completedSteps?.includes( key ) || i < currentIdx;
							const isActive = i === currentIdx;
							return (
								<li
									key={ key }
									className={
										'adbot-wizard__step' +
										( isActive ? ' is-active' : '' ) +
										( isDone ? ' is-done' : '' )
									}
								>
									<span className="adbot-wizard__step-bullet">
										{ isDone && ! isActive ? '✓' : i + 1 }
									</span>
									<span>{ label }</span>
								</li>
							);
						} ) }
					</ol>
					<button
						type="button"
						className="adbot-wizard__skip"
						onClick={ skip }
					>
						Skip to dashboard
					</button>
				</aside>

				<main className="adbot-wizard__stage">
					<div className="adbot-wizard__topbar">
						{ canGoBack ? (
							<button
								type="button"
								className="adbot-wizard__back"
								onClick={ back }
							>
								← Back
							</button>
						) : (
							<span />
						) }
					</div>
					<StepComponent />
				</main>
			</div>
		</div>
	);
}
