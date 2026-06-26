import { useOnboarding } from './OnboardingProvider';
import { __ } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import StepWelcome from './steps/StepWelcome';
import StepConnect from './steps/StepConnect';
import StepProperty from './steps/StepProperty';
import StepGA4 from './steps/StepGA4';
import StepAudit from './steps/StepAudit';
import StepReport from './steps/StepReport';
import StepPay from './steps/StepPay';
import StepApply from './steps/StepApply';

const STEP_LABELS = [
	[ 'welcome', __( 'Welcome', 'adbot' ) ],
	[ 'connect', __( 'Connect Google', 'adbot' ) ],
	[ 'property', __( 'Choose container', 'adbot' ) ],
	[ 'ga4', __( 'Select GA4 property', 'adbot' ) ],
	[ 'audit', __( 'Audit tracking', 'adbot' ) ],
	[ 'report', __( 'Review report', 'adbot' ) ],
	[ 'pay', __( 'Unlock fixes', 'adbot' ) ],
	[ 'apply', __( 'Apply fixes', 'adbot' ) ],
];

function getWizardBrand() {
	if ( typeof window === 'undefined' ) {
		return {
			logoUrl: '',
			setupTitle: __( 'Tracking setup', 'adbot' ),
			tagline: '',
		};
	}
	const admin = window.adbotAdmin;
	if ( ! admin?.brand ) {
		return {
			logoUrl: admin?.images?.adbot || '',
			setupTitle: __( 'Tracking setup', 'adbot' ),
			tagline: '',
		};
	}
	const b = admin.brand;
	return {
		logoUrl: b.logoUrl || admin.images?.adbot || '',
		setupTitle: b.setupTitle || __( 'Tracking setup', 'adbot' ),
		tagline: b.tagline || '',
	};
}

const COMPONENTS = {
	welcome: StepWelcome,
	connect: StepConnect,
	property: StepProperty,
	ga4: StepGA4,
	audit: StepAudit,
	report: StepReport,
	pay: StepPay,
	apply: StepApply,
};

export default function WizardShell() {
	const { state, loading, skipped, skip, back, canGoBack } = useOnboarding();
	const frameRef = useRef( null );
	const step = state?.step;

	useEffect( () => {
		if ( loading || ! state || step === 'done' || skipped ) {
			return;
		}

		if ( frameRef.current ) {
			const focusable = frameRef.current.querySelector(
				'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
			);
			if ( focusable ) {
				focusable.focus();
			}
		}
	}, [ loading, skipped, state, step ] );

	if ( loading || ! state ) {
		return null;
	}

	if ( state.step === 'done' || skipped ) {
		return null;
	}

	const StepComponent = COMPONENTS[ state.step ] || StepWelcome;
	const currentIdx = STEP_LABELS.findIndex( ( [ k ] ) => k === state.step );
	const titleId = 'adbot-wizard-title';
	const wizardBrand = getWizardBrand();

	return (
		<div className="adbot-wizard">
			<div className="adbot-wizard__backdrop" />
			<div
				className="adbot-wizard__frame"
				role="dialog"
				aria-modal="true"
				aria-labelledby={ titleId }
				ref={ frameRef }
			>
				<aside className="adbot-wizard__rail">
					<div className="adbot-wizard__brand">
						{ wizardBrand.logoUrl ? (
							<img
								src={ wizardBrand.logoUrl }
								alt=""
								className="adbot-wizard__brand-logo"
								width={ 44 }
								height={ 44 }
							/>
						) : (
							<span className="adbot-wizard__brand-dot" aria-hidden="true" />
						) }
						<div className="adbot-wizard__brand-text">
							<span id={ titleId } className="adbot-wizard__brand-title">
								{ wizardBrand.setupTitle }
							</span>
							{ wizardBrand.tagline ? (
								<span className="adbot-wizard__brand-tagline">{ wizardBrand.tagline }</span>
							) : null }
						</div>
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
									aria-current={ isActive ? 'step' : undefined }
								>
									<span className="adbot-wizard__step-bullet">
										<span aria-hidden="true">
											{ isDone && ! isActive ? '✓' : i + 1 }
										</span>
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
						{ __( 'Skip to dashboard', 'adbot' ) }
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
								<span aria-hidden="true">←</span> { __( 'Back', 'adbot' ) }
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
