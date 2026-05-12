import { useEffect, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { summarizeForGate } from '../gateAudit';
import ScoreGauge from '../../components/ScoreGauge';

const SEVERITY_META = [
	[ 'critical', 'Critical', '#d63638' ],
	[ 'high', 'High', '#dba617' ],
	[ 'medium', 'Medium', '#ff6900' ],
	[ 'low', 'Low', '#50575e' ],
];

export default function StepReport() {
	const { advance } = useOnboarding();
	const [ summary, setSummary ] = useState( null );

	useEffect( () => {
		try {
			const raw = sessionStorage.getItem( 'adbot_audit' );
			if ( raw ) setSummary( summarizeForGate( JSON.parse( raw ) ) );
		} catch ( e ) {
			setSummary( null );
		}
	}, [] );

	const price = window.adbotAdmin?.fixPrice || 0;
	const currency = window.adbotAdmin?.currency || 'ZAR';

	if ( ! summary ) {
		return (
			<div className="adbot-step">
				<h1 className="adbot-step__title">No audit found</h1>
				<p>Re-run the audit to see your health report.</p>
				<button
					type="button"
					className="adbot-btn adbot-btn--primary"
					onClick={ () => advance( 'audit' ) }
				>
					Re-run audit
				</button>
			</div>
		);
	}

	const categoryEntries = Object.entries( summary.categories ).sort(
		( [ , a ], [ , b ] ) => b - a
	);

	if ( summary.total === 0 ) {
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 4 of 6</div>
				<h1 className="adbot-step__title">Your tracking is in great shape 🎉</h1>
				<p className="adbot-step__lede">
					We scanned your container and found no tracking gaps. No fixes needed, and no
					payment required. You are ready to go.
				</p>

				<div className="adbot-report">
					<div className="adbot-report__score">
						<ScoreGauge score={ summary.score } />
						<div className="adbot-report__score-caption">
							0 issues · { summary.snippetInstalled ? 'Snippet live on site' : 'Snippet pending on site' }
							{ summary.measurementIdPresent ? ' · GA4 connected' : '' }
						</div>
					</div>
				</div>

				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary adbot-btn--large"
						onClick={ () =>
							advance( 'done', {
								markCompleted: [ 'audit', 'report', 'pay', 'apply' ],
							} )
						}
					>
						Finish & go to dashboard
					</button>
				</div>
			</div>
		);
	}

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 4 of 6</div>
			<h1 className="adbot-step__title">Your tracking health report</h1>

			<div className="adbot-report">
				<div className="adbot-report__score">
					<ScoreGauge score={ summary.score } />
					<div className="adbot-report__score-caption">
						{ summary.total } issues found
					</div>
				</div>

				<div className="adbot-report__grid">
					{ SEVERITY_META.map( ( [ key, label, color ] ) => (
						<div className="adbot-report__tile" key={ key } style={ { borderTopColor: color } }>
							<div className="adbot-report__tile-count">
								{ summary.bySeverity[ key ] || 0 }
							</div>
							<div className="adbot-report__tile-label">{ label }</div>
						</div>
					) ) }
				</div>

				{ categoryEntries.length > 0 && (
					<div className="adbot-report__chips">
						{ categoryEntries.map( ( [ cat, count ] ) => (
							<span key={ cat } className="adbot-chip">
								{ cat } · { count }
							</span>
						) ) }
					</div>
				) }

				<div className="adbot-report__gate">
					<div>
						<h3>Unlock the full report & auto-fix</h3>
						<p>
							Pay once and we will apply every fix directly to your GTM workspace and
							publish a new version for you.
						</p>
					</div>
					<button
						type="button"
						className="adbot-btn adbot-btn--primary adbot-btn--large"
						onClick={ () => advance( 'pay' ) }
						disabled={ summary.total === 0 }
					>
						{ summary.total === 0
							? 'Nothing to fix 🎉'
							: `Unlock & fix (${ currency } ${ price })` }
					</button>
				</div>
			</div>
		</div>
	);
}
