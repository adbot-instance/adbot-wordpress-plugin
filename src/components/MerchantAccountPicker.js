import { useMemo, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

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

// Merchant Center account picker. Same `.adbot-container-picker` styling as the
// GTM, GA4 and Search Console pickers.
//
// Props: see the call site in src/channels/.
export default function MerchantAccountPicker( { accounts = [], selected, busy, onSelect } ) {
	const [ query, setQuery ] = useState( '' );

	const filtered = useMemo( () => {
		const q = query.trim().toLowerCase();
		if ( ! q ) {
			return accounts;
		}
		return accounts.filter(
			( account ) =>
				( account.accountName || '' ).toLowerCase().includes( q ) ||
				( account.accountId || '' ).toLowerCase().includes( q )
		);
	}, [ accounts, query ] );

	if ( ! accounts.length ) {
		return (
			<p className="adbot-container-picker__empty">
				{ __(
					'No Merchant Center accounts found for this Google account. Create one at merchants.google.com, then refresh.',
					'adbot'
				) }
			</p>
		);
	}

	return (
		<div className="adbot-container-picker">
			{ accounts.length > 6 && (
				<input
					type="search"
					className="adbot-container-picker__search"
					placeholder={ __( 'Search by name or account ID…', 'adbot' ) }
					value={ query }
					onChange={ ( e ) => setQuery( e.target.value ) }
					aria-label={ __( 'Search Merchant Center accounts', 'adbot' ) }
					disabled={ busy }
				/>
			) }

			{ filtered.length === 0 ? (
				<p className="adbot-container-picker__empty">
					{ __( 'No accounts match your search.', 'adbot' ) }
				</p>
			) : (
				<ul className="adbot-container-picker__list">
					{ filtered.map( ( account ) => {
						const isSelected = selected === account.name;

						return (
							<li key={ account.name }>
								<button
									type="button"
									className={
										'adbot-container-card' + ( isSelected ? ' is-selected' : '' )
									}
									onClick={ () => onSelect( account ) }
									disabled={ busy }
									aria-pressed={ isSelected }
								>
									<span className="adbot-container-card__body">
										<span className="adbot-container-card__name">
											{ account.accountName }
										</span>
										<span className="adbot-container-card__meta">
											<span className="adbot-container-card__id">
												{ account.accountId }
											</span>
											{ account.testAccount && (
												<span className="adbot-container-card__domains">
													{ __(
														'Test account — products will not serve',
														'adbot'
													) }
												</span>
											) }
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
