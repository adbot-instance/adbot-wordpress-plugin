import { useEffect, useState, useCallback } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { getGA4Properties, selectGA4Property } from '../../api/ga4';
import { initiateOAuth } from '../../api/auth';
import PropertySelector from '../../components/PropertySelector';

function hostOf( url ) {
	return ( url || '' )
		.replace( /^https?:\/\//, '' )
		.replace( /\/.*$/, '' )
		.toLowerCase();
}

// Best match = a property whose web-stream URL shares a host with this site;
// otherwise the first property so the user always has a sensible default.
function bestMatch( accounts, siteUrl ) {
	const host = hostOf( siteUrl );
	let best = null;
	accounts.forEach( ( account ) => {
		account.properties.forEach( ( p ) => {
			const phost = hostOf( p.websiteUrl );
			const match =
				host && phost && ( phost.includes( host ) || host.includes( phost ) );
			if ( match && ! best ) {
				best = { ...p, accountName: account.name };
			}
		} );
	} );
	if ( ! best && accounts[ 0 ]?.properties?.[ 0 ] ) {
		best = { ...accounts[ 0 ].properties[ 0 ], accountName: accounts[ 0 ].name };
	}
	return best;
}

export default function StepGA4() {
	const { advance } = useOnboarding();
	const [ accounts, setAccounts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ suggestion, setSuggestion ] = useState( null );
	const [ showAll, setShowAll ] = useState( false );
	const [ saving, setSaving ] = useState( false );
	const [ savingPath, setSavingPath ] = useState( null );

	const siteUrl = window.adbotAdmin?.siteUrl || '';

	const load = useCallback( () => {
		setLoading( true );
		setError( null );
		return getGA4Properties()
			.then( ( data ) => {
				setAccounts( data );
				setSuggestion( bestMatch( data, siteUrl ) );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || 'Failed to load GA4 properties' );
				setLoading( false );
			} );
	}, [ siteUrl ] );

	useEffect( () => {
		load();
		// After the reconnect popup grants the new scopes, the window regains
		// focus — reload so the now-readable properties appear.
		const onFocus = () => setTimeout( load, 1000 );
		window.addEventListener( 'focus', onFocus );
		return () => window.removeEventListener( 'focus', onFocus );
	}, [ load ] );

	const confirm = async ( property ) => {
		setSaving( true );
		setSavingPath( property.path );
		setError( null );
		try {
			// Persist the chosen Measurement ID server-side; StepAudit reads it back
			// from /status and uses it for the setup plan (overriding URL discovery).
			await selectGA4Property( property.measurementId );
			await advance( 'audit' );
		} catch ( err ) {
			setError( err.message || 'Failed to select GA4 property' );
			setSaving( false );
			setSavingPath( null );
		}
	};

	const handleReconnect = async () => {
		setError( null );
		try {
			const { url } = await initiateOAuth();
			const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );
			if ( ! popup ) {
				window.location.href = url;
			}
		} catch ( err ) {
			setError( err.message || 'Failed to start Google sign-in' );
		}
	};

	if ( loading ) {
		return (
			<div className="adbot-step">
				<h1 className="adbot-step__title">Finding your GA4 properties…</h1>
				<div className="adbot-progress-bar">
					<span />
				</div>
			</div>
		);
	}

	// Most common failure during rollout: the connected token predates the new
	// Analytics scopes, so listing 403s. Offer a reconnect + retry, and always a
	// way forward (the audit derives a measurement id from the container's tags).
	if ( error ) {
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 3 of 7</div>
				<h1 className="adbot-step__title">Couldn’t load your GA4 properties</h1>
				<p className="adbot-error">{ error }</p>
				<p className="adbot-step__lede">
					This usually means Adbot needs permission to read Google Analytics. Reconnect
					to grant it, or continue — we’ll use the GA4 settings already in your container.
				</p>
				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ handleReconnect }
					>
						Reconnect Google
					</button>
					<button
						type="button"
						className="adbot-btn adbot-btn--ghost"
						onClick={ load }
					>
						Try again
					</button>
					<button
						type="button"
						className="adbot-btn adbot-btn--ghost"
						onClick={ () => advance( 'audit' ) }
					>
						Continue without it
					</button>
				</div>
			</div>
		);
	}

	if ( ! accounts.length ) {
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 3 of 7</div>
				<h1 className="adbot-step__title">No GA4 property to select</h1>
				<p className="adbot-step__lede">
					The connected Google account doesn’t have a Google Analytics 4 web property we
					can use. You can continue — we’ll use the GA4 configuration already in your GTM
					container — or create a property first.
				</p>
				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ () => advance( 'audit' ) }
					>
						Continue without selecting a property
					</button>
					<a
						className="adbot-btn adbot-btn--ghost"
						href="https://analytics.google.com"
						target="_blank"
						rel="noreferrer"
					>
						Create a GA4 property
					</a>
				</div>
			</div>
		);
	}

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 3 of 7</div>
			<h1 className="adbot-step__title">Select your GA4 property</h1>
			<p className="adbot-step__lede">
				We’ll set up tracking for this Google Analytics 4 property. Confirm the match
				for <strong>{ siteUrl }</strong> or pick a different one.
			</p>

			{ suggestion && ! showAll && (
				<div className="adbot-suggestion-card">
					<div>
						<div className="adbot-suggestion-card__label">GA4 property</div>
						<div className="adbot-suggestion-card__value">{ suggestion.name }</div>
						<div className="adbot-suggestion-card__meta">
							{ suggestion.measurementId }
							{ suggestion.accountName ? ` · ${ suggestion.accountName }` : '' }
						</div>
						{ suggestion.websiteUrl && (
							<div className="adbot-suggestion-card__meta">
								{ suggestion.websiteUrl }
							</div>
						) }
					</div>
					<div className="adbot-step__actions">
						<button
							type="button"
							className="adbot-btn adbot-btn--primary"
							onClick={ () => confirm( suggestion ) }
							disabled={ saving }
						>
							{ saving ? 'Saving…' : 'Use this property' }
						</button>
						<button
							type="button"
							className="adbot-btn adbot-btn--ghost"
							onClick={ () => setShowAll( true ) }
						>
							Choose different
						</button>
					</div>
				</div>
			) }

			{ showAll && (
				<>
					<PropertySelector
						onSelect={ confirm }
						selectedPath={ savingPath }
						busy={ saving }
					/>
					<div className="adbot-step__actions">
						<button
							type="button"
							className="adbot-btn adbot-btn--ghost"
							onClick={ () => setShowAll( false ) }
						>
							Back to suggestion
						</button>
					</div>
				</>
			) }
		</div>
	);
}
