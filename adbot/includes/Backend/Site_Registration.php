<?php

namespace Adbot\Backend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Adbot\Consent;

/**
 * Registers this WordPress site with the Adbot backend and stores the
 * returned site_token encrypted at rest. Runs automatically as soon as an
 * admin has granted consent — no configuration required from the user.
 */
class Site_Registration {
	private const TRANSIENT_CHALLENGE = 'adbot_site_challenge';
	private const TRANSIENT_ATTEMPT   = 'adbot_site_register_attempt';

	public function hook(): void {
		// Attempt registration whenever an admin visits an Adbot admin page
		// and consent is granted but a site_token is missing.
		add_action( 'admin_init', [ $this, 'maybe_register' ] );
	}

	public function maybe_register(): void {
		if ( ! Consent::has_consent() ) {
			return;
		}
		if ( '' !== Token_Store::get_site_token() ) {
			return;
		}
		// Debounce: only try at most once every 60 seconds.
		if ( get_transient( self::TRANSIENT_ATTEMPT ) ) {
			return;
		}
		set_transient( self::TRANSIENT_ATTEMPT, 1, MINUTE_IN_SECONDS );

		try {
			$this->register_now();
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[adbot][site_register] ' . $e->getMessage() );
			}
		}
	}

	public function register_now(): void {
		$client = new Client();
		$nonce  = wp_generate_password( 32, false );

		$register = $client->unauthenticated_post( '/sites/register', [
			'site_url'       => get_site_url(),
			'site_name'      => get_bloginfo( 'name' ),
			'admin_email'    => get_option( 'admin_email' ),
			'wp_version'     => get_bloginfo( 'version' ),
			'plugin_version' => defined( 'ADBOT_VERSION' ) ? ADBOT_VERSION : '',
			'nonce'          => $nonce,
		] );

		$site_id = (string) ( $register['site_id'] ?? '' );
		if ( '' === $site_id ) {
			throw new Backend_Exception( 'invalid_response', 'Missing site_id in register response.', 502 );
		}

		Token_Store::set_site_id( $site_id );
		set_transient( self::TRANSIENT_CHALLENGE, $nonce, 10 * MINUTE_IN_SECONDS );

		$verify = $client->unauthenticated_post( '/sites/verify', [
			'site_id' => $site_id,
			'nonce'   => $nonce,
		] );

		$token = (string) ( $verify['site_token'] ?? '' );
		if ( '' === $token ) {
			throw new Backend_Exception( 'verification_failed', 'Missing site_token in verify response.', 502 );
		}

		Token_Store::set_site_token( $token );
		delete_transient( self::TRANSIENT_CHALLENGE );
	}

	/**
	 * Public verification endpoint: `/wp-json/adbot/v1/verify?challenge=…`.
	 *
	 * Called server-to-server by the backend during /sites/verify. Returns
	 * the nonce if the challenge matches the transient we set during
	 * register_now(). No authentication — proof-of-origin is the secret
	 * nonce value itself.
	 */
	public static function handle_verify_request( \WP_REST_Request $request ): \WP_REST_Response {
		$challenge = (string) $request->get_param( 'challenge' );
		$stored    = (string) get_transient( self::TRANSIENT_CHALLENGE );

		if ( '' === $challenge || '' === $stored || ! hash_equals( $stored, $challenge ) ) {
			return new \WP_REST_Response(
				[ 'code' => 'challenge_mismatch', 'message' => 'Challenge does not match.' ],
				403
			);
		}

		return new \WP_REST_Response( [ 'nonce' => $stored ], 200 );
	}
}
