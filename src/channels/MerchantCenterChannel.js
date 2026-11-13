import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ChannelCard, { ChannelDetail, formatWhen } from '../components/ChannelCard';
import MerchantAccountPicker from '../components/MerchantAccountPicker';
import {
	getMerchantAccounts,
	selectMerchantAccount,
	claimMerchantWebsite,
	registerProductFeed,
	fetchProductFeedNow,
} from '../api/gmc';
import { openOAuthPopup, hasScopeSet } from '../lib/oauthPopup';

// Merchant Center card: grant → pick account → claim website → register feed.
//
// The feed is served by WordPress and fetched by Google on a daily schedule.
// No product data passes through Adbot, which is worth saying on the card
// itself — it is the thing merchants ask about.
//
// Props: status (a /status response) and onStatusChange (re-read it).
// `embedded`: rendered inside its product page, whose header already shows
// the logo, name and status, so the card is just the setup steps.
export default function MerchantCenterChannel( { status, onStatusChange, embedded = false } ) {
	const granted = hasScopeSet( status, 'gmc' );
	const wooActive = Boolean( status?.wooActive );

	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	const load = useCallback(
		( { refresh = false } = {} ) => {
			if ( ! granted || ! wooActive ) {
				return Promise.resolve();
			}
			setLoading( true );
			setError( '' );
			return getMerchantAccounts( { refresh } )
				.then( ( result ) => setData( result ) )
				.catch( ( err ) =>
					setError( err?.message || __( 'Could not load Merchant Center.', 'adbot' ) )
				)
				.finally( () => setLoading( false ) );
		},
		[ granted, wooActive ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	useEffect( () => {
		const onFocus = () => {
			if ( onStatusChange ) {
				setTimeout( onStatusChange, 800 );
			}
		};
		window.addEventListener( 'focus', onFocus );
		return () => window.removeEventListener( 'focus', onFocus );
	}, [ onStatusChange ] );

	const run = async ( key, fn, successMessage ) => {
		setError( '' );
		setNotice( '' );
		setBusy( key );
		try {
			const result = await fn();
			if ( successMessage ) {
				setNotice( successMessage( result ) );
			}
			await load( { refresh: true } );
			if ( onStatusChange ) {
				onStatusChange();
			}
		} catch ( err ) {
			setError( err?.message || __( 'That did not work. Try again.', 'adbot' ) );
		}
		setBusy( '' );
	};

	const handleGrant = async () => {
		setError( '' );
		setBusy( 'grant' );
		try {
			await openOAuthPopup( { scopes: [ 'gmc' ] } );
		} catch ( err ) {
			setError( err?.message || __( 'Could not start Google sign-in.', 'adbot' ) );
		}
		setBusy( '' );
	};

	const logoUrl = window.adbotAdmin?.images?.gmc || '';
	const feed = data?.feed;
	const selected = data?.selected || status?.gmcAccountName || '';
	const claimedAt = data?.claimedAt || status?.gmcClaimedAt || '';
	const registeredAt = data?.registeredAt || status?.gmcFeedRegisteredAt || '';
	const feedUrl = feed?.url || status?.productFeedUrl || '';

	let state = 'neutral';
	let stateLabel = __( 'Not connected', 'adbot' );
	if ( ! wooActive ) {
		state = 'neutral';
		stateLabel = __( 'WooCommerce required', 'adbot' );
	} else if ( ! granted ) {
		state = 'accent';
		stateLabel = __( 'Ready to connect', 'adbot' );
	} else if ( registeredAt ) {
		state = 'success';
		stateLabel = __( 'Feed registered', 'adbot' );
	} else if ( selected ) {
		state = 'warning';
		stateLabel = __( 'Feed not registered', 'adbot' );
	}

	const actions = [];
	if ( wooActive && ! granted ) {
		actions.push(
			<button
				key="grant"
				type="button"
				className="adbot-btn adbot-btn--primary"
				onClick={ handleGrant }
				disabled={ busy === 'grant' }
			>
				{ busy === 'grant'
					? __( 'Opening Google…', 'adbot' )
					: __( 'Grant Merchant Center access', 'adbot' ) }
			</button>
		);
	} else if ( wooActive && granted ) {
		if ( selected && ! claimedAt ) {
			actions.push(
				<button
					key="claim"
					type="button"
					className="adbot-btn adbot-btn--primary"
					onClick={ () =>
						run( 'claim', claimMerchantWebsite, () =>
							__( 'Website claimed for this Merchant Center account.', 'adbot' )
						)
					}
					disabled={ !! busy }
				>
					{ busy === 'claim'
						? __( 'Claiming…', 'adbot' )
						: __( 'Claim website', 'adbot' ) }
				</button>
			);
		}

		if ( selected && feed?.ready ) {
			const registerLabel = registeredAt
				? __( 'Update feed settings', 'adbot' )
				: __( 'Register product feed', 'adbot' );
			actions.push(
				<button
					key="register"
					type="button"
					className={
						'adbot-btn ' + ( registeredAt ? 'adbot-btn--ghost' : 'adbot-btn--primary' )
					}
					onClick={ () =>
						run( 'register', registerProductFeed, () =>
							__( 'Product feed registered. Google will fetch it daily.', 'adbot' )
						)
					}
					disabled={ !! busy }
				>
					{ busy === 'register' ? __( 'Registering…', 'adbot' ) : registerLabel }
				</button>
			);
		}

		if ( registeredAt ) {
			actions.push(
				<button
					key="fetch"
					type="button"
					className="adbot-btn adbot-btn--ghost"
					onClick={ () =>
						run( 'fetch', fetchProductFeedNow, () =>
							__( 'Asked Google to fetch the feed now.', 'adbot' )
						)
					}
					disabled={ !! busy }
				>
					{ busy === 'fetch' ? __( 'Requesting…', 'adbot' ) : __( 'Fetch now', 'adbot' ) }
				</button>
			);
		}

		actions.push(
			<button
				key="refresh"
				type="button"
				className="adbot-btn adbot-btn--ghost"
				onClick={ () => load( { refresh: true } ) }
				disabled={ !! busy || loading }
			>
				{ __( 'Refresh', 'adbot' ) }
			</button>
		);
	}

	return (
		<ChannelCard
			title={ embedded ? __( 'Setup', 'adbot' ) : __( 'Google Merchant Center', 'adbot' ) }
			logoUrl={ embedded ? '' : logoUrl }
			state={ state }
			stateLabel={ embedded ? '' : stateLabel }
			description={ __(
				'Publish your WooCommerce products to Google Shopping. Adbot hosts the product feed on your site and Google fetches it daily — your product data never passes through Adbot.',
				'adbot'
			) }
			actions={ actions }
		>
			{ error && <p className="adbot-error">{ error }</p> }
			{ notice && <p className="adbot-success">{ notice }</p> }

			{ ! wooActive && (
				<p className="adbot-channel-card__description">
					{ __(
						'Activate WooCommerce to build a product feed. Everything else in Adbot works without it.',
						'adbot'
					) }
				</p>
			) }

			{ wooActive && ! granted && (
				<p className="adbot-channel-card__description">
					{ __(
						'Adbot needs permission to manage Merchant Center for your Google account. You only grant this once.',
						'adbot'
					) }
				</p>
			) }

			{ wooActive && granted && loading && ! data && (
				<div className="adbot-progress-bar">
					<span />
				</div>
			) }

			{ wooActive && granted && data && (
				<>
					<MerchantAccountPicker
						accounts={ data.accounts || [] }
						selected={ selected }
						busy={ busy === 'select' }
						onSelect={ ( account ) =>
							run( 'select', () => selectMerchantAccount( account.name ) )
						}
					/>

					{ feed && ! feed.ready && feed.blockedReason && (
						<p className="adbot-error">{ feed.blockedReason }</p>
					) }

					{ /* Non-blocking: the feed can still be registered. */ }
					{ ( feed?.warnings || [] ).map( ( warning ) => (
						<p key={ warning } className="adbot-warning">
							{ warning }
						</p>
					) ) }

					{ selected && ! claimedAt && (
						<p className="adbot-channel-card__description">
							{ __(
								'Claiming the website links this store to the account. It needs the site verified in Search Console first — the same proof covers both.',
								'adbot'
							) }
						</p>
					) }

					<ChannelDetail label={ __( 'Website claimed', 'adbot' ) }>
						{ claimedAt ? formatWhen( claimedAt ) : __( 'Not yet', 'adbot' ) }
					</ChannelDetail>

					<ChannelDetail label={ __( 'Feed URL', 'adbot' ) }>
						{ feedUrl ? (
							<a href={ feedUrl } target="_blank" rel="noreferrer">
								{ feedUrl }
							</a>
						) : (
							__( 'Not available', 'adbot' )
						) }
					</ChannelDetail>

					<ChannelDetail label={ __( 'Feed registered', 'adbot' ) }>
						{ registeredAt ? formatWhen( registeredAt ) : __( 'Not yet', 'adbot' ) }
					</ChannelDetail>

					{ feed?.feedLabel && (
						<ChannelDetail label={ __( 'Target', 'adbot' ) }>
							{ sprintf(
								/* translators: 1: country code, 2: language code. */
								__( '%1$s · %2$s', 'adbot' ),
								feed.feedLabel,
								feed.contentLanguage
							) }
						</ChannelDetail>
					) }
				</>
			) }
		</ChannelCard>
	);
}
