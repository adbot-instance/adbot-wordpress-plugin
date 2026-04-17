import { useOnboarding } from '../OnboardingProvider';

export default function StepWelcome() {
	const { advance } = useOnboarding();
	const siteName = window.adbotAdmin?.siteName || 'your site';

	return (
		<div className="adbot-step">
			<div className="adbot-step__eyebrow">Welcome</div>
			<h1 className="adbot-step__title">Let's get { siteName } tracking like a pro.</h1>
			<p className="adbot-step__lede">
				Adbot audits your Google Tag Manager setup, shows you what's missing, and — once you
				approve — publishes the fixes for you. No code. No copy-pasting.
			</p>

			<ul className="adbot-step__checklist">
				<li><strong>1. Connect</strong> your Google account</li>
				<li><strong>2. Match</strong> the right GTM container & GA4 property</li>
				<li><strong>3. Audit</strong> your current tracking</li>
				<li><strong>4. Review</strong> a short health report</li>
				<li><strong>5. Unlock</strong> automated fixes via Paystack</li>
				<li><strong>6. Apply</strong> — we publish changes to GTM for you</li>
			</ul>

			<div className="adbot-step__actions">
				<button
					type="button"
					className="adbot-btn adbot-btn--primary"
					onClick={ () => advance( 'connect' ) }
				>
					Get started
				</button>
			</div>
		</div>
	);
}
