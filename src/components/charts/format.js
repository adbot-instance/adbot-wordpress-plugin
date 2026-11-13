/**
 * Number formatting for the product pages. ValueFormat matches the Shopify
 * app's lib/insights/types.ts: number | currency | percent | decimal | duration.
 */

const locale = () =>
	( typeof document !== 'undefined' && document.documentElement.lang ) ||
	undefined;

/**
 * Auto-compact large numbers (1,284 / 12.9K / 4.2M); full under 10,000.
 *
 * @param {number} value
 * @param {string} format
 * @param {string} currency ISO code, for `currency`.
 * @return {string} Display string.
 */
export function formatValue( value, format = 'number', currency = '' ) {
	if ( value === null || value === undefined || Number.isNaN( value ) ) {
		return '—';
	}
	const n = Number( value );
	const compact = Math.abs( n ) >= 10000;
	try {
		switch ( format ) {
			case 'percent':
				// Backend sends ratios (0.123) for percent.
				return new Intl.NumberFormat( locale(), {
					style: 'percent',
					maximumFractionDigits: 1,
				} ).format( n );
			case 'decimal':
				return new Intl.NumberFormat( locale(), {
					maximumFractionDigits: 1,
				} ).format( n );
			case 'duration': {
				const s = Math.round( n );
				const m = Math.floor( s / 60 );
				return m > 0 ? `${ m }m ${ s % 60 }s` : `${ s }s`;
			}
			case 'currency':
				return new Intl.NumberFormat( locale(), {
					style: currency ? 'currency' : 'decimal',
					currency: currency || undefined,
					notation: compact ? 'compact' : 'standard',
					maximumFractionDigits: compact ? 1 : 0,
				} ).format( n );
			default:
				return new Intl.NumberFormat( locale(), {
					notation: compact ? 'compact' : 'standard',
					maximumFractionDigits: compact ? 1 : 0,
				} ).format( n );
		}
	} catch {
		return String( n );
	}
}

/**
 * Signed change vs the comparison period, and whether it is good news.
 *
 * @param {number} value
 * @param {number} previous
 * @param {string} goodDirection 'up' | 'down'
 * @return {Object|null} { label, tone } or null when there is no comparison.
 */
export function formatDelta( value, previous, goodDirection = 'up' ) {
	if (
		previous === null ||
		previous === undefined ||
		Number.isNaN( previous )
	) {
		return null;
	}
	if ( Number( previous ) === 0 ) {
		return Number( value ) === 0 ? { label: '0%', tone: 'neutral' } : null;
	}
	const change =
		( Number( value ) - Number( previous ) ) / Math.abs( previous );
	const rounded = Math.round( change * 1000 ) / 10;
	if ( rounded === 0 ) {
		return { label: '0%', tone: 'neutral' };
	}
	const up = rounded > 0;
	const good = goodDirection === 'down' ? ! up : up;
	return {
		label: `${ up ? '+' : '' }${ rounded }%`,
		tone: good ? 'success' : 'danger',
	};
}

/**
 * Short date for axis ticks and tooltips.
 *
 * @param {string} iso yyyy-mm-dd
 * @return {string} e.g. "12 Nov".
 */
export function formatDay( iso ) {
	const d = new Date( `${ iso }T00:00:00` );
	if ( Number.isNaN( d.getTime() ) ) {
		return iso;
	}
	return d.toLocaleDateString( locale(), { day: 'numeric', month: 'short' } );
}

/** Platform chart-1…5, always in this order. Never cycled past five. */
export const SERIES_VARS = [
	'var(--adbot-chart-1)',
	'var(--adbot-chart-2)',
	'var(--adbot-chart-3)',
	'var(--adbot-chart-4)',
	'var(--adbot-chart-5)',
];
