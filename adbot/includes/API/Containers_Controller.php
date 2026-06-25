<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

class Containers_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/containers', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_containers' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'refresh' => [
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
				],
			],
		] );
	}

	public function list_containers( WP_REST_Request $request ): WP_REST_Response {
		$cache_key = 'adbot_containers';
		$force     = (bool) $request->get_param( 'refresh' );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( $cached, 200 );
			}
		}

		try {
			$result = ( new Client() )->get( '/gtm/containers', [], Client::SLOW_TIMEOUT );
			set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'containers_list', $e );
			return $this->backend_error( $e );
		}
	}
}
