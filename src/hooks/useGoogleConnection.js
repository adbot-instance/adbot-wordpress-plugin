import { useState, useEffect, useCallback } from '@wordpress/element';
import { getStatus } from '../api/settings';

/**
 * Mirrors Overview / Settings “Google connected” from REST `GET /status`.
 */
export function useGoogleConnection() {
	const [ connected, setConnected ] = useState( false );
	const [ loading, setLoading ] = useState( true );

	const refresh = useCallback( ( silent = false ) => {
		if ( ! silent ) {
			setLoading( true );
		}
		return getStatus()
			.then( ( data ) => {
				setConnected( !! data?.connected );
				setLoading( false );
				return data;
			} )
			.catch( () => {
				// A failed status read (slow or busy backend) is not a
				// disconnect. Keep the last known state so the nav lock in
				// Layout does not bounce the user back to Overview mid-task;
				// the initial state is still "not connected".
				setLoading( false );
				return null;
			} );
	}, [] );

	useEffect( () => {
		refresh( false );
	}, [ refresh ] );

	useEffect( () => {
		const onFocus = () => {
			if ( document.visibilityState === 'visible' ) {
				refresh( true );
			}
		};
		window.addEventListener( 'focus', onFocus );
		document.addEventListener( 'visibilitychange', onFocus );
		return () => {
			window.removeEventListener( 'focus', onFocus );
			document.removeEventListener( 'visibilitychange', onFocus );
		};
	}, [ refresh ] );

	return { connected, loading, refresh };
}
