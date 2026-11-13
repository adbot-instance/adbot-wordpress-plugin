import { useCallback, useEffect, useState } from '@wordpress/element';
import { getStatus } from '../api/settings';

/**
 * The plugin's GET /status (connection, selections per Google product), loaded
 * once per page and refreshed when the window regains focus — which is how a
 * finished OAuth popup or a change made in another tab shows up.
 *
 * A failed refresh keeps the last good status rather than blanking the page.
 *
 * @return {{status: Object|null, loading: boolean, error: string, reload: Function}} State.
 */
export default function useStatus() {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const reload = useCallback( () => {
		return getStatus()
			.then( ( data ) => {
				setStatus( data );
				setError( '' );
				return data;
			} )
			.catch( ( err ) => {
				setError( err?.message || '' );
				return null;
			} )
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		reload();
		const onFocus = () => setTimeout( reload, 800 );
		window.addEventListener( 'focus', onFocus );
		return () => window.removeEventListener( 'focus', onFocus );
	}, [ reload ] );

	return { status, loading, error, reload };
}
