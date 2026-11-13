<?php

namespace Adbot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publishes Google's site-ownership proof so the backend can claim the domain
 * on the user's behalf.
 *
 * One proof, two consumers: Search Console adds the property, and Merchant
 * Center claims the store website. Both accept the same META verification, so
 * this is built once and used twice.
 *
 * The backend hands us only the opaque `content` value — never a tag — so the
 * markup below is ours and is escaped here. Google fetches the site's home page
 * and looks for it in <head>.
 */
class Site_Verification {

	public const OPTION = 'adbot_site_verification_token';

	public function __construct() {
		// Priority 1, matching Snippet_Injector: verification must survive a
		// theme or optimizer that truncates late <head> output.
		add_action( 'wp_head', [ $this, 'inject_meta' ], 1 );
	}

	public function inject_meta(): void {
		$token = self::token();
		if ( '' === $token ) {
			return;
		}

		printf(
			'<meta name="google-site-verification" content="%s" />' . "\n",
			esc_attr( $token )
		);
	}

	public static function token(): string {
		return (string) get_option( self::OPTION, '' );
	}

	public static function set_token( string $token ): void {
		update_option( self::OPTION, $token, false );
	}

	/**
	 * The proof stays published after verification: Google re-checks
	 * periodically and drops ownership if the tag disappears, which would
	 * silently break both the Search Console property and the Merchant Center
	 * claim. So there is no cleanup on success — only on disconnect.
	 */
	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
