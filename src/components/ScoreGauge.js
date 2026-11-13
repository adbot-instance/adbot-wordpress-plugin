import { scoreTone } from './StatusBadge';

// Stroke and number colour come from the shared status tone tokens
// (--adbot-success / --adbot-warning / --adbot-danger), so the gauge matches
// status pills here and in the other Adbot products.
const TONE_VAR = {
	success: 'var(--adbot-success-dot)',
	warning: 'var(--adbot-warning-dot)',
	danger: 'var(--adbot-danger-dot)',
};

export default function ScoreGauge( { score } ) {
	const color = TONE_VAR[ scoreTone( score ) ];

	const circumference = 2 * Math.PI * 45;
	const offset = circumference - ( score / 100 ) * circumference;

	return (
		<div className="adbot-score-gauge">
			<svg width="120" height="120" viewBox="0 0 120 120">
				<circle
					cx="60"
					cy="60"
					r="45"
					fill="none"
					stroke="var(--adbot-border)"
					strokeWidth="10"
				/>
				<circle
					cx="60"
					cy="60"
					r="45"
					fill="none"
					stroke={ color }
					strokeWidth="10"
					strokeDasharray={ circumference }
					strokeDashoffset={ offset }
					strokeLinecap="round"
					transform="rotate(-90 60 60)"
				/>
				<text
					x="60"
					y="60"
					textAnchor="middle"
					dominantBaseline="central"
					fontSize="28"
					fontWeight="bold"
					fill="var(--adbot-ink)"
				>
					{ score }
				</text>
			</svg>
			<p className="adbot-score-gauge__label">Health score</p>
		</div>
	);
}
