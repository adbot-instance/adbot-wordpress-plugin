import { apiPost, apiGet } from './client';

export function runAudit( containerPath ) {
	return apiPost( 'audit', { containerPath } );
}

export function getContainers() {
	return apiGet( 'containers' ).then( groupContainers );
}

/**
 * The backend returns a flat list:
 *   { containers: [ { accountId, accountName, accountPath, containerId,
 *                     containerName, publicId, containerPath, usageContext } ] }
 * but the wizard UI (StepProperty / ContainerSelector) expects accounts grouped
 * with nested containers and shorter field names. Adapt here so both consumers
 * get a stable shape regardless of how the backend responds.
 *
 * @param {Array|Object} data Raw `/containers` response (array or { containers }).
 * @return {Array} Accounts, each with a nested `containers` array.
 */
function groupContainers( data ) {
	const flat = Array.isArray( data )
		? data
		: ( data && data.containers ) || [];
	if ( ! Array.isArray( flat ) ) {
		return [];
	}

	const byAccount = new Map();
	flat.forEach( ( item ) => {
		if ( ! item ) {
			return;
		}
		const accountPath = item.accountPath || item.accountId || '';
		if ( ! byAccount.has( accountPath ) ) {
			byAccount.set( accountPath, {
				path: accountPath,
				accountId: item.accountId || '',
				name: item.accountName || '',
				containers: [],
			} );
		}
		byAccount.get( accountPath ).containers.push( {
			path: item.containerPath || '',
			containerId: item.containerId || '',
			name: item.containerName || '',
			publicId: item.publicId || '',
			domainName: item.domainName || [],
			usageContext: item.usageContext || [],
		} );
	} );

	return Array.from( byAccount.values() );
}
