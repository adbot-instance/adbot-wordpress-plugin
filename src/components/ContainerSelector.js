import { useState, useEffect } from '@wordpress/element';
import { getContainers } from '../api/audit';

export default function ContainerSelector( { onSelect, selectedPath } ) {
	const [ accounts, setAccounts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		setLoading( true );
		getContainers()
			.then( ( data ) => {
				setAccounts( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || 'Failed to load containers' );
				setLoading( false );
			} );
	}, [] );

	if ( loading ) {
		return <p>Loading GTM containers...</p>;
	}

	if ( error ) {
		return <p className="adbot-error">{ error }</p>;
	}

	if ( ! accounts.length ) {
		return <p>No GTM accounts found. Make sure the connected Google account has access to Tag Manager.</p>;
	}

	return (
		<div className="adbot-container-selector">
			{ accounts.map( ( account ) => (
				<div key={ account.path } className="adbot-container-selector__account">
					<h4>{ account.name }</h4>
					<ul>
						{ account.containers.map( ( container ) => (
							<li key={ container.path }>
								<button
									className={
										'button' +
										( selectedPath === container.path ? ' button-primary' : '' )
									}
									onClick={ () => onSelect( container ) }
								>
									{ container.name } ({ container.containerId })
								</button>
								{ container.domainName?.length > 0 && (
									<span className="adbot-container-selector__domains">
										{ container.domainName.join( ', ' ) }
									</span>
								) }
							</li>
						) ) }
					</ul>
				</div>
			) ) }
		</div>
	);
}
