<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Backend\Token_Store;

class Status_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function get_status( WP_REST_Request $request ): WP_REST_Response {
		$snippet_active         = (bool) get_option( 'adbot_snippet_active' );
		$snippet_container_id   = (string) get_option( 'adbot_snippet_container_id', '' );
		$snippet_container_path = (string) get_option( 'adbot_snippet_container_path', '' );
		$snippet_external       = (bool) get_option( 'adbot_snippet_external_detected' );
		$snippet_detection      = get_option( 'adbot_snippet_detection', null );

		$connected = false;
		$account   = null;

		if ( '' !== Token_Store::get_site_token() ) {
			try {
				$result    = ( new Client() )->get( '/auth/status' );
				$connected = (bool) ( $result['connected'] ?? false );
				$account   = $result['account'] ?? null;
			} catch ( Backend_Exception $e ) {
				$this->log_exception( 'status', $e );
			}
		}

		return new WP_REST_Response( [
			'connected'            => $connected,
			'account'              => $account,
			'snippetActive'           => $snippet_active,
			'snippetContainerId'      => $snippet_container_id,
			'snippetContainerPath'    => $snippet_container_path,
			'snippetExternalDetected' => $snippet_external,
			'snippetDetection'        => $snippet_detection,
			'ga4MeasurementId'        => (string) get_option( 'adbot_ga4_measurement_id', '' ),
			'siteUrl'              => get_site_url(),
			'wpVersion'            => get_bloginfo( 'version' ),
			'pluginVersion'        => ADBOT_VERSION,
		], 200 );
	}
}
