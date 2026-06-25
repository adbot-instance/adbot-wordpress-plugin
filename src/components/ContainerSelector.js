import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getContainers } from '../api/audit';

function CheckIcon() {
	return (
		<svg
			viewBox="0 0 20 20"
			width="18"
			height="18"
			aria-hidden="true"
			focusable="false"
		>
			<path
				fill="currentColor"
				d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.3 3.3 6.8-6.8a1 1 0 0 1 1.4 0Z"
			/>
		</svg>
	);
}

export default function ContainerSelector( { onSelect, selectedPath, busy } ) {
	const [ accounts, setAccounts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ query, setQuery ] = useState( '' );

	useEffect( () => {
		setLoading( true );
		getContainers()
			.then( ( data ) => {
				setAccounts( Array.isArray( data ) ? data : [] );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load containers', 'adbot' ) );
				setLoading( false );
			} );
	}, [] );

	const totalContainers = useMemo(
		() => accounts.reduce( ( n, a ) => n + ( a.containers?.length || 0 ), 0 ),
		[ accounts ]
	);

	const filtered = useMemo( () => {
		const q = query.trim().toLowerCase();
		if ( ! q ) {
			return accounts;
		}
		return accounts
			.map( ( account ) => {
				const accountMatch = ( account.name || '' )
					.toLowerCase()
					.includes( q );
				const containers = ( account.containers || [] ).filter(
					( c ) =>
						accountMatch ||
						( c.name || '' ).toLowerCase().includes( q ) ||
						( c.publicId || '' ).toLowerCase().includes( q ) ||
						String( c.containerId || '' ).includes( q )
				);
				return { ...account, containers };
			} )
			.filter( ( account ) => account.containers.length > 0 );
	}, [ accounts, query ] );

	if ( loading ) {
		return (
			<div className="adbot-container-picker__status">
				<div className="adbot-progress-bar">
					<span />
				</div>
				<p>{ __( 'Loading your Tag Manager containers…', 'adbot' ) }</p>
			</div>
		);
	}

	if ( error ) {
		return <p className="adbot-error">{ error }</p>;
	}

	if ( ! accounts.length ) {
		return (
			<p className="adbot-container-picker__empty">
				{ __(
					'No GTM accounts found. Make sure the connected Google account has access to Tag Manager.',
					'adbot'
				) }
			</p>
		);
	}

	return (
		<div className="adbot-container-picker">
			{ totalContainers > 6 && (
				<input
					type="search"
					className="adbot-container-picker__search"
					placeholder={ __( 'Search by name, domain, or ID…', 'adbot' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					aria-label={ __( 'Search Tag Manager containers', 'adbot' ) }
					disabled={ busy }
				/>
			) }

			{ filtered.length === 0 ? (
				<p className="adbot-container-picker__empty">
					{ __( 'No containers match your search.', 'adbot' ) }
				</p>
			) : (
				<div className="adbot-container-picker__groups">
					{ filtered.map( ( account ) => (
						<div
							key={ account.path }
							className="adbot-container-picker__group"
						>
							<div className="adbot-container-picker__group-label">
								<span>{ account.name || __( 'Account', 'adbot' ) }</span>
								<span className="adbot-container-picker__group-count">
									{ account.containers.length }
								</span>
							</div>
							<ul className="adbot-container-picker__list">
								{ account.containers.map( ( container ) => {
									const isSelected = selectedPath === container.path;
									return (
										<li key={ container.path }>
											<button
												type="button"
												className={
													'adbot-container-card' +
													( isSelected ? ' is-selected' : '' )
												}
												onClick={ () => onSelect( container ) }
												disabled={ busy }
												aria-pressed={ isSelected }
											>
												<span className="adbot-container-card__body">
													<span className="adbot-container-card__name">
														{ container.name ||
															container.publicId ||
															container.containerId }
													</span>
													<span className="adbot-container-card__meta">
														{ container.publicId && (
															<span className="adbot-container-card__id">
																{ container.publicId }
															</span>
														) }
														<span className="adbot-container-card__id">
															{ __( 'ID', 'adbot' ) }{ ' ' }
															{ container.containerId }
														</span>
														{ container.domainName?.length > 0 && (
															<span className="adbot-container-card__domains">
																{ container.domainName.join( ', ' ) }
															</span>
														) }
													</span>
												</span>
												<span
													className="adbot-container-card__check"
													aria-hidden="true"
												>
													{ busy && isSelected ? (
														<span className="adbot-spinner" />
													) : (
														<CheckIcon />
													) }
												</span>
											</button>
										</li>
									);
								} ) }
							</ul>
						</div>
					) ) }
				</div>
			) }
		</div>
	);
}
