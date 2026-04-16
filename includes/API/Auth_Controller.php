<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Auth\OAuth;
use Adbot\Auth\Encryption;
use Adbot\Database\Supabase;

class Auth_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/auth/google', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'initiate_oauth' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/auth/google/callback', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_callback' ],
			'permission_callback' => '__return_true', // Public — Google redirects here.
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
		$oauth = new OAuth();

		// Generate a state token for CSRF protection, store in a transient.
		$state = wp_generate_password( 32, false );
		set_transient( 'adbot_oauth_state_' . $state, true, 600 );

		$auth_url = $oauth->get_auth_url( $state );

		return new WP_REST_Response( [ 'url' => $auth_url ], 200 );
	}

	public function handle_callback( WP_REST_Request $request ): WP_REST_Response {
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );

		if ( ! $code || ! $state ) {
			return $this->error_response( 'Missing code or state parameter.', 400 );
		}

		// Verify CSRF state.
		$valid = get_transient( 'adbot_oauth_state_' . $state );
		delete_transient( 'adbot_oauth_state_' . $state );

		if ( ! $valid ) {
			return $this->error_response( 'Invalid or expired state token.', 403 );
		}

		try {
			$oauth  = new OAuth();
			$tokens = $oauth->exchange_code( $code );

			if ( empty( $tokens['access_token'] ) ) {
				return $this->error_response( 'Failed to obtain access token.', 500 );
			}

			// Fetch Google user info.
			$user_info = $oauth->get_user_info( $tokens['access_token'] );

			// Encrypt tokens.
			$encryption       = new Encryption();
			$access_token_enc  = $encryption->encrypt( $tokens['access_token'] );
			$refresh_token_enc = ! empty( $tokens['refresh_token'] )
				? $encryption->encrypt( $tokens['refresh_token'] )
				: '';

			$token_expiry = ! empty( $tokens['expires_in'] )
				? gmdate( 'Y-m-d\TH:i:s\Z', time() + (int) $tokens['expires_in'] )
				: gmdate( 'Y-m-d\TH:i:s\Z', time() + 3600 );

			$scopes = implode( ' ', $oauth->get_scopes() );

			$supabase = new Supabase();

			// Upsert Account by googleSub.
			$account = $supabase->upsert_account( [
				'googleSub' => $user_info['id'],
				'email'     => $user_info['email'],
				'name'      => $user_info['name'] ?? null,
				'picture'   => $user_info['picture'] ?? null,
			] );

			$account_id = $account['id'];

			// Upsert GoogleConnection.
			$supabase->upsert_google_connection( [
				'accountId'       => $account_id,
				'accessTokenEnc'  => $access_token_enc,
				'refreshTokenEnc' => $refresh_token_enc,
				'tokenExpiry'     => $token_expiry,
				'scopes'          => $scopes,
			] );

			// Create/update WordPressSite record.
			$site = $supabase->upsert_wordpress_site( [
				'site_url'   => get_site_url(),
				'site_name'  => get_bloginfo( 'name' ),
				'wp_version' => get_bloginfo( 'version' ),
				'account_id' => $account_id,
			] );

			// Store account ID locally for quick lookups.
			update_option( 'adbot_account_id', $account_id );

			// Store site ID if returned.
			if ( ! empty( $site['id'] ) ) {
				update_option( 'adbot_site_id', $site['id'] );
			}

			// Redirect back to the admin page.
			$redirect_url = admin_url( 'admin.php?page=adbot&connected=1' );

			// Return HTML that closes the popup and refreshes the parent.
			$html = '<!DOCTYPE html><html><head><title>Connecting...</title></head><body>';
			$html .= '<script>if(window.opener){window.opener.location.href="' . esc_url( $redirect_url ) . '";window.close();}else{window.location.href="' . esc_url( $redirect_url ) . '";}</script>';
			$html .= '</body></html>';

			// Send raw HTML response.
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		} catch ( \Exception $e ) {
			return $this->error_response( 'OAuth failed: ' . $e->getMessage(), 500 );
		}
	}

	public function disconnect( WP_REST_Request $request ): WP_REST_Response {
		$account_id = get_option( 'adbot_account_id' );

		if ( $account_id ) {
			$supabase = new Supabase();
			$supabase->delete_google_connection( $account_id );
		}

		delete_option( 'adbot_account_id' );
		delete_option( 'adbot_site_id' );
		delete_option( 'adbot_snippet_active' );
		delete_option( 'adbot_snippet_container_id' );

		return new WP_REST_Response( [ 'disconnected' => true ], 200 );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$account_id = get_option( 'adbot_account_id' );

		if ( ! $account_id ) {
			return new WP_REST_Response( [
				'connected'          => false,
				'account'            => null,
				'snippetActive'      => (bool) get_option( 'adbot_snippet_active' ),
				'snippetContainerId' => get_option( 'adbot_snippet_container_id', '' ),
			], 200 );
		}

		try {
			$supabase = new Supabase();
			$account  = $supabase->get_account( $account_id );

			return new WP_REST_Response( [
				'connected' => true,
				'account'   => $account ? [
					'id'      => $account['id'],
					'email'   => $account['email'],
					'name'    => $account['name'] ?? null,
					'picture' => $account['picture'] ?? null,
				] : null,
				'snippetActive'      => (bool) get_option( 'adbot_snippet_active' ),
				'snippetContainerId' => get_option( 'adbot_snippet_container_id', '' ),
			], 200 );
		} catch ( \Exception $e ) {
			return new WP_REST_Response( [
				'connected'          => true,
				'account'            => null,
				'error'              => $e->getMessage(),
				'snippetActive'      => (bool) get_option( 'adbot_snippet_active' ),
				'snippetContainerId' => get_option( 'adbot_snippet_container_id', '' ),
			], 200 );
		}
	}
}
