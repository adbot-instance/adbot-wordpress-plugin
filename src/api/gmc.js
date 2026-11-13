import { apiGet, apiPost } from './client';

/**
 * Merchant Center channel.
 *
 * The feed itself is served by WordPress and fetched by Google directly, so
 * none of these calls carries product data — only the feed URL and the account
 * being connected.
 */

export function getMerchantAccounts( { refresh = false } = {} ) {
	return apiGet( 'gmc/accounts' + ( refresh ? '?refresh=1' : '' ) );
}

export function selectMerchantAccount( accountName ) {
	return apiPost( 'gmc/select', { accountName } );
}

/** Claim the store website. Needs the domain verified first. */
export function claimMerchantWebsite() {
	return apiPost( 'gmc/claim' );
}

/** Register the plugin's feed URL as a daily scheduled fetch. */
export function registerProductFeed() {
	return apiPost( 'gmc/feed' );
}

export function getProductFeedStatus() {
	return apiGet( 'gmc/feed' );
}

/** Ask Google to fetch now rather than waiting for the daily schedule. */
export function fetchProductFeedNow() {
	return apiPost( 'gmc/feed/fetch' );
}
