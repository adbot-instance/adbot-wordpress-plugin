/**
 * Reduce a full audit payload to the public (pre-payment) summary shown in the
 * onboarding report. We reveal each problem's TITLE and SEVERITY only — the
 * description and the how-to-fix recommendation stay behind the paywall.
 */
export function summarizeForGate( audit ) {
	const gaps = audit?.gaps || [];
	const bySeverity = { critical: 0, warning: 0, info: 0 };
	const issues = [];

	gaps.forEach( ( g ) => {
		const severity = normalizeSeverity( g.severity );
		bySeverity[ severity ] += 1;
		issues.push( { id: g.id, title: g.title || labelFromId( g.id ), severity } );
	} );

	return {
		score: audit?.score ?? 0,
		total: gaps.length,
		bySeverity,
		issues,
		inventory: inventoryFrom( audit ),
		snippetInstalled: !! audit?.snippetInstalled,
		measurementIdPresent: !! audit?.measurementId,
		isGreenfield: isGreenfieldAudit( audit ),
	};
}

/**
 * The backend emits severities `critical | warning | info`; older data may use
 * `high | medium | low`. Collapse both into the report's display vocabulary so
 * the severity tiles never silently read zero for a real issue (which is what
 * happened when the tiles were keyed critical/high/medium/low but the data was
 * warning/info).
 */
const SEVERITY_ALIASES = {
	critical: 'critical',
	high: 'critical',
	warning: 'warning',
	medium: 'warning',
	info: 'info',
	low: 'info',
	notice: 'info',
};

export function normalizeSeverity( severity ) {
	return SEVERITY_ALIASES[ String( severity || '' ).toLowerCase() ] || 'info';
}

/**
 * "Greenfield" means the container is genuinely EMPTY — a brand-new container
 * with no tags and no triggers — so the report reframes it as a "here's what
 * we'll set up" checklist instead of a misleadingly low health score.
 *
 * This is based ONLY on the actual container inventory, never on the gap mix: a
 * fully-configured container with a single remaining gap is NOT a clean slate.
 * If we don't have an explicit tag inventory, we never claim greenfield.
 */
export function isGreenfieldAudit( audit ) {
	const tags = audit?.tags;
	if ( ! Array.isArray( tags ) ) {
		return false;
	}
	const triggers = audit?.triggers;
	const noTriggers = ! Array.isArray( triggers ) || triggers.length === 0;
	return tags.length === 0 && noTriggers;
}

/**
 * Names (and tag types) of what already exists in the container, so the report
 * can show the user we read their setup correctly instead of guessing.
 */
function inventoryFrom( audit ) {
	const list = ( arr ) =>
		( Array.isArray( arr ) ? arr : [] ).map( ( x, i ) => ( {
			id: ( x && ( x.tagId || x.triggerId || x.variableId ) ) || `i${ i }`,
			name: ( x && x.name ) || '(unnamed)',
			type: ( x && x.type ) || '',
		} ) );
	return {
		tags: list( audit?.tags ),
		triggers: list( audit?.triggers ),
		variables: list( audit?.variables ),
	};
}

const TAG_TYPE_LABELS = {
	googtag: 'Google tag',
	gaawc: 'GA4 configuration',
	gaawe: 'GA4 event',
	ua: 'Universal Analytics',
	html: 'Custom HTML',
	img: 'Custom image',
	awct: 'Google Ads conversion',
	sp: 'Google Ads remarketing',
	gclidw: 'Conversion linker',
	flc: 'Floodlight counter',
	fls: 'Floodlight sales',
};

/** Humanise a raw GTM tag type (e.g. `gaawe` → "GA4 event"). */
export function tagTypeLabel( type = '' ) {
	const t = String( type || '' ).toLowerCase();
	if ( TAG_TYPE_LABELS[ t ] ) {
		return TAG_TYPE_LABELS[ t ];
	}
	if ( t.startsWith( 'cvt_' ) ) {
		return 'Custom template';
	}
	return type || 'Tag';
}

/**
 * Humanise a gap id like `missing_event_site_search` into "Site search" as a
 * last resort when the backend did not supply a title.
 */
function labelFromId( id = '' ) {
	const cleaned = String( id )
		.replace( /^missing[_-]/i, '' )
		.replace( /^event[_-]/i, '' )
		.replace( /[_-]+/g, ' ' )
		.trim();
	if ( ! cleaned ) {
		return 'Tracking issue';
	}
	return cleaned.charAt( 0 ).toUpperCase() + cleaned.slice( 1 );
}
