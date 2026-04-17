import { useEffect, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { getAuthStatus, initiateOAuth } from '../../api/auth';

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
			<div className="adbot-step__eyebrow">Step 1 of 6</div>
			<h1 className="adbot-step__title">Connect your Google account</h1>
			<p className="adbot-step__lede">
				Adbot needs read & write access to Google Tag Manager, Analytics, Ads, Merchant Center,
				and Business Profile so it can audit and fix your tracking.
			</p>

			{ error && <p className="adbot-error">{ error }</p> }

			{ ! account && (
				<div className="adbot-step__actions">
					<button
						type="button"
						className="adbot-btn adbot-btn--primary adbot-btn--google"
						onClick={ handleConnect }
						disabled={ connecting }
					>
						{ connecting ? 'Opening Google…' : 'Continue with Google' }
					</button>
				</div>
			) }

			{ account && (
				<div className="adbot-account-card">
					{ account.picture && <img src={ account.picture } alt="" /> }
					<div>
						<strong>{ account.name || 'Google account' }</strong>
						<span>{ account.email }</span>
					</div>
					<button
						type="button"
						className="adbot-btn adbot-btn--primary"
						onClick={ handleContinue }
					>
						Continue
					</button>
				</div>
			) }
		</div>
	);
}
