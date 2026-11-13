<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Consent;
use Adbot\Consent_Required_Exception;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Backend\Token_Store;
use Adbot\Backend\Site_Registration;
use Adbot\Channels\Site_Verification;
use Adbot\Channels\Sync;

/**
 * Thin proxy to the Adbot backend for Google OAuth.
 *
 * The plugin does not hold any Google credentials. It asks the backend for
 * an authorization URL, which redirects the browser through Google and
 * back to the backend's callback. On success the backend redirects the
 * browser to the admin page.
 */
class Auth_Controller extends REST_Controller {

	/**
	 * Scope bundles the plugin may ask for on top of the base GTM/GA4 grant.
	 * Mirrors WP_GOOGLE_SCOPE_SETS in the backend's lib/wp/google-oauth.ts —
	 * the backend re-validates, so this list is UX, not the security boundary.
	 */
	public const SCOPE_SETS = [ 'gsc', 'gmc' ];

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/auth/google', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'initiate_oauth' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				// Opt-in scope bundles for the channel cards. Names only, matched
				// against an allow-list here and again on the backend, so the UI
				// can never widen its own Google grant to an arbitrary scope.
				'scopes' => [
					'type'              => 'array',
					'items'             => [ 'type' => 'string' ],
					'sanitize_callback' => function ( $value ) {
						if ( ! is_array( $value ) ) {
							return [];
						}
						return array_values( array_intersect(
							array_map( 'sanitize_key', $value ),
							self::SCOPE_SETS
						) );
					},
					'validate_callback' => function ( $value ) {
						if ( ! is_array( $value ) ) {
							return false;
						}
						foreach ( $value as $set ) {
							if ( ! is_string( $set ) || ! in_array( $set, self::SCOPE_SETS, true ) ) {
								return false;
							}
						}
						return true;
					},
				],
			],
		] );

		register_rest_route( $this->namespace, '/auth/disconnect', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'disconnect' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/auth/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function initiate_oauth( WP_REST_Request $request ): WP_REST_Response {
		// Consent is granted when an admin explicitly starts the connect flow.
		update_option( Consent::OPTION, true );

		try {
			// Ensure we have a site_token before asking the backend for an auth URL.
			// An explicit Connect click must force a real attempt: do NOT use the
			// debounced maybe_register() here. If a prior background attempt failed,
			// its 60s debounce would make this click silently no-op and the user
			// would see "not finished registering" again with no retry. Calling
			// register_now() directly also lets the real failure reason (network
			// timeout, verification_failed, backend 5xx) surface to the UI below
			// instead of being swallowed and masked by a generic not_registered.
			if ( '' === Token_Store::get_site_token() ) {
				( new Site_Registration() )->register_now();
			}

			$body = [
				'return_url' => admin_url( 'admin.php?page=adbot&connected=1' ),
			];

			// Only send the key when bundles were asked for, so an ordinary
			// connect keeps the exact request shape the backend has always seen.
			$scopes = $request->get_param( 'scopes' );
			if ( is_array( $scopes ) && ! empty( $scopes ) ) {
				$body['scopes'] = array_values( $scopes );
			}

			$client = new Client();
			$result = $client->post( '/auth/google/start', $body );

			return new WP_REST_Response( [ 'url' => (string) ( $result['auth_url'] ?? '' ) ], 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'oauth_initiate', $e );
			return $this->backend_error( $e );
		}
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		try {
			( new Client() )->post( '/auth/disconnect' );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'oauth_disconnect', $e );
			// Fall through — always clear local state.
		}

		delete_option( 'adbot_snippet_active' );
		delete_option( 'adbot_snippet_container_id' );
		delete_option( 'adbot_snippet_container_path' );
		delete_option( 'adbot_onboarding' );
		// Clear the cached "Google connected" flags so a disconnect reflects
		// immediately (see Onboarding_Controller::is_google_connected()).
		delete_transient( 'adbot_google_connected_cache' );
		delete_option( 'adbot_google_connected_last' );
		delete_transient( 'adbot_containers' );

		// Channel selections belong to the Google account that was just
		// revoked, so they cannot be honoured any more. The ownership proof
		// goes too: leaving the meta tag published would keep asserting a claim
		// for an account this site is no longer connected to.
		delete_option( GSC_Controller::OPTION_SITE_URL );
		delete_option( GSC_Controller::OPTION_SITEMAP_URL );
		delete_option( GSC_Controller::OPTION_SUBMITTED_AT );
		delete_option( GSC_Controller::OPTION_VERIFIED_AT );
		delete_option( GMC_Controller::OPTION_ACCOUNT_NAME );
		delete_option( GMC_Controller::OPTION_ACCOUNT_LABEL );
		delete_option( GMC_Controller::OPTION_CLAIMED_AT );
		delete_option( GMC_Controller::OPTION_REGISTERED_AT );
		delete_transient( 'adbot_gsc_sites' );
		delete_transient( 'adbot_gmc_accounts' );
		delete_option( Insights_Controller::OPTION_GA4_PROPERTY_ID );
		delete_option( Insights_Controller::OPTION_GA4_PROPERTY_NAME );
		Insights_Controller::flush();
		Site_Verification::clear();
		Sync::clear();

		// The feed token is deliberately KEPT. Rotating it on every disconnect
		// would break a Merchant Center data source that the user may be about
		// to reconnect, and the URL is not a credential for the Google account.

		return new WP_REST_Response( [ 'disconnected' => true ], 200 );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$snippet_container_id = (string) get_option( 'adbot_snippet_container_id', '' );
		$snippet = [
			'snippetActive'           => (bool) get_option( 'adbot_snippet_active' ),
			'snippetExternalDetected' => (bool) get_option( 'adbot_snippet_external_detected' ),
			'snippetContainerId'      => $snippet_container_id,
			'snippetContainerPath'    => (string) get_option( 'adbot_snippet_container_path', '' ),
			// The exact code Adbot injects for this container, so the admin UI can
			// show the user precisely what is (or would be) on their site.
			'snippetHead'             => '' !== $snippet_container_id ? \Adbot\Tracking\Snippet_Injector::head_snippet( $snippet_container_id ) : '',
			'snippetBody'             => '' !== $snippet_container_id ? \Adbot\Tracking\Snippet_Injector::body_snippet( $snippet_container_id ) : '',
		];

		if ( '' === Token_Store::get_site_token() ) {
			return new WP_REST_Response( array_merge( [
				'connected' => false,
				'account'   => null,
				'scopes'    => null,
				'scopeSets' => [],
			], $snippet ), 200 );
		}

		try {
			$result = ( new Client() )->get( '/auth/status' );
			return new WP_REST_Response( array_merge( $result, $snippet ), 200 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'oauth_status', $e );
			return new WP_REST_Response( array_merge( [
				'connected' => false,
				'account'   => null,
				'scopes'    => null,
				'scopeSets' => [],
				'error'     => $e->getMessage(),
			], $snippet ), 200 );
		}
	}
}
