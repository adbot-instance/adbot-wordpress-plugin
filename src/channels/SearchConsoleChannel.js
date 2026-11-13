import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import ChannelCard, { ChannelDetail, formatWhen } from '../components/ChannelCard';
import SitePicker from '../components/SitePicker';
import {
	getSearchConsoleSites,
	selectSearchConsoleSite,
	verifySearchConsoleSite,
	submitSitemap,
} from '../api/gsc';
import { openOAuthPopup, hasScopeSet } from '../lib/oauthPopup';

// Search Console card: grant → verify → pick property → submit sitemap.
//
// The grant is asked for here rather than at first connect, so a site that only
// wants tag tracking never sees a Search Console consent screen.
//
// Props: status (a /status response) and onStatusChange (re-read it).
// `embedded`: rendered inside its product page, whose header already shows
// the logo, name and status, so the card is just the setup steps.
export default function SearchConsoleChannel( { status, onStatusChange, embedded = false } ) {
	const granted = hasScopeSet( status, 'gsc' );

	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( false );
	const [ busy, setBusy ] = useState( '' );
	const [ error, setError ] = useState( '' );
	const [ notice, setNotice ] = useState( '' );

	const load = useCallback(
		( { refresh = false } = {} ) => {
			if ( ! granted ) {
				return Promise.resolve();
			}
			setLoading( true );
			setError( '' );
			return getSearchConsoleSites( { refresh } )
				.then( ( result ) => setData( result ) )
				.catch( ( err ) =>
					setError( err?.message || __( 'Could not load Search Console.', 'adbot' ) )
				)
				.finally( () => setLoading( false ) );
		},
		[ granted ]
	);

	useEffect( () => {
		load();
	}, [ load ] );

	// The grant happens in a popup, so nothing in this document changes when it
	// completes. Re-reading on focus is how the other OAuth steps detect it too.
	useEffect( () => {
		const onFocus = () => {
			if ( onStatusChange ) {
				setTimeout( onStatusChange, 800 );
			}
		};
		window.addEventListener( 'focus', onFocus );
		return () => window.removeEventListener( 'focus', onFocus );
	}, [ onStatusChange ] );

	const handleGrant = async () => {
		setError( '' );
		setBusy( 'grant' );
		try {
			await openOAuthPopup( { scopes: [ 'gsc' ] } );
		} catch ( err ) {
			setError( err?.message || __( 'Could not start Google sign-in.', 'adbot' ) );
		}
		setBusy( '' );
	};

	const handleVerify = async () => {
		setError( '' );
		setNotice( '' );
		setBusy( 'verify' );
		try {
			const result = await verifySearchConsoleSite();
			if ( ! result?.verified ) {
				// The backend answers 200 with verified=false when Google
				// fetched the site but did not find the tag — not a success.
				setError(
					__(
						'Google could not confirm ownership. If your site uses a page cache, clear it and try again.',
						'adbot'
					)
				);
			} else if ( result.added === false && result.addError ) {
				// Verification is the part that can genuinely fail; a verified
				// domain whose property was not added is recoverable on reload,
				// so say so rather than presenting it as a failure.
				setNotice(
					sprintf(
						/* translators: %s: error message from Google. */
						__(
							'Ownership confirmed, but the property could not be added yet: %s',
							'adbot'
						),
						result.addError
					)
				);
			} else {
				setNotice( __( 'Ownership confirmed and the property was added.', 'adbot' ) );
			}
			await load( { refresh: true } );
			if ( onStatusChange ) {
				onStatusChange();
			}
		} catch ( err ) {
			setError(
				err?.message ||
					__(
						'Google could not confirm ownership. If your site uses a page cache, clear it and try again.',
						'adbot'
					)
			);
		}
		setBusy( '' );
	};

	const handleSelect = async ( site ) => {
		setError( '' );
		setNotice( '' );
		setBusy( 'select' );
		try {
			await selectSearchConsoleSite( site.siteUrl, site.permissionLevel );
			await load( { refresh: true } );
			if ( onStatusChange ) {
				onStatusChange();
			}
		} catch ( err ) {
			setError( err?.message || __( 'Could not save that property.', 'adbot' ) );
		}
		setBusy( '' );
	};

	const handleSubmitSitemap = async () => {
		setError( '' );
		setNotice( '' );
		setBusy( 'sitemap' );
		try {
			const result = await submitSitemap();
			setNotice(
				sprintf(
					/* translators: %s: sitemap URL. */
					__( 'Submitted %s to Search Console.', 'adbot' ),
					result.sitemapUrl
				)
			);
			await load( { refresh: true } );
			if ( onStatusChange ) {
				onStatusChange();
			}
		} catch ( err ) {
			setError( err?.message || __( 'Could not submit the sitemap.', 'adbot' ) );
		}
		setBusy( '' );
	};

	const logoUrl = window.adbotAdmin?.images?.gsc || '';
	const sitemap = data?.sitemap;
	const selected = data?.selected || status?.gscSiteUrl || '';
	const verified = Boolean( data?.verifiedAt || status?.gscVerifiedAt );
	const submittedAt = sitemap?.submittedAt || status?.gscSitemapSubmittedAt || '';

	let state = 'neutral';
	let stateLabel = __( 'Not connected', 'adbot' );
	if ( ! granted ) {
		state = 'accent';
		stateLabel = __( 'Ready to connect', 'adbot' );
	} else if ( submittedAt ) {
		state = 'success';
		stateLabel = __( 'Sitemap submitted', 'adbot' );
	} else if ( selected ) {
		state = 'warning';
		stateLabel = __( 'Sitemap not submitted', 'adbot' );
	} else if ( verified ) {
		state = 'warning';
		stateLabel = __( 'Choose a property', 'adbot' );
	}

	const actions = [];
	if ( ! granted ) {
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
					: __( 'Grant Search Console access', 'adbot' ) }
			</button>
		);
	} else {
		if ( ! data?.thisSiteVerified ) {
			actions.push(
				<button
					key="verify"
					type="button"
					className="adbot-btn adbot-btn--primary"
					onClick={ handleVerify }
					disabled={ !! busy }
				>
					{ busy === 'verify'
						? __( 'Confirming with Google…', 'adbot' )
						: __( 'Verify this site', 'adbot' ) }
				</button>
			);
		}

		if ( selected && sitemap?.available ) {
			const sitemapLabel = submittedAt
				? __( 'Resubmit sitemap', 'adbot' )
				: __( 'Submit sitemap', 'adbot' );
			actions.push(
				<button
					key="sitemap"
					type="button"
					className={
						'adbot-btn ' + ( submittedAt ? 'adbot-btn--ghost' : 'adbot-btn--primary' )
					}
					onClick={ handleSubmitSitemap }
					disabled={ !! busy }
				>
					{ busy === 'sitemap' ? __( 'Submitting…', 'adbot' ) : sitemapLabel }
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
			title={ embedded ? __( 'Setup', 'adbot' ) : __( 'Google Search Console', 'adbot' ) }
			logoUrl={ embedded ? '' : logoUrl }
			state={ state }
			stateLabel={ embedded ? '' : stateLabel }
			description={ __(
				'Prove you own this site and keep your sitemap submitted, so Google can find and index your pages.',
				'adbot'
			) }
			actions={ actions }
		>
			{ error && <p className="adbot-error">{ error }</p> }
			{ notice && <p className="adbot-success">{ notice }</p> }

			{ ! granted && (
				<p className="adbot-channel-card__description">
					{ __(
						'Adbot needs permission to read and update Search Console for your Google account. You only grant this once.',
						'adbot'
					) }
				</p>
			) }

			{ granted && loading && ! data && (
				<div className="adbot-progress-bar">
					<span />
				</div>
			) }

			{ granted && data && (
				<>
					<SitePicker
						sites={ data.sites || [] }
						selected={ selected }
						busy={ busy === 'select' }
						onSelect={ handleSelect }
					/>

					<ChannelDetail label={ __( 'Property', 'adbot' ) }>
						{ selected || __( 'None selected', 'adbot' ) }
					</ChannelDetail>

					{ sitemap?.available ? (
						<ChannelDetail label={ __( 'Sitemap', 'adbot' ) }>
							{ sitemap.url }
						</ChannelDetail>
					) : (
						<p className="adbot-error">
							{ sitemap?.conflictingPlugin
								? sprintf(
										/* translators: %s: SEO plugin name. */
										__(
											'WordPress sitemaps are switched off, most likely by %s. Submit that plugin’s sitemap in Search Console directly.',
											'adbot'
										),
										sitemap.conflictingPlugin
								  )
								: __(
										'WordPress sitemaps are switched off on this site, so there is nothing to submit.',
										'adbot'
								  ) }
						</p>
					) }

					<ChannelDetail label={ __( 'Last submitted', 'adbot' ) }>
						{ formatWhen( submittedAt ) || __( 'Never', 'adbot' ) }
					</ChannelDetail>
				</>
			) }
		</ChannelCard>
	);
}
