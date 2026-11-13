import { __, sprintf } from '@wordpress/i18n';
import { formatDelta, formatValue } from './format';

/**
 * Stat tiles: label, value, and the change against the comparison period.
 * The delta's colour is direction × whether up is good (so a falling average
 * position is green), and it always carries its sign and period in text.
 *
 * @param {Object} props
 * @param {Array}  props.scorecards
 * @param {string} props.currency
 * @param {string} props.compareLabel e.g. "previous period"
 * @param {number} props.limit        Show only the first N (Overview previews).
 */
export default function Scorecards( {
	scorecards,
	currency,
	compareLabel,
	limit,
} ) {
	const cards = limit
		? ( scorecards || [] ).slice( 0, limit )
		: scorecards || [];
	if ( ! cards.length ) {
		return null;
	}
	return (
		<dl
			className={ `adbot-stats ${ limit ? 'adbot-stats--compact' : '' }` }
		>
			{ cards.map( ( c ) => {
				const delta = formatDelta(
					c.value,
					c.previous,
					c.goodDirection
				);
				return (
					<div key={ c.id } className="adbot-stat">
						<dt className="adbot-stat__label">{ c.label }</dt>
						<dd className="adbot-stat__value">
							{ formatValue( c.value, c.format, currency ) }
						</dd>
						{ delta ? (
							<dd
								className={ `adbot-stat__delta adbot-stat__delta--${ delta.tone }` }
								title={ sprintf(
									/* translators: %s: comparison period, e.g. "previous period". */
									__( 'vs %s', 'adbot' ),
									compareLabel ||
										__( 'previous period', 'adbot' )
								) }
							>
								{ delta.label }
								{ ! limit ? (
									<span className="adbot-stat__period">
										{ ' ' }
										{ sprintf(
											/* translators: %s: comparison period. */
											__( 'vs %s', 'adbot' ),
											compareLabel ||
												__( 'previous period', 'adbot' )
										) }
									</span>
								) : null }
							</dd>
						) : null }
					</div>
				);
			} ) }
		</dl>
	);
}
