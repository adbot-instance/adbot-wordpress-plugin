import { useMemo, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

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

// Search Console property picker.
//
// Reuses the `.adbot-container-picker` / `.adbot-container-card` styles so it
// matches the GTM and GA4 pickers — the same convention PropertySelector
// documents. Flat rather than grouped: Search Console has no account layer.
//
// Data is passed in rather than fetched here, because the Channels page already
// holds it to decide what the card should offer.
//
// Props: see the call site in src/channels/.
export default function SitePicker( { sites = [], selected, busy, onSelect } ) {
	const [ query, setQuery ] = useState( '' );

	const filtered = useMemo( () => {
		const q = query.trim().toLowerCase();
		if ( ! q ) {
			return sites;
		}
		return sites.filter( ( site ) =>
			( site.siteUrl || '' ).toLowerCase().includes( q )
		);
	}, [ sites, query ] );

	if ( ! sites.length ) {
		return (
			<p className="adbot-container-picker__empty">
				{ __(
					'No Search Console properties yet. Verify this site below and Adbot will add it for you.',
					'adbot'
				) }
			</p>
		);
	}

	return (
		<div className="adbot-container-picker">
			{ sites.length > 6 && (
				<input
					type="search"
					className="adbot-container-picker__search"
					placeholder={ __( 'Search by domain…', 'adbot' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					aria-label={ __( 'Search Search Console properties', 'adbot' ) }
					disabled={ busy }
				/>
			) }

			{ filtered.length === 0 ? (
				<p className="adbot-container-picker__empty">
					{ __( 'No properties match your search.', 'adbot' ) }
				</p>
			) : (
				<ul className="adbot-container-picker__list">
					{ filtered.map( ( site ) => {
						const isSelected = selected === site.siteUrl;
						// Search Console reports properties the account can see
						// but not act on. Offering them would produce a picker
						// that succeeds and a sitemap submit that 403s.
						const readOnly = site.permissionLevel === 'siteUnverifiedUser';

						return (
							<li key={ site.siteUrl }>
								<button
									type="button"
									className={
										'adbot-container-card' + ( isSelected ? ' is-selected' : '' )
									}
									onClick={ () => onSelect( site ) }
									disabled={ busy || readOnly }
									aria-pressed={ isSelected }
								>
									<span className="adbot-container-card__body">
										<span className="adbot-container-card__name">
											{ site.siteUrl }
										</span>
										<span className="adbot-container-card__meta">
											{ site.isThisSite && (
												<span className="adbot-container-card__id">
													{ __( 'This site', 'adbot' ) }
												</span>
											) }
											<span className="adbot-container-card__domains">
												{ readOnly
													? __( 'No permission to submit sitemaps', 'adbot' )
													: sprintf(
															/* translators: %s: Search Console permission level, e.g. siteOwner. */
															__( 'Access: %s', 'adbot' ),
															site.permissionLevel || __( 'unknown', 'adbot' )
													  ) }
											</span>
										</span>
									</span>
									<span className="adbot-container-card__check" aria-hidden="true">
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
			) }
		</div>
	);
}
