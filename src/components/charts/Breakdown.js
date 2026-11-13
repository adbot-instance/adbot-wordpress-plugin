import { __ } from '@wordpress/i18n';
import StatusBadge from '../StatusBadge';
import { formatValue, SERIES_VARS } from './format';

const STATUS_TONE = {
	success: 'success',
	warning: 'warning',
	critical: 'danger',
};
const STATUS_FILL = {
	success: 'var(--adbot-success-dot)',
	warning: 'var(--adbot-warning-dot)',
	critical: 'var(--adbot-danger-dot)',
};

/**
 * A breakdown from the insights payload (top pages, sources, product status).
 *
 * Every kind renders as a labelled horizontal bar list with the value printed
 * at the bar end, so the numbers are always readable as text:
 * - bar / funnel: one hue (chart-1), magnitude only.
 * - donut: share of total; each label keeps its own chart colour by position
 *   in the backend's order, which is the entity's, not its rank on screen.
 * - status rows (`tone`): reserved status colours plus a text label.
 * - table: plain table with the backend's extra columns.
 *
 * @param {Object} props
 * @param {Object} props.breakdown
 * @param {string} props.currency
 */
export default function Breakdown( { breakdown, currency } ) {
	const rows = breakdown.rows || [];
	if ( ! rows.length ) {
		return null;
	}

	if ( breakdown.kind === 'table' ) {
		const cols = breakdown.columns || [];
		return (
			<figure className="adbot-chart adbot-breakdown">
				<figcaption className="adbot-chart__head">
					<span className="adbot-chart__title">
						{ breakdown.title }
					</span>
				</figcaption>
				<div className="adbot-chart__table-wrap">
					<table className="adbot-table">
						<thead>
							<tr>
								<th scope="col">{ __( 'Name', 'adbot' ) }</th>
								<th scope="col" className="is-num">
									{ breakdown.valueLabel }
								</th>
								{ cols.map( ( c ) => (
									<th
										scope="col"
										key={ c.id }
										className={
											c.format === 'text' ? '' : 'is-num'
										}
									>
										{ c.label }
									</th>
								) ) }
							</tr>
						</thead>
						<tbody>
							{ rows.map( ( r ) => (
								<tr key={ r.label }>
									<td className="adbot-table__label">
										{ r.label }
									</td>
									<td className="is-num">
										{ formatValue(
											r.value,
											breakdown.format,
											currency
										) }
									</td>
									{ cols.map( ( c ) => (
										<td
											key={ c.id }
											className={
												c.format === 'text'
													? ''
													: 'is-num'
											}
										>
											{ c.format === 'text'
												? r.extra?.[ c.id ] ?? ''
												: formatValue(
														r.extra?.[ c.id ],
														c.format,
														currency
												  ) }
										</td>
									) ) }
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
				{ breakdown.note ? (
					<p className="adbot-chart__note">{ breakdown.note }</p>
				) : null }
			</figure>
		);
	}

	const max =
		Math.max( ...rows.map( ( r ) => Number( r.value ) || 0 ), 0 ) || 1;
	const total =
		rows.reduce( ( n, r ) => n + ( Number( r.value ) || 0 ), 0 ) || 1;
	const isShare = breakdown.kind === 'donut';

	return (
		<figure className="adbot-chart adbot-breakdown">
			<figcaption className="adbot-chart__head">
				<span className="adbot-chart__title">{ breakdown.title }</span>
				<span className="adbot-chart__unit">
					{ breakdown.valueLabel }
				</span>
			</figcaption>
			<ul className="adbot-bars">
				{ rows.map( ( r, i ) => {
					const status = r.tone && STATUS_FILL[ r.tone ];
					let fill = SERIES_VARS[ 0 ];
					if ( status ) {
						fill = status;
					} else if ( isShare ) {
						fill =
							SERIES_VARS[
								Math.min( i, SERIES_VARS.length - 1 )
							];
					}
					const pct = ( ( Number( r.value ) || 0 ) / max ) * 100;
					return (
						<li key={ r.label } className="adbot-bars__row">
							<span
								className="adbot-bars__label"
								title={ r.label }
							>
								{ r.tone ? (
									<StatusBadge
										tone={ STATUS_TONE[ r.tone ] }
										dot
									>
										{ r.label }
									</StatusBadge>
								) : (
									r.label
								) }
							</span>
							<span
								className="adbot-bars__track"
								aria-hidden="true"
							>
								<span
									className="adbot-bars__fill"
									style={ {
										width: `${ Math.max( pct, 1 ) }%`,
										background: fill,
									} }
								/>
							</span>
							<span className="adbot-bars__value">
								{ formatValue(
									r.value,
									breakdown.format,
									currency
								) }
								{ isShare ? (
									<span className="adbot-bars__share">
										{ formatValue(
											( Number( r.value ) || 0 ) / total,
											'percent'
										) }
									</span>
								) : null }
							</span>
						</li>
					);
				} ) }
			</ul>
			{ breakdown.note ? (
				<p className="adbot-chart__note">{ breakdown.note }</p>
			) : null }
		</figure>
	);
}
