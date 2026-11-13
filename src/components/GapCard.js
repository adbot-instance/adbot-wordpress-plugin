import StatusBadge, { SEVERITY_TONE, toneFor } from './StatusBadge';

const SEVERITY_LABELS = {
	critical: 'Critical',
	warning: 'Needs attention',
	info: 'Suggested',
};

export default function GapCard( { gap } ) {
	const tone = toneFor( SEVERITY_TONE, gap.severity );
	const label = SEVERITY_LABELS[ gap.severity ] || gap.severity;

	return (
		<div className={ `adbot-gap-card adbot-gap-card--${ tone }` }>
			<div className="adbot-gap-card__header">
				<StatusBadge tone={ tone }>{ label }</StatusBadge>
				<h4>{ gap.title }</h4>
			</div>
			<p className="adbot-gap-card__description">{ gap.description }</p>
			<p className="adbot-gap-card__recommendation">
				<strong>Recommendation:</strong> { gap.recommendation }
			</p>
		</div>
	);
}
