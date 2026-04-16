import { useState, useEffect, useCallback } from '@wordpress/element';
import { getAuthStatus, initiateOAuth, disconnect } from '../api/auth';
import { installSnippet, uninstallSnippet } from '../api/settings';
import ContainerSelector from '../components/ContainerSelector';

export default function Connect() {
	const [ authStatus, setAuthStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ connecting, setConnecting ] = useState( false );
	const [ selectedContainer, setSelectedContainer ] = useState( null );
	const [ installing, setInstalling ] = useState( false );

	const loadStatus = useCallback( () => {
		setLoading( true );
		getAuthStatus()
			.then( ( data ) => {
				setAuthStatus( data );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		loadStatus();

		// Listen for OAuth popup completion.
		const handleFocus = () => {
			setTimeout( loadStatus, 1000 );
		};
		window.addEventListener( 'focus', handleFocus );
		return () => window.removeEventListener( 'focus', handleFocus );
	}, [ loadStatus ] );

	const handleConnect = async () => {
		setConnecting( true );
		try {
			const { url } = await initiateOAuth();
			// Open OAuth in a popup.
			const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );
			if ( ! popup ) {
				window.location.href = url;
			}
		} catch ( err ) {
			alert( 'Failed to start OAuth: ' + ( err.message || err ) );
		}
		setConnecting( false );
	};

	const handleDisconnect = async () => {
		if ( ! confirm( 'Are you sure you want to disconnect your Google account?' ) ) {
			return;
		}
		await disconnect();
		setAuthStatus( { connected: false } );
		setSelectedContainer( null );
	};

	const handleInstallSnippet = async ( container ) => {
		setInstalling( true );
		try {
			await installSnippet( container.containerId, container.path );
			setSelectedContainer( container );
			loadStatus();
		} catch ( err ) {
			alert( 'Failed to install snippet: ' + ( err.message || err ) );
		}
		setInstalling( false );
	};

	const handleUninstallSnippet = async () => {
		if ( ! confirm( 'Remove the GTM snippet from your site?' ) ) {
			return;
		}
		await uninstallSnippet();
		setSelectedContainer( null );
		loadStatus();
	};

	if ( loading ) {
		return <p>Loading...</p>;
	}

	const connected = authStatus?.connected;
	const snippetActive = authStatus?.snippetActive;

	return (
		<div className="adbot-connect">
			<h2>Connect Google Account</h2>

			{ ! connected && (
				<div className="adbot-connect__section">
					<p>
						Connect your Google account to access Tag Manager, Analytics,
						Ads, Merchant Center, and Business Profile.
					</p>
					<button
						className="button button-primary button-hero"
						onClick={ handleConnect }
						disabled={ connecting }
					>
						{ connecting ? 'Opening Google...' : 'Connect with Google' }
					</button>
				</div>
			) }

			{ connected && (
				<div className="adbot-connect__section">
					<div className="adbot-connect__account">
						{ authStatus.account?.picture && (
							<img
								src={ authStatus.account.picture }
								alt=""
								className="adbot-connect__avatar"
							/>
						) }
						<div>
							<strong>{ authStatus.account?.name || 'Google Account' }</strong>
							<br />
							<span>{ authStatus.account?.email }</span>
						</div>
						<button className="button" onClick={ handleDisconnect }>
							Disconnect
						</button>
					</div>
				</div>
			) }

			{ connected && ! snippetActive && (
				<div className="adbot-connect__section">
					<h3>Select GTM Container</h3>
					<p>Choose the GTM container to install on this WordPress site.</p>
					<ContainerSelector
						onSelect={ handleInstallSnippet }
						selectedPath={ selectedContainer?.path }
					/>
					{ installing && <p>Installing snippet...</p> }
				</div>
			) }

			{ connected && snippetActive && (
				<div className="adbot-connect__section">
					<h3>GTM Snippet Installed</h3>
					<p>
						Container <strong>{ authStatus.snippetContainerId }</strong> is
						active on your site.
					</p>
					<button className="button" onClick={ handleUninstallSnippet }>
						Remove Snippet
					</button>
				</div>
			) }
		</div>
	);
}
