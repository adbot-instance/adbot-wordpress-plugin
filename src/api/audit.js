import { apiPost, apiGet } from './client';

export function runAudit( containerPath ) {
	return apiPost( 'audit', { containerPath } );
}

export function getContainers() {
	return apiGet( 'containers' );
}
