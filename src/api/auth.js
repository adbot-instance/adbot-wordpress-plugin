import { apiGet, apiPost } from './client';

export function getAuthStatus() {
	return apiGet( 'auth/status' );
}

export function initiateOAuth() {
	return apiPost( 'auth/google' );
}

export function disconnect() {
	return apiPost( 'auth/disconnect' );
}
