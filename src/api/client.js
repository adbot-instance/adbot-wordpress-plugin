import apiFetch from '@wordpress/api-fetch';

const restRoot = window.adbotAdmin?.restRoot || '/wp-json/';

apiFetch.use( apiFetch.createRootURLMiddleware( restRoot ) );
apiFetch.use( apiFetch.createNonceMiddleware( window.adbotAdmin?.nonce ) );

const API_PREFIX = 'adbot/v1/';

export function apiGet( path ) {
	return apiFetch( { path: API_PREFIX + path } );
}

export function apiPost( path, data ) {
	return apiFetch( {
		path: API_PREFIX + path,
		method: 'POST',
		data,
	} );
}

export function apiDelete( path ) {
	return apiFetch( {
		path: API_PREFIX + path,
		method: 'DELETE',
	} );
}
