const SEVERITY_COLORS = {
	critical: '#d63638',
	warning: '#dba617',
	info: '#2271b1',
};

const SEVERITY_LABELS = {
	critical: 'Critical',
	warning: 'Warning',
	info: 'Info',
};

export default function GapCard( { gap } ) {
	const color = SEVERITY_COLORS[ gap.severity ] || '#666';
	const label = SEVERITY_LABELS[ gap.severity ] || gap.severity;

	return (
		<div className="adbot-gap-card" style={ { borderLeftColor: color } }>
			<div className="adbot-gap-card__header">
				<span
					className="adbot-gap-card__severity"
					style={ { backgroundColor: color } }
				>
					{ label }
				</span>
				<h4>{ gap.title }</h4>
			</div>
			<p className="adbot-gap-card__description">{ gap.description }</p>
			<p className="adbot-gap-card__recommendation">
				<strong>Recommendation:</strong> { gap.recommendation }
			</p>
		</div>
	);
}
