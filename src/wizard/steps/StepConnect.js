import { useEffect, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOnboarding } from '../OnboardingProvider';
import { getAuthStatus, initiateOAuth } from '../../api/auth';
import GoogleSignInButton from '../../components/GoogleSignInButton';

export default function StepConnect() {
	const { advance, refresh } = useOnboarding();
	const [ connecting, setConnecting ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ connected, setConnected ] = useState( false );
	const [ account, setAccount ] = useState( null );
	const [ advanceFailed, setAdvanceFailed ] = useState( false );
	const popupRef = useRef( null );
	const advancedRef = useRef( false );

	// Watch for the Google connection becoming live: poll the backend, react to
	// window focus (popup-blocked / full-page fallback), and react to the popup
	// posting back when it returns from the OAuth round-trip.
	useEffect( () => {
		let cancelled = false;
		const poll = () => {
			getAuthStatus()
				.then( ( data ) => {
					if ( cancelled ) {
						return;
					}
					// Drive advancement off `connected` alone. The backend can
					// return `connected: true` with `account: null` (the account
					// lookup needs Google userinfo scopes the audit flow doesn't
					// request), so gating on `account` would hang the wizard here.
					if ( data?.connected ) {
						setConnected( true );
						setAccount( data.account || null );
					}
				} )
				.catch( () => {} );
		};
		poll();

		const handleFocus = () => setTimeout( poll, 800 );
		const handleMessage = ( event ) => {
			if ( event.origin !== window.location.origin ) {
				return;
			}
			const data = event.data || {};
			if ( data.type !== 'adbot:oauth-result' ) {
				return;
			}
			if ( data.error ) {
				setConnecting( false );
				setError(
					__(
						'Google sign-in did not complete. Please try connecting again.',
						'adbot'
					)
				);
				return;
			}
			// Don't trust the popup's word — confirm the connection server-side.
			poll();
		};

		window.addEventListener( 'focus', handleFocus );
		window.addEventListener( 'message', handleMessage );
		const timer = setInterval( poll, 2500 );
		return () => {
			cancelled = true;
			window.removeEventListener( 'focus', handleFocus );
			window.removeEventListener( 'message', handleMessage );
			clearInterval( timer );
		};
	}, [] );

	// Once connected: close any lingering popup and continue automatically into
	// the property / GTM-snippet step. Guarded so it only fires once.
	useEffect( () => {
		if ( ! connected || advancedRef.current ) {
			return;
		}
		advancedRef.current = true;
		if ( popupRef.current && ! popupRef.current.closed ) {
			popupRef.current.close();
		}
		( async () => {
			try {
				await refresh();
				await advance( 'property' );
			} catch {
				// Stay on this step and offer a manual continue instead of hanging.
				advancedRef.current = false;
				setAdvanceFailed( true );
			}
		} )();
	}, [ connected, advance, refresh ] );

	const handleConnect = async () => {
		setConnecting( true );
		setError( null );
		try {
			const { url } = await initiateOAuth();
			const popup = window.open(
				url,
				'adbot-oauth',
				'width=600,height=700'
			);
			popupRef.current = popup;
			if ( ! popup ) {
				// Popup blocked: fall back to a full-page redirect. We return to
				// this page with the OAuth result params and the poll picks it up.
				window.location.href = url;
			}
		} catch ( err ) {
			setError(
				err.message || __( 'Failed to start Google sign-in', 'adbot' )
			);
			setConnecting( false );
		}
	};

	const handleContinue = async () => {
		if ( advancedRef.current ) {
			return;
		}
		advancedRef.current = true;
		setAdvanceFailed( false );
		try {
			await refresh();
			await advance( 'property' );
		} catch {
			advancedRef.current = false;
			setAdvanceFailed( true );
		}
	};

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">
				{ sprintf(
					/* translators: 1: current step number after Welcome, 2: total setup steps (excluding Welcome). */
					__( 'Step %1$d of %2$d', 'adbot' ),
					1,
					7
				) }
			</div>
			<h1 className="adbot-step__title">
				{ __( 'Connect your Google account', 'adbot' ) }
			</h1>
			<p className="adbot-step__lede">
				{ __(
					'Read and write access to Google Tag Manager, Analytics, Ads, Merchant Center, and Business Profile is required so we can audit and fix your tracking.',
					'adbot'
				) }
			</p>

			{ error && <p className="adbot-error">{ error }</p> }

			{ ! connected && (
				<div className="adbot-step__actions">
					<GoogleSignInButton
						variant="continue"
						onClick={ handleConnect }
						loading={ connecting }
						size="large"
					/>
				</div>
			) }

			{ connected && (
				<div className="adbot-account-card">
					{ account?.picture && (
						<img src={ account.picture } alt="" />
					) }
					<div>
						<strong>
							{ account?.name ||
								__( 'Google account connected', 'adbot' ) }
						</strong>
						{ account?.email && <span>{ account.email }</span> }
					</div>
					{ advanceFailed ? (
						<button
							type="button"
							className="adbot-btn adbot-btn--primary"
							onClick={ handleContinue }
						>
							{ __( 'Continue', 'adbot' ) }
						</button>
					) : (
						<span className="adbot-account-card__status">
							{ __( 'Connected — continuing…', 'adbot' ) }
						</span>
					) }
				</div>
			) }
		</div>
	);
}
