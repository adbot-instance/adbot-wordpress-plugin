import { apiGet } from './client';

/**
 * Chart data for one Google product: ga4 | gsc | gmc | gbp.
 * Shape matches the Shopify app's ProductInsights (scorecards, charts,
 * breakdowns, source "live" | "none", emptyReason, comingSoon).
 *
 * @param {string}  product
 * @param {string}  range   7d | 28d | 90d
 * @param {boolean} refresh Bypass the WordPress cache.
 * @return {Promise<Object>} ProductInsights.
 */
export function getInsights( product, range = '28d', refresh = false ) {
	const params = new URLSearchParams( { range } );
	if ( refresh ) {
		params.set( 'refresh', '1' );
	}
	return apiGet( `insights/${ product }?${ params.toString() }` );
}
