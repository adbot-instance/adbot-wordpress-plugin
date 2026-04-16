<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Database\Supabase;

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
		$container_id      = $request->get_param( 'containerId' );
		$container_path    = $request->get_param( 'containerPath' );
		$ga4_measurement   = $request->get_param( 'ga4MeasurementId' );

		// Store in wp_options for fast front-end access.
		update_option( 'adbot_snippet_container_id', $container_id );
		update_option( 'adbot_snippet_active', true );

		// Also track in Supabase.
		$site_id = get_option( 'adbot_site_id' );
		if ( $site_id ) {
			try {
				$supabase = new Supabase();
				$supabase->upsert_snippet_install( [
					'wordpress_site_id'  => $site_id,
					'gtm_container_id'   => $container_id,
					'container_path'     => $container_path ?? '',
					'ga4_measurement_id' => $ga4_measurement ?? '',
					'is_active'          => true,
				] );
			} catch ( \Exception $e ) {
				// Non-critical — local options are sufficient for snippet injection.
			}
		}

		return new WP_REST_Response( [
			'installed'   => true,
			'containerId' => $container_id,
		], 200 );
	}

	public function uninstall_snippet( WP_REST_Request $request ): WP_REST_Response {
		delete_option( 'adbot_snippet_active' );
		delete_option( 'adbot_snippet_container_id' );

		$site_id = get_option( 'adbot_site_id' );
		if ( $site_id ) {
			try {
				$supabase = new Supabase();
				$supabase->delete_snippet_install( $site_id );
			} catch ( \Exception $e ) {
				// Non-critical.
			}
		}

		return new WP_REST_Response( [ 'uninstalled' => true ], 200 );
	}
}
