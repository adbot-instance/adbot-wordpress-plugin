import { apiGet, apiPost } from './client';

export function getOnboarding() {
	return apiGet( 'onboarding' );
}

export function updateOnboarding( payload ) {
	return apiPost( 'onboarding', payload );
}

export function resetOnboarding() {
	return apiPost( 'onboarding/reset' );
}
