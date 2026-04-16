export default function ScoreGauge( { score } ) {
	const color = score >= 80 ? '#00a32a'
		: score >= 50 ? '#dba617'
		: '#d63638';

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
					stroke="#e0e0e0"
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
					fill={ color }
				>
					{ score }
				</text>
			</svg>
			<p className="adbot-score-gauge__label">Health Score</p>
		</div>
	);
}
