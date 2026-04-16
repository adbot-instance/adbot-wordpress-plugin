import { apiPost } from './client';

export function generatePlan( gaps, measurementId ) {
	return apiPost( 'setup/plan', { gaps, measurementId } );
}

export function executePlan( containerPath, workspacePath, plan ) {
	return apiPost( 'setup/execute', { containerPath, workspacePath, plan } );
}

export function publishChanges( containerPath, workspacePath ) {
	return apiPost( 'setup/publish', { containerPath, workspacePath } );
}
