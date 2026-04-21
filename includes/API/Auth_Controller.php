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

/**
 * Thin proxy to the Adbot backend for Google OAuth.
 *
 * The plugin does not hold any Google credentials. It asks the backend for
 * an authorization URL, which redirects the browser through Google and
 * back to the backend's callback. On success the backend redirects the
 * browser to the admin page.
 */
class Auth_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/auth/google', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'initiate_oauth' ],
			'permission_callback' => [ $this, 'permission_callback' ],
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
			( new Site_Registration() )->maybe_register();

			$client = new Client();
			$result = $client->post( '/auth/google/start', [
				'return_url' => admin_url( 'admin.php?page=adbot&connected=1' ),
			] );

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

		return new WP_REST_Response( [ 'disconnected' => true ], 200 );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$snippet = [
			'snippetActive'        => (bool) get_option( 'adbot_snippet_active' ),
			'snippetContainerId'   => (string) get_option( 'adbot_snippet_container_id', '' ),
			'snippetContainerPath' => (string) get_option( 'adbot_snippet_container_path', '' ),
		];

		if ( '' === Token_Store::get_site_token() ) {
			return new WP_REST_Response( array_merge( [
				'connected' => false,
				'account'   => null,
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
				'error'     => $e->getMessage(),
			], $snippet ), 200 );
		}
	}
}
