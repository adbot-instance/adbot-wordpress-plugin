import { apiGet, apiPost } from './client';

/**
 * Search Console channel. Mirrors src/api/ga4.js: a cached GET to list, a POST
 * to persist the choice, plus the two calls the ownership proof needs.
 */

export function getSearchConsoleSites( { refresh = false } = {} ) {
	return apiGet( 'gsc/sites' + ( refresh ? '?refresh=1' : '' ) );
}

export function selectSearchConsoleSite( siteUrl, permissionLevel ) {
	return apiPost( 'gsc/select', { siteUrl, permissionLevel } );
}

/**
 * Publish the ownership proof and ask Google to claim it. One call: the plugin
 * stores the token and the backend does the claim, because the tag has to be
 * live before Google fetches the page.
 */
export function verifySearchConsoleSite() {
	return apiPost( 'gsc/verify' );
}

export function submitSitemap() {
	return apiPost( 'gsc/sitemap' );
}

export function getSitemapStatus() {
	return apiGet( 'gsc/sitemap' );
}
