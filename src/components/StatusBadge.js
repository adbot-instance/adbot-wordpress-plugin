/**
 * Status tones shared by every Adbot product.
 *
 * Mirrors the Tracking Platform's `components/shared/status-badge.tsx` and
 * `lib/ui/status.ts`. The six tone names and their meanings are the same in
 * the platform, the Shopify app and this plugin:
 *
 * - success: connected, verified, healthy
 * - warning: works but needs attention
 * - danger:  broken, failed, blocks setup
 * - info:    neutral fact or in progress
 * - accent:  available, ready to connect
 * - neutral: coming soon, disabled
 *
 * Map a domain status to a tone here, never with a colour map inside a page.
 */

export const TONES = [
	'success',
	'warning',
	'danger',
	'info',
	'accent',
	'neutral',
];

/**
 * Audit / monitoring severity → tone. Same mapping as the platform's
 * SEVERITY_TONE (critical is red, high is orange, medium amber, low blue).
 */
export const SEVERITY_TONE = {
	critical: 'danger',
	high: 'accent',
	error: 'danger',
	warning: 'warning',
	medium: 'warning',
	low: 'info',
	info: 'info',
	ok: 'success',
	healthy: 'success',
};

/**
 * Older plugin components call the danger tone `error`. Accept both.
 *
 * @param {string} tone A tone name, or the legacy `error`.
 * @return {string} One of TONES; unknown values fall back to neutral.
 */
export function normalizeTone( tone ) {
	if ( tone === 'error' ) {
		return 'danger';
	}
	return TONES.includes( tone ) ? tone : 'neutral';
}

/**
 * @param {Object<string,string>} map   Domain value → tone.
 * @param {string}                value Domain value.
 * @return {string} The tone, or neutral for unknown values.
 */
export function toneFor( map, value ) {
	return normalizeTone( ( value && map[ value ] ) || 'neutral' );
}

/**
 * Health score → tone, for the score gauge.
 *
 * @param {number} score 0–100.
 * @return {string} success, warning or danger.
 */
export function scoreTone( score ) {
	if ( score >= 80 ) {
		return 'success';
	}
	return score >= 50 ? 'warning' : 'danger';
}

/**
 * Rounded status pill: sentence case, 12px semibold. Never uppercase.
 *
 *   <StatusBadge tone="success">Connected</StatusBadge>
 *   <StatusBadge tone="danger" dot>3 critical</StatusBadge>
 *
 * @param {Object}  props
 * @param {string}  props.tone      One of TONES (or legacy `error`).
 * @param {boolean} props.dot       Show a leading dot.
 * @param {string}  props.title     Optional hover text.
 * @param {string}  props.className Extra classes.
 * @param {*}       props.children  Label.
 */
export default function StatusBadge( {
	tone = 'neutral',
	dot = false,
	title,
	className = '',
	children,
} ) {
	const t = normalizeTone( tone );
	return (
		<span
			title={ title }
			className={ `adbot-pill adbot-pill--${ t } ${ className }`.trim() }
		>
			{ dot ? (
				<span className="adbot-pill__dot" aria-hidden="true" />
			) : null }
			{ children }
		</span>
	);
}
