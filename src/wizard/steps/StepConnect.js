import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { useOnboarding } from '../OnboardingProvider';
import { getAuthStatus, initiateOAuth } from '../../api/auth';
import GoogleSignInButton from '../../components/GoogleSignInButton';

export default function StepConnect() {
	const { advance, refresh } = useOnboarding();
	const [ connecting, setConnecting ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ account, setAccount ] = useState( null );

	useEffect( () => {
		const poll = () => {
			getAuthStatus().then( ( data ) => {
				if ( data?.connected ) {
					setAccount( data.account );
				}
			} );
		};
		poll();
		const handleFocus = () => setTimeout( poll, 800 );
		window.addEventListener( 'focus', handleFocus );
		const timer = setInterval( poll, 2500 );
		return () => {
			window.removeEventListener( 'focus', handleFocus );
			clearInterval( timer );
		};
	}, [] );

	const handleConnect = async () => {
		setConnecting( true );
		setError( null );
		try {
			const { url } = await initiateOAuth();
			const popup = window.open( url, 'adbot-oauth', 'width=600,height=700' );
			if ( ! popup ) {
				window.location.href = url;
			}
		} catch ( err ) {
			setError( err.message || 'Failed to start OAuth' );
		}
		setConnecting( false );
	};

	const handleContinue = async () => {
		await refresh();
		await advance( 'property' );
	};

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">
				{ sprintf(
					/* translators: 1: current step number after Welcome, 2: total setup steps (excluding Welcome). */
					__( 'Step %1$d of %2$d', 'adbot' ),
					1,
					6
				) }
			</div>
			<h1 className="adbot-step__title">{ __( 'Connect your Google account', 'adbot' ) }</h1>
			<p className="adbot-step__lede">
				{ __(
					'Read and write access to Google Tag Manager, Analytics, Ads, Merchant Center, and Business Profile is required so we can audit and fix your tracking.',
					'adbot'
				) }
			</p>

			{ error && <p className="adbot-error">{ error }</p> }

			{ ! account && (
				<div className="adbot-step__actions">
					<GoogleSignInButton
						variant="continue"
						onClick={ handleConnect }
						loading={ connecting }
						size="large"
					/>
				</div>
			) }

			{ account && (
				<div className="adbot-account-card">
					{ account.picture && <img src={ account.picture } alt="" /> }
					<div>
						<strong>{ account.name || __( 'Google account', 'adbot' ) }</strong>
						<span>{ account.email }</span>
					</div>
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ handleContinue }
					>
						{ __( 'Continue', 'adbot' ) }
					</button>
				</div>
			) }
		</div>
	);
}
