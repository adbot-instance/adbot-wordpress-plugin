import { useEffect, useState } from '@wordpress/element';
import { useOnboarding } from '../OnboardingProvider';
import { initializePayment, verifyPayment } from '../../api/payments';

const PAYSTACK_SCRIPT = 'https://js.paystack.co/v1/inline.js';

function loadPaystack() {
	if ( window.PaystackPop ) return Promise.resolve( window.PaystackPop );
	return new Promise( ( resolve, reject ) => {
		const existing = document.querySelector( `script[src="${ PAYSTACK_SCRIPT }"]` );
		if ( existing ) {
			existing.addEventListener( 'load', () => resolve( window.PaystackPop ) );
			existing.addEventListener( 'error', reject );
			return;
		}
		const s = document.createElement( 'script' );
		s.src = PAYSTACK_SCRIPT;
		s.async = true;
		s.onload = () => resolve( window.PaystackPop );
		s.onerror = reject;
		document.head.appendChild( s );
	} );
}

export default function StepPay() {
	const { advance } = useOnboarding();
	const [ loading, setLoading ] = useState( false );
	const [ verifying, setVerifying ] = useState( false );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		loadPaystack().catch( () =>
			setError( 'Could not load Paystack. Check your connection and retry.' )
		);
	}, [] );

	const price = window.adbotAdmin?.fixPrice || 0;
	const currency = window.adbotAdmin?.currency || 'ZAR';

	const handlePay = async () => {
		setError( null );
		setLoading( true );
		try {
			const init = await initializePayment();
			const PaystackPop = await loadPaystack();
			if ( ! PaystackPop ) throw new Error( 'Paystack failed to load.' );
			if ( ! init.publicKey ) {
				throw new Error(
					'Paystack public key is not configured. Add PAYSTACK_PUBLIC_KEY to your .env.'
				);
			}

			const handler = PaystackPop.setup( {
				key: init.publicKey,
				email: init.email,
				amount: init.amount,
				currency: init.currency,
				ref: init.reference,
				onClose: () => {
					setLoading( false );
				},
				callback: ( response ) => {
					setVerifying( true );
					verifyPayment( response.reference )
						.then( () => advance( 'apply' ) )
						.catch( ( err ) =>
							setError( err.message || 'Verification failed.' )
						)
						.finally( () => {
							setVerifying( false );
							setLoading( false );
						} );
				},
			} );
			handler.openIframe();
		} catch ( err ) {
			setError( err.message || 'Could not start payment.' );
			setLoading( false );
		}
	};

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Step 5 of 6</div>
			<h1 className="adbot-step__title">Unlock automated fixes</h1>
			<p className="adbot-step__lede">
				One-time payment. Adbot writes every fix to your GTM workspace and publishes a new
				container version. You can always revert via GTM's version history.
			</p>

			<div className="adbot-price-card">
				<div className="adbot-price-card__amount">
					{ currency } { price }
				</div>
				<div className="adbot-price-card__details">
					<ul>
						<li>Full detailed report unlocked</li>
						<li>All fixes applied to your GTM container</li>
						<li>Published to a new GTM version automatically</li>
					</ul>
				</div>
			</div>

			{ error && <p className="adbot-error">{ error }</p> }

			<div className="adbot-step__actions">
				<button
					type="button"
					className="adbot-btn adbot-btn--primary adbot-btn--large"
					onClick={ handlePay }
					disabled={ loading || verifying }
				>
					{ verifying
						? 'Confirming payment…'
						: loading
							? 'Opening Paystack…'
							: `Pay ${ currency } ${ price } with Paystack` }
				</button>
			</div>
		</div>
	);
}
