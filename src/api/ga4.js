import { apiGet, apiPost } from './client';

export function getGA4Properties() {
	return apiGet( 'ga4/properties' ).then( groupProperties );
}

export function selectGA4Property( measurementId ) {
	return apiPost( 'ga4/select', { measurementId } );
}

/**
 * The backend returns a flat list:
 *   { properties: [ { accountId, accountName, propertyId, propertyName,
 *                     measurementId, websiteUrl, streamName } ] }
 * Group by account (mirrors groupContainers in api/audit.js) so the picker can
 * show account → properties, and give each property a stable `path` key.
 *
 * @param {Array|Object} data Raw `/ga4/properties` response.
 * @return {Array} Accounts, each with a nested `properties` array.
 */
function groupProperties( data ) {
	const flat = Array.isArray( data ) ? data : ( data && data.properties ) || [];
	if ( ! Array.isArray( flat ) ) {
		return [];
	}

	const byAccount = new Map();
	flat.forEach( ( item ) => {
		if ( ! item ) {
			return;
		}
		const key = item.accountId || item.accountName || '';
		if ( ! byAccount.has( key ) ) {
			byAccount.set( key, {
				path: key,
				accountId: item.accountId || '',
				name: item.accountName || '',
				properties: [],
			} );
		}
		byAccount.get( key ).properties.push( {
			// `path` is the stable key + selection identity (a measurement id is
			// unique per web stream).
			path: item.measurementId || `${ item.propertyId }:${ item.streamName }`,
			propertyId: item.propertyId || '',
			name: item.propertyName || '',
			measurementId: item.measurementId || '',
			websiteUrl: item.websiteUrl || '',
			streamName: item.streamName || '',
		} );
	} );

	return Array.from( byAccount.values() );
}
