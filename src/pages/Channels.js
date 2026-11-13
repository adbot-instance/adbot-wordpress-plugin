import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { getStatus } from '../api/settings';
import SearchConsoleChannel from '../channels/SearchConsoleChannel';
import MerchantCenterChannel from '../channels/MerchantCenterChannel';

/**
 * The organic and e-commerce surfaces, kept off the onboarding wizard on
 * purpose: neither is a prerequisite for the tracking audit, and threading them
 * through Onboarding_Controller::resolve_state() would risk the funnel for two
 * features most sites adopt later, if at all.
 *
 * Status is loaded once here and handed down, so both cards agree about which
 * scopes are granted and neither duplicates the /status call.
 */
export default function Channels() {
	const [ status, setStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	const loadStatus = useCallback( () => {
		return getStatus()
			.then( ( data ) => {
				setStatus( data );
				setError( '' );
			} )
			.catch( ( err ) =>
				setError( err?.message || __( 'Could not load your Google connection.', 'adbot' ) )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		loadStatus();
	}, [ loadStatus ] );

	if ( loading ) {
		return (
			<div className="adbot-connect">
				<h2>{ __( 'Channels', 'adbot' ) }</h2>
				<div className="adbot-progress-bar">
					<span />
				</div>
			</div>
		);
	}

	return (
		<div className="adbot-connect adbot-channels">
			<h2>{ __( 'Channels', 'adbot' ) }</h2>
			<p>
				{ __(
					'Send your organic and product data to Google. Each channel asks for its own permission, so you only grant what you use.',
					'adbot'
				) }
			</p>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{ ! status?.connected ? (
				<div className="adbot-connect__section">
					<p>
						{ __(
							'Connect your Google account on the Connect screen first, then come back here.',
							'adbot'
						) }
					</p>
				</div>
			) : (
				<>
					<SearchConsoleChannel status={ status } onStatusChange={ loadStatus } />
					<MerchantCenterChannel status={ status } onStatusChange={ loadStatus } />
				</>
			) }
		</div>
	);
}
