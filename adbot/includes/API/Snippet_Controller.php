<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

class Snippet_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/snippet/install', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'install_snippet' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'containerId' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return preg_match( '/^GTM-[A-Z0-9]+$/', $value );
					},
				],
				'containerPath' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'ga4MeasurementId' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return empty( $value ) || preg_match( '/^G-[A-Z0-9]+$/', $value );
					},
				],
			],
		] );

		register_rest_route( $this->namespace, '/snippet/uninstall', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'uninstall_snippet' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function install_snippet( WP_REST_Request $request ): WP_REST_Response {
		$container_id    = (string) $request->get_param( 'containerId' );
		$container_path  = (string) $request->get_param( 'containerPath' );
		$ga4_measurement = (string) $request->get_param( 'ga4MeasurementId' );

		// Local state: used by Snippet_Injector on every front-end page.
		update_option( 'adbot_snippet_container_id', $container_id );
		update_option( 'adbot_snippet_active', true );
		if ( '' !== $container_path ) {
			update_option( 'adbot_snippet_container_path', $container_path );
		}

		// Tell the backend so it can record the install. Failure is non-critical.
		try {
			( new Client() )->post( '/gtm/snippet/install', [
				'container_id'       => $container_id,
				'container_path'     => $container_path,
				'ga4_measurement_id' => $ga4_measurement,
				'site_url'           => get_site_url(),
			], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception | Backend_Exception $e ) {
			$this->log_exception( 'snippet_install_record', $e );
		}

		return new WP_REST_Response( [
			'installed'   => true,
			'containerId' => $container_id,
		], 200 );
	}

	public function uninstall_snippet( WP_REST_Request $request ): WP_REST_Response {
		delete_option( 'adbot_snippet_active' );
		delete_option( 'adbot_snippet_container_id' );
		delete_option( 'adbot_snippet_container_path' );

		try {
			( new Client() )->post( '/gtm/snippet/uninstall' );
		} catch ( Consent_Required_Exception | Backend_Exception $e ) {
			$this->log_exception( 'snippet_uninstall_record', $e );
		}

		return new WP_REST_Response( [ 'uninstalled' => true ], 200 );
	}
}
