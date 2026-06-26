import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice, Modal, Button } from '@wordpress/components';
import { getAuthStatus, initiateOAuth, disconnect } from '../api/auth';
import { installSnippet, uninstallSnippet } from '../api/settings';
import ContainerSelector from '../components/ContainerSelector';
import GoogleSignInButton from '../components/GoogleSignInButton';
import SnippetCode from '../components/SnippetCode';

export default function Connect() {
	const [ authStatus, setAuthStatus ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ connecting, setConnecting ] = useState( false );
	const [ selectedContainer, setSelectedContainer ] = useState( null );
	const [ installing, setInstalling ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ confirmDisconnect, setConfirmDisconnect ] = useState( false );
	const [ confirmUninstall, setConfirmUninstall ] = useState( false );

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
		setError( '' );
		try {
			const { url } = await initiateOAuth();
			// Open OAuth in a popup.
			const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );
			if ( ! popup ) {
				window.location.href = url;
			}
		} catch ( err ) {
			setError( err?.message ? String( err.message ) : String( err ) );
		}
		setConnecting( false );
	};

	const handleDisconnect = async () => {
		setConfirmDisconnect( true );
	};

	const handleInstallSnippet = async ( container ) => {
		setInstalling( true );
		setError( '' );
		try {
			// The snippet uses the public GTM-XXXX id, not the numeric containerId.
			await installSnippet(
				container.publicId || container.containerId,
				container.path
			);
			setSelectedContainer( container );
			loadStatus();
		} catch ( err ) {
			setError( err?.message ? String( err.message ) : String( err ) );
		}
		setInstalling( false );
	};

	const handleUninstallSnippet = async () => {
		setConfirmUninstall( true );
	};

	if ( loading ) {
		return <p>{ __( 'Loading…', 'adbot' ) }</p>;
	}

	const connected = authStatus?.connected;
	const snippetActive = authStatus?.snippetActive;
	const externalDetected = authStatus?.snippetExternalDetected;
	const snippetContainerId = authStatus?.snippetContainerId;
	// An existing third-party snippet was found, so Adbot is intentionally not
	// injecting a duplicate — show it for reference rather than the picker.
	const showExisting = connected && externalDetected && !! snippetContainerId;

	return (
		<div className="adbot-connect">
			<h2>{ __( 'Connect Google account', 'adbot' ) }</h2>

			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }

			{ ! connected && (
				<div className="adbot-connect__section">
					<p>
						{ __(
							'Connect your Google account to access Tag Manager, Analytics, Ads, Merchant Center, and Business Profile.',
							'adbot'
						) }
					</p>
					<GoogleSignInButton
						variant="continue"
						onClick={ handleConnect }
						loading={ connecting }
						size="large"
					/>
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
							<strong>
								{ authStatus.account?.name || __( 'Google account', 'adbot' ) }
							</strong>
							<br />
							<span>{ authStatus.account?.email }</span>
						</div>
						<button className="button" onClick={ handleDisconnect }>
							{ __( 'Disconnect', 'adbot' ) }
						</button>
					</div>
				</div>
			) }

			{ connected && ! snippetActive && ! showExisting && (
				<div className="adbot-connect__section">
					<h3>{ __( 'Select GTM container', 'adbot' ) }</h3>
					<p>{ __( 'Choose the GTM container to install on this WordPress site.', 'adbot' ) }</p>
					<ContainerSelector
						onSelect={ handleInstallSnippet }
						selectedPath={ selectedContainer?.path }
					/>
					{ installing && <p>{ __( 'Installing snippet…', 'adbot' ) }</p> }
				</div>
			) }

			{ connected && snippetActive && (
				<div className="adbot-connect__section">
					<h3>{ __( 'GTM snippet installed', 'adbot' ) }</h3>
					<p>
						{ __( 'Container', 'adbot' ) }{' '}
						<strong>{ snippetContainerId }</strong>{' '}
						{ __( 'is active on your site. This is the exact code Adbot added:', 'adbot' ) }
					</p>
					<SnippetCode head={ authStatus.snippetHead } body={ authStatus.snippetBody } />
					<button className="button" onClick={ handleUninstallSnippet }>
						{ __( 'Remove snippet', 'adbot' ) }
					</button>
				</div>
			) }

			{ showExisting && (
				<div className="adbot-connect__section">
					<h3>{ __( 'Existing GTM snippet detected', 'adbot' ) }</h3>
					<SnippetCode
						head={ authStatus.snippetHead }
						body={ authStatus.snippetBody }
						note={ sprintf(
							/* translators: %s: GTM container id, e.g. GTM-XXXXXXX. */
							__(
								'A GTM snippet for %s was already on your site, so Adbot did not add a duplicate (a second copy would make every tag fire twice). For reference, the standard snippet for this container is:',
								'adbot'
							),
							snippetContainerId
						) }
					/>
					<button className="button" onClick={ handleUninstallSnippet }>
						{ __( 'Remove snippet', 'adbot' ) }
					</button>
				</div>
			) }

			{ confirmDisconnect && (
				<Modal
					title={ __( 'Disconnect Google account', 'adbot' ) }
					onRequestClose={ () => setConfirmDisconnect( false ) }
				>
					<p>{ __( 'Are you sure you want to disconnect your Google account?', 'adbot' ) }</p>
					<div className="adbot-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setConfirmDisconnect( false ) }
						>
							{ __( 'Cancel', 'adbot' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ async () => {
								setConfirmDisconnect( false );
								await disconnect();
								setAuthStatus( { connected: false } );
								setSelectedContainer( null );
							} }
						>
							{ __( 'Disconnect', 'adbot' ) }
						</Button>
					</div>
				</Modal>
			) }

			{ confirmUninstall && (
				<Modal
					title={ __( 'Remove GTM snippet', 'adbot' ) }
					onRequestClose={ () => setConfirmUninstall( false ) }
				>
					<p>{ __( 'Remove the GTM snippet from your site?', 'adbot' ) }</p>
					<div className="adbot-modal-actions">
						<Button
							variant="secondary"
							onClick={ () => setConfirmUninstall( false ) }
						>
							{ __( 'Cancel', 'adbot' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ async () => {
								setConfirmUninstall( false );
								await uninstallSnippet();
								setSelectedContainer( null );
								loadStatus();
							} }
						>
							{ __( 'Remove', 'adbot' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
}
