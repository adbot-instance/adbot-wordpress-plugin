<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Google\Client;
use Adbot\Google\TagManager;

class Containers_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/containers', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_containers' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function list_containers( WP_REST_Request $request ): WP_REST_Response {
		$account_id = get_option( 'adbot_account_id' );

		if ( ! $account_id ) {
			return $this->error_response( 'Not connected to Google.', 401 );
		}

		try {
			$google_client = Client::get_authenticated_client( $account_id );
			$tagmanager    = new TagManager( $google_client );
			$containers    = $tagmanager->list_accounts_and_containers();

			return new WP_REST_Response( $containers, 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Failed to list containers: ' . $e->getMessage(), 500 );
		}
	}
}
