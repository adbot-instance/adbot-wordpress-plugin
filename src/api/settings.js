import { apiGet, apiPost, apiDelete } from './client';

export function getSettings() {
	return apiGet( 'settings' );
}

export function updateSettings( data ) {
	return apiPost( 'settings', data );
}

export function installSnippet( containerId, containerPath, ga4MeasurementId ) {
	return apiPost( 'snippet/install', { containerId, containerPath, ga4MeasurementId } );
}

export function uninstallSnippet() {
	return apiDelete( 'snippet/uninstall' );
}

export function getStatus() {
	return apiGet( 'status' );
}

export function getSnippetStatus() {
	return apiGet( 'snippet/status' );
}

export function rescanSnippet() {
	return apiPost( 'snippet/rescan', {} );
}
