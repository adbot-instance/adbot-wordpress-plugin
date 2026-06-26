import { useEffect, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { getContainers } from '../../api/audit';
import { installSnippet } from '../../api/settings';
import ContainerSelector from '../../components/ContainerSelector';
import SnippetCode from '../../components/SnippetCode';

function describeMethod( method ) {
	switch ( method ) {
		case 'loader':
		case 'inline':
			return 'the page <head> script';
		case 'noscript':
			return 'the <noscript> fallback';
		case 'gtm4wp':
			return 'the GTM4WP plugin';
		case 'site_kit':
			return 'Google Site Kit';
		case 'monsterinsights':
			return 'MonsterInsights';
		case 'wpcode':
			return 'a code-snippet plugin';
		case 'consent_deferred':
			return 'a consent / cookie plugin';
		default:
			return 'an existing install';
	}
}

function bestMatch( accounts, siteUrl ) {
	const host = siteUrl.replace( /^https?:\/\//, '' ).replace( /\/.*$/, '' ).toLowerCase();
	let best = null;
	accounts.forEach( ( account ) => {
		account.containers.forEach( ( c ) => {
			const domains = ( c.domainName || [] ).map( ( d ) => d.toLowerCase() );
			const match =
				domains.some( ( d ) => d.includes( host ) || host.includes( d ) ) ||
				( c.name || '' ).toLowerCase().includes( host.split( '.' )[ 0 ] );
			if ( match && ! best ) {
				best = { ...c, accountName: account.name };
			}
		} );
	} );
	if ( ! best && accounts[ 0 ]?.containers?.[ 0 ] ) {
		best = { ...accounts[ 0 ].containers[ 0 ], accountName: accounts[ 0 ].name };
	}
	return best;
}

export default function StepProperty() {
	const { advance } = useOnboarding();
	const [ accounts, setAccounts ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ suggestion, setSuggestion ] = useState( null );
	const [ showAll, setShowAll ] = useState( false );
	const [ installing, setInstalling ] = useState( false );
	const [ installingPath, setInstallingPath ] = useState( null );
	const [ result, setResult ] = useState( null );

	const siteUrl = window.adbotAdmin?.siteUrl || '';

	useEffect( () => {
		getContainers()
			.then( ( data ) => {
				setAccounts( data );
				setSuggestion( bestMatch( data, siteUrl ) );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || 'Failed to load GTM containers' );
				setLoading( false );
			} );
	}, [ siteUrl ] );

	const confirm = async ( container ) => {
		setInstalling( true );
		setInstallingPath( container.path );
		setError( null );
		try {
			// The snippet uses the public GTM-XXXX id, not the numeric containerId.
			const res = await installSnippet(
				container.publicId || container.containerId,
				container.path
			);
			// Always show the confirmation screen with the exact installed code —
			// whether Adbot injected its own snippet or detected a matching one
			// already on the site (in which case it won't add a duplicate).
			setResult( res );
			setInstalling( false );
		} catch ( err ) {
			setError( err.message || 'Failed to install snippet' );
			setInstalling( false );
			setInstallingPath( null );
		}
	};

	if ( result ) {
		const method = result.detection?.method;
		const isExternal = !! result.externalDetected;
		return (
			<div className="adbot-step">
				<div className="adbot-step__eyebrow">Step 2 of 7</div>
				<h1 className="adbot-step__title">
					{ isExternal ? 'Existing GTM snippet found' : 'GTM snippet installed' }
				</h1>
				<p className="adbot-step__lede">
					{ isExternal ? (
						<>
							<strong>{ result.containerId }</strong> is already installed on{ ' ' }
							<strong>{ siteUrl }</strong>
							{ method && method !== 'none' ? ` via ${ describeMethod( method ) }` : '' }.
							Adbot won't add a second copy — a duplicate would make every tag fire
							twice. We'll use the snippet that's already there.
						</>
					) : (
						<>
							Adbot added container <strong>{ result.containerId }</strong> to{ ' ' }
							<strong>{ siteUrl }</strong>. This is the exact code now on your site:
						</>
					) }
				</p>
				<SnippetCode
					head={ result.snippetHead }
					body={ result.snippetBody }
					note={
						isExternal
							? 'For reference, the standard snippet for this container:'
							: null
					}
				/>
				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ () => advance( 'ga4' ) }
					>
						Continue
					</button>
					<button
						type="button"
						className="adbot-btn adbot-btn--ghost"
						onClick={ () => {
							setResult( null );
							setInstallingPath( null );
						} }
					>
						Use a different container
					</button>
				</div>
			</div>
		);
	}

	if ( loading ) {
		return (
			<div className="adbot-step">
				<h1 className="adbot-step__title">Finding your GTM container…</h1>
				<div className="adbot-progress-bar"><span /></div>
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="adbot-step">
				<p className="adbot-error">{ error }</p>
			</div>
		);
	}

	if ( ! accounts.length ) {
		return (
			<div className="adbot-step">
				<h1 className="adbot-step__title">No GTM containers found</h1>
				<p>Your Google account doesn't have access to any Tag Manager containers yet. Create one at <a href="https://tagmanager.google.com" target="_blank" rel="noreferrer">tagmanager.google.com</a>, then come back.</p>
			</div>
		);
	}

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 2 of 7</div>
			<h1 className="adbot-step__title">Use this GTM container?</h1>
			<p className="adbot-step__lede">
				We matched the best container for <strong>{ siteUrl }</strong>. Confirm or pick a
				different one.
			</p>

			{ suggestion && ! showAll && (
				<div className="adbot-suggestion-card">
					<div>
						<div className="adbot-suggestion-card__label">GTM container</div>
						<div className="adbot-suggestion-card__value">{ suggestion.name }</div>
						<div className="adbot-suggestion-card__meta">
							ID { suggestion.containerId } · { suggestion.accountName }
						</div>
						{ suggestion.domainName?.length > 0 && (
							<div className="adbot-suggestion-card__meta">
								Domains: { suggestion.domainName.join( ', ' ) }
							</div>
						) }
					</div>
					<div className="adbot-step__actions">
						<button
							type="button"
							className="adbot-btn adbot-btn--primary"
							onClick={ () => confirm( suggestion ) }
							disabled={ installing }
						>
							{ installing ? 'Installing…' : 'Use this container' }
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
					<ContainerSelector
						onSelect={ confirm }
						selectedPath={ installingPath }
						busy={ installing }
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
