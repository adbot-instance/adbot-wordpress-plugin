import { useEffect, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { summarizeForGate, tagTypeLabel } from '../gateAudit';
import ScoreGauge from '../../components/ScoreGauge';

// Display vocabulary for the report — matches the severities the audit emits
// (critical / warning / info) after normalizeSeverity().
const SEVERITY_META = {
	critical: [ 'Critical', '#d63638' ],
	warning: [ 'Needs attention', '#dba617' ],
	info: [ 'Suggested', '#2271b1' ],
};
const SEVERITY_ORDER = [ 'critical', 'warning', 'info' ];

function SeverityBadge( { severity } ) {
	const [ label, color ] = SEVERITY_META[ severity ] || SEVERITY_META.info;
	return (
		<span className="adbot-sev-badge" style={ { color, borderColor: color } }>
			{ label }
		</span>
	);
}

function IssueList( { issues } ) {
	return (
		<ul className="adbot-issue-list">
			{ issues.map( ( issue, i ) => (
				<li className="adbot-issue-list__item" key={ issue.id || i }>
					<span className="adbot-issue-list__title">{ issue.title }</span>
					<SeverityBadge severity={ issue.severity } />
				</li>
			) ) }
		</ul>
	);
}

/**
 * Shows what already exists in the container (tags / triggers / variables) so the
 * user can confirm Adbot read their setup correctly. Renders nothing for a truly
 * empty container.
 */
function InventorySection( { inventory } ) {
	if ( ! inventory ) {
		return null;
	}
	const groups = [
		[ 'Tags', inventory.tags || [], true ],
		[ 'Triggers', inventory.triggers || [], false ],
		[ 'Variables', inventory.variables || [], false ],
	];
	const total = groups.reduce( ( n, [ , items ] ) => n + items.length, 0 );
	if ( total === 0 ) {
		return null;
	}

	return (
		<div className="adbot-inventory">
			<h3 className="adbot-report__issues-heading">
				What’s already in your container
			</h3>
			<div className="adbot-inventory__groups">
				{ groups.map( ( [ label, items, showType ] ) => (
					<div className="adbot-inventory__group" key={ label }>
						<div className="adbot-inventory__group-head">
							{ label }
							<span className="adbot-inventory__count">{ items.length }</span>
						</div>
						{ items.length > 0 ? (
							<ul className="adbot-inventory__list">
								{ items.map( ( it, i ) => (
									<li className="adbot-inventory__item" key={ it.id || i }>
										<span className="adbot-inventory__name">{ it.name }</span>
										{ showType && it.type && (
											<span className="adbot-inventory__type">
												{ tagTypeLabel( it.type ) }
											</span>
										) }
									</li>
								) ) }
							</ul>
						) : (
							<div className="adbot-inventory__empty">None yet</div>
						) }
					</div>
				) ) }
			</div>
		</div>
	);
}

export default function StepReport() {
	const { advance } = useOnboarding();
	const [ summary, setSummary ] = useState( null );

	useEffect( () => {
		try {
			const raw = sessionStorage.getItem( 'adbot_audit' );
			if ( raw ) {
				setSummary( summarizeForGate( JSON.parse( raw ) ) );
			}
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

	// Nothing to fix — celebrate and skip payment.
	if ( summary.total === 0 ) {
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 5 of 7</div>
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

					<InventorySection inventory={ summary.inventory } />
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

	// Brand-new / empty container — reframe as a setup checklist rather than a
	// scary low health score. Same paid auto-fix routes them to payment.
	if ( summary.isGreenfield ) {
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 5 of 7</div>
				<h1 className="adbot-step__title">Let’s set up your tracking</h1>
				<p className="adbot-step__lede">
					Your Tag Manager container is a clean slate. Here’s the essential tracking
					we’ll create and publish for you, so you can measure visits, leads, and sales
					from day one.
				</p>

				<div className="adbot-report">
					<div className="adbot-setup-list__heading">
						{ `We’ll set up ${ summary.total } ${ summary.total === 1 ? 'item' : 'items' }:` }
					</div>
					<ul className="adbot-setup-list">
						{ summary.issues.map( ( issue, i ) => (
							<li className="adbot-setup-list__item" key={ issue.id || i }>
								<span className="adbot-setup-list__dot" aria-hidden="true" />
								<span>{ issue.title }</span>
							</li>
						) ) }
					</ul>

					<div className="adbot-report__gate">
						<div>
							<h3>Set up my tracking</h3>
							<p>
								Pay once and we’ll create these tags directly in your GTM workspace and
								publish a new version — no manual setup needed.
							</p>
						</div>
						<button
							type="button"
							className="adbot-btn adbot-btn--primary adbot-btn--large"
							onClick={ () => advance( 'pay' ) }
						>
							{ `Set up my tracking (${ currency } ${ price })` }
						</button>
					</div>
				</div>
			</div>
		);
	}

	// Configured site with gaps — show the health report, the problem list (free),
	// and gate the details + auto-fix behind payment.
	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 5 of 7</div>
			<h1 className="adbot-step__title">Your tracking health report</h1>

			<div className="adbot-report">
				<div className="adbot-report__score">
					<ScoreGauge score={ summary.score } />
					<div className="adbot-report__score-caption">
						{ `${ summary.total } ${ summary.total === 1 ? 'issue' : 'issues' } found` }
					</div>
				</div>

				<div className="adbot-report__grid">
					{ SEVERITY_ORDER.map( ( key ) => {
						const [ label, color ] = SEVERITY_META[ key ];
						return (
							<div
								className="adbot-report__tile"
								key={ key }
								style={ { borderTopColor: color } }
							>
								<div className="adbot-report__tile-count">
									{ summary.bySeverity[ key ] || 0 }
								</div>
								<div className="adbot-report__tile-label">{ label }</div>
							</div>
						);
					} ) }
				</div>

				<div className="adbot-report__issues">
					<h3 className="adbot-report__issues-heading">What we found</h3>
					<IssueList issues={ summary.issues } />
				</div>

				<InventorySection inventory={ summary.inventory } />

				<div className="adbot-report__gate">
					<div>
						<h3>Unlock the full report & auto-fix</h3>
						<p>
							See the full details and let us apply every fix directly to your GTM
							workspace and publish a new version for you.
						</p>
					</div>
					<button
						type="button"
						className="adbot-btn adbot-btn--primary adbot-btn--large"
						onClick={ () => advance( 'pay' ) }
					>
						{ `Unlock & fix (${ currency } ${ price })` }
					</button>
				</div>
			</div>
		</div>
	);
}
