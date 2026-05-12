import { __ } from '@wordpress/i18n';
import { useOnboarding } from '../OnboardingProvider';

function getBrand() {
	if ( typeof window === 'undefined' || ! window.adbotAdmin ) {
		return { logoUrl: '', name: __( 'Tracking', 'adbot' ), tagline: '' };
	}
	const admin = window.adbotAdmin;
	const b = admin.brand || {};
	return {
		logoUrl: b.logoUrl || admin.images?.adbot || '',
		name: b.name || __( 'Tracking', 'adbot' ),
		tagline: b.tagline || '',
	};
}

export default function StepWelcome() {
	const { advance } = useOnboarding();
	const brand = getBrand();

	return (
		<div className="adbot-step">
			<div className="adbot-step__welcome-hero">
				{ brand.logoUrl ? (
					<img
						src={ brand.logoUrl }
						alt=""
						className="adbot-step__welcome-logo"
						width={ 72 }
						height={ 72 }
					/>
				) : null }
				<div className="adbot-step__welcome-headlines">
					<div className="adbot-step__eyebrow">{ __( 'Welcome', 'adbot' ) }</div>
					<p className="adbot-step__brand-name">{ brand.name }</p>
					{ brand.tagline ? (
						<p className="adbot-step__brand-tagline">{ brand.tagline }</p>
					) : null }
				</div>
			</div>

			<h1 className="adbot-step__title">
				{ __( 'Let’s get your site tracking like a pro.', 'adbot' ) }
			</h1>
			<p className="adbot-step__lede">
				{ __(
					'We audit your Google Tag Manager setup, show you what’s missing, and publish the fixes for you after you approve. No code. No copy-pasting.',
					'adbot'
				) }
			</p>

			<ul className="adbot-step__checklist">
				<li>
					<strong>{ __( '1. Connect', 'adbot' ) }</strong>{ ' ' }
					{ __( 'your Google account.', 'adbot' ) }
				</li>
				<li>
					<strong>{ __( '2. Match', 'adbot' ) }</strong>{ ' ' }
					{ __( 'the right GTM container and GA4 property.', 'adbot' ) }
				</li>
				<li>
					<strong>{ __( '3. Audit', 'adbot' ) }</strong>{ ' ' }
					{ __( 'your current tracking.', 'adbot' ) }
				</li>
				<li>
					<strong>{ __( '4. Review', 'adbot' ) }</strong>{ ' ' }
					{ __( 'a short health report.', 'adbot' ) }
				</li>
				<li>
					<strong>{ __( '5. Complete payment', 'adbot' ) }</strong>{ ' ' }
					{ __( 'to unlock the proposed changes to your GTM container.', 'adbot' ) }
				</li>
				<li>
					<strong>{ __( '6. Apply.', 'adbot' ) }</strong>{ ' ' }
					{ __( 'We publish changes to GTM for you.', 'adbot' ) }
				</li>
			</ul>

			<div className="adbot-step__actions">
				<button
					type="button"
					className="adbot-btn adbot-btn--primary"
					onClick={ () => advance( 'connect' ) }
				>
					{ __( 'Get started', 'adbot' ) }
				</button>
			</div>
		</div>
	);
}
