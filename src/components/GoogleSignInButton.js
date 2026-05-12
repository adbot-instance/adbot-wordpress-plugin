import { __ } from '@wordpress/i18n';

/**
 * Custom-styled control aligned with Google's Sign in with Google branding guidelines
 * (approved wording, multicolor G mark, light neutral chrome — not site primary orange).
 *
 * @see https://developers.google.com/identity/branding-guidelines
 */
export default function GoogleSignInButton( {
	onClick,
	disabled,
	loading,
	variant = 'signin',
	size = 'default',
} ) {
	const label =
		variant === 'continue'
			? __( 'Continue with Google', 'adbot' )
			: __( 'Sign in with Google', 'adbot' );

	return (
		<button
			type="button"
			className={
				size === 'large'
					? 'adbot-google-signin-btn adbot-google-signin-btn--large'
					: 'adbot-google-signin-btn'
			}
			onClick={ onClick }
			disabled={ disabled || loading }
			aria-busy={ !! loading }
		>
			<span className="adbot-google-signin-btn__icon" aria-hidden="true">
				<svg
					xmlns="http://www.w3.org/2000/svg"
					viewBox="0 0 48 48"
					width="18"
					height="18"
					focusable="false"
				>
					<path
						fill="#FFC107"
						d="M43.6 20.5H42V20H24v8h11.3C33.8 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6 29.2 4 24 4 13 4 4 13 4 24s9 20 20 20c11 0 20-9 20-20 0-1.2-.1-2.3-.4-3.5z"
					/>
					<path
						fill="#FF3D00"
						d="M6.3 14.7l6.6 4.8C14.5 15.6 18.9 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34 6 29.2 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"
					/>
					<path
						fill="#4CAF50"
						d="M24 44c5 0 9.6-1.9 13-5l-6-5.1c-2 1.4-4.4 2.1-7 2.1-5.3 0-9.8-3.4-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"
					/>
					<path
						fill="#1976D2"
						d="M43.6 20.5H42V20H24v8h11.3c-.7 2-2 3.7-3.7 4.9l6.1 5.1c4.3-3.9 6.8-9.7 6.3-16-.1-.5-.1-1-.1-1.5z"
					/>
				</svg>
			</span>
			<span className="adbot-google-signin-btn__label">
				{ loading
					? __( 'Opening Google…', 'adbot' )
					: label }
			</span>
		</button>
	);
}
