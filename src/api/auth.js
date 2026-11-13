import { apiGet, apiPost } from './client';

export function getAuthStatus() {
	return apiGet( 'auth/status' );
}

/**
 * Start the Google consent flow.
 *
 * @param {string[]} [scopes] Scope bundle names ('gsc', 'gmc') to request on
 *                            top of the base GTM/GA4 grant. Sent only when
 *                            non-empty, so an ordinary connect keeps the exact
 *                            request shape the backend has always seen.
 */
export function initiateOAuth( scopes ) {
	const data = Array.isArray( scopes ) && scopes.length ? { scopes } : undefined;
	return apiPost( 'auth/google', data );
}

export function disconnect() {
	return apiPost( 'auth/disconnect' );
}
