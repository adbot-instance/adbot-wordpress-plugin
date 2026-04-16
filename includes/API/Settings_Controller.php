<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;

class Settings_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/settings', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'exclude_admins' => [
						'type' => 'boolean',
					],
					'debug_mode' => [
						'type' => 'boolean',
					],
				],
			],
		] );
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = get_option( 'adbot_settings', [
			'exclude_admins' => true,
			'debug_mode'     => false,
		] );

		return new WP_REST_Response( $settings, 200 );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$current = get_option( 'adbot_settings', [] );

		if ( $request->has_param( 'exclude_admins' ) ) {
			$current['exclude_admins'] = (bool) $request->get_param( 'exclude_admins' );
		}

		if ( $request->has_param( 'debug_mode' ) ) {
			$current['debug_mode'] = (bool) $request->get_param( 'debug_mode' );
		}

		update_option( 'adbot_settings', $current );

		return new WP_REST_Response( $current, 200 );
	}
}
