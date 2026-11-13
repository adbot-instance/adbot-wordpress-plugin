import { initiateOAuth } from '../api/auth';

/**
 * Open the Google consent flow in a popup, falling back to a full-page
 * redirect when the browser blocks it.
 *
 * Extracted because the same open-or-redirect dance lived in four places
 * (StepConnect, StepGA4, pages/Connect, pages/Dashboard) and each channel card
 * needs a scoped variant of it. The popup posts `adbot:oauth-result` back to
 * the opener from finishOAuthPopupReturn() in src/index.js; callers listen for
 * that, or just poll status on focus.
 *
 * @param {Object}   options
 * @param {string[]} [options.scopes] Scope bundle names ('gsc', 'gmc') to
 *                                    request on top of the base grant. Omit
 *                                    for an ordinary connect.
 * @return {Promise<Window|null>} The popup, or null when we redirected instead.
 */
export async function openOAuthPopup( { scopes } = {} ) {
	const { url } = await initiateOAuth( scopes );

	if ( ! url ) {
		throw new Error( 'Adbot did not receive a Google sign-in URL.' );
	}

	// Named window, so a second click reuses the same popup rather than
	// stacking consent screens the user has to dismiss one by one.
	const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );

	if ( ! popup ) {
		// Blocked. The return lands back on this admin page carrying the OAuth
		// result params, and the caller's status poll picks it up from there.
		window.location.href = url;
		return null;
	}

	return popup;
}

/**
 * Whether the connected Google token already covers a scope bundle.
 *
 * Reads the `googleScopeSets` / `scopeSets` the backend reports, so a card can
 * show its picker immediately instead of provoking a 403 from Google to
 * discover it needs a grant.
 *
 * @param {Object} status A /status or /auth/status response.
 * @param {string} bundle Scope bundle name.
 * @return {boolean} True when the grant is already held.
 */
export function hasScopeSet( status, bundle ) {
	const sets = status?.googleScopeSets || status?.scopeSets || [];
	return Array.isArray( sets ) && sets.includes( bundle );
}
