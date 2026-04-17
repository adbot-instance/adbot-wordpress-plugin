/**
 * Reduce a full audit payload to the public (pre-payment) summary.
 * No gap titles, descriptions, or recommendations leak through.
 */
export function summarizeForGate( audit ) {
	const bySeverity = { critical: 0, high: 0, medium: 0, low: 0 };
	const categories = {};
	const gaps = audit?.gaps || [];

	gaps.forEach( ( g ) => {
		const sev = ( g.severity || 'low' ).toLowerCase();
		if ( bySeverity[ sev ] == null ) {
			bySeverity[ sev ] = 0;
		}
		bySeverity[ sev ] += 1;

		const cat = categoryOf( g.id );
		categories[ cat ] = ( categories[ cat ] || 0 ) + 1;
	} );

	return {
		score: audit?.score ?? 0,
		total: gaps.length,
		bySeverity,
		categories,
		snippetInstalled: !! audit?.snippetInstalled,
		measurementIdPresent: !! audit?.measurementId,
	};
}

const CATEGORY_LABELS = {
	google: 'Base tracking',
	page: 'Page tracking',
	form: 'Form tracking',
	purchase: 'Ecommerce',
	scroll: 'Engagement',
	click: 'Engagement',
	video: 'Engagement',
	ads: 'Ads & attribution',
	conversion: 'Ads & attribution',
};

export function categoryOf( id = '' ) {
	const parts = id.split( '_' );
	// gap ids follow pattern like `missing_google_tag` / `missing_purchase`
	const key = parts[ 1 ] || parts[ 0 ] || 'other';
	return CATEGORY_LABELS[ key ] || 'Other';
}
