import { useState, useEffect, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getGA4Properties } from '../api/ga4';

function CheckIcon() {
	return (
		<svg viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" focusable="false">
			<path
				fill="currentColor"
				d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.3 3.3 6.8-6.8a1 1 0 0 1 1.4 0Z"
			/>
		</svg>
	);
}

// Reuses the .adbot-container-picker / .adbot-container-card styles so the GA4
// picker matches the GTM container picker visually.
export default function PropertySelector( { onSelect, selectedPath, busy } ) {
	const [ accounts, setAccounts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ query, setQuery ] = useState( '' );

	useEffect( () => {
		setLoading( true );
		getGA4Properties()
			.then( ( data ) => {
				setAccounts( Array.isArray( data ) ? data : [] );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load GA4 properties', 'adbot' ) );
				setLoading( false );
			} );
	}, [] );

	const total = useMemo(
		() => accounts.reduce( ( n, a ) => n + ( a.properties?.length || 0 ), 0 ),
		[ accounts ]
	);

	const filtered = useMemo( () => {
		const q = query.trim().toLowerCase();
		if ( ! q ) {
			return accounts;
		}
		return accounts
			.map( ( account ) => {
				const accountMatch = ( account.name || '' ).toLowerCase().includes( q );
				const properties = ( account.properties || [] ).filter(
					( p ) =>
						accountMatch ||
						( p.name || '' ).toLowerCase().includes( q ) ||
						( p.measurementId || '' ).toLowerCase().includes( q ) ||
						( p.websiteUrl || '' ).toLowerCase().includes( q )
				);
				return { ...account, properties };
			} )
			.filter( ( account ) => account.properties.length > 0 );
	}, [ accounts, query ] );

	if ( loading ) {
		return (
			<div className="adbot-container-picker__status">
				<div className="adbot-progress-bar">
					<span />
				</div>
				<p>{ __( 'Loading your GA4 properties…', 'adbot' ) }</p>
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
					'No GA4 properties found. Make sure the connected Google account has access to Google Analytics.',
					'adbot'
				) }
			</p>
		);
	}

	return (
		<div className="adbot-container-picker">
			{ total > 6 && (
				<input
					type="search"
					className="adbot-container-picker__search"
					placeholder={ __( 'Search by name, measurement ID, or domain…', 'adbot' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					aria-label={ __( 'Search GA4 properties', 'adbot' ) }
					disabled={ busy }
				/>
			) }

			{ filtered.length === 0 ? (
				<p className="adbot-container-picker__empty">
					{ __( 'No properties match your search.', 'adbot' ) }
				</p>
			) : (
				<div className="adbot-container-picker__groups">
					{ filtered.map( ( account ) => (
						<div key={ account.path } className="adbot-container-picker__group">
							<div className="adbot-container-picker__group-label">
								<span>{ account.name || __( 'Account', 'adbot' ) }</span>
								<span className="adbot-container-picker__group-count">
									{ account.properties.length }
								</span>
							</div>
							<ul className="adbot-container-picker__list">
								{ account.properties.map( ( property ) => {
									const isSelected = selectedPath === property.path;
									return (
										<li key={ property.path }>
											<button
												type="button"
												className={
													'adbot-container-card' +
													( isSelected ? ' is-selected' : '' )
												}
												onClick={ () => onSelect( property ) }
												disabled={ busy }
												aria-pressed={ isSelected }
											>
												<span className="adbot-container-card__body">
													<span className="adbot-container-card__name">
														{ property.name || property.measurementId }
													</span>
													<span className="adbot-container-card__meta">
														<span className="adbot-container-card__id">
															{ property.measurementId }
														</span>
														{ property.websiteUrl && (
															<span className="adbot-container-card__domains">
																{ property.websiteUrl }
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
