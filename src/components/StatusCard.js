export default function StatusCard( { title, value, status, description } ) {
	const statusClass = status === 'success' ? 'adbot-status--success'
		: status === 'warning' ? 'adbot-status--warning'
		: status === 'error' ? 'adbot-status--error'
		: 'adbot-status--neutral';

	return (
		<div className={ `adbot-status-card ${ statusClass }` }>
			<div className="adbot-status-card__header">
				<h3>{ title }</h3>
				<span className="adbot-status-card__badge">{ value }</span>
			</div>
			{ description && (
				<p className="adbot-status-card__description">{ description }</p>
			) }
		</div>
	);
}
