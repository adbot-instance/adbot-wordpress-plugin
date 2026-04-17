<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Database\Supabase;

class Status_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$account_id   = get_option( 'adbot_account_id' );
		$is_connected = ! empty( $account_id );

		$snippet_active         = (bool) get_option( 'adbot_snippet_active' );
		$snippet_container_id   = get_option( 'adbot_snippet_container_id', '' );
		$snippet_container_path = get_option( 'adbot_snippet_container_path', '' );

		$account = null;
		if ( $is_connected ) {
			try {
				$supabase = new Supabase();
				$account  = $supabase->get_account( $account_id );
			} catch ( \Exception $e ) {
				// Silently continue — account data is non-critical for status.
			}
		}

		return new WP_REST_Response( [
			'connected'          => $is_connected,
			'account'            => $account ? [
				'id'      => $account['id'],
				'email'   => $account['email'],
				'name'    => $account['name'] ?? null,
				'picture' => $account['picture'] ?? null,
			] : null,
			'snippetActive'        => $snippet_active,
			'snippetContainerId'   => $snippet_container_id,
			'snippetContainerPath' => $snippet_container_path,
			'siteUrl'            => get_site_url(),
			'wpVersion'          => get_bloginfo( 'version' ),
			'pluginVersion'      => ADBOT_VERSION,
		], 200 );
	}
}
