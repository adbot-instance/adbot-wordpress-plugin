<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

/**
 * Thin proxy for setup-plan generation, execution, and container publishing.
 * All Tag Manager API calls happen on the Adbot backend.
 */
class Setup_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/setup/plan', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'generate_plan' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'gaps' => [
					'required' => true,
					'type'     => 'array',
				],
				'measurementId' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( $this->namespace, '/setup/execute', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'execute_plan' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'containerPath' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'workspacePath' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'plan' => [
					'required' => true,
					'type'     => 'array',
				],
			],
		] );

		register_rest_route( $this->namespace, '/setup/publish', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'publish' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'containerPath' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'workspacePath' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public function generate_plan( WP_REST_Request $request ): WP_REST_Response {
		return $this->proxy( 'setup_plan', '/setup/plan', [
			'gaps'           => $request->get_param( 'gaps' ),
			'measurement_id' => (string) $request->get_param( 'measurementId' ),
		] );
	}

	public function execute_plan( WP_REST_Request $request ): WP_REST_Response {
		return $this->proxy( 'setup_execute', '/setup/execute', [
			'container_path' => (string) $request->get_param( 'containerPath' ),
			'workspace_path' => (string) $request->get_param( 'workspacePath' ),
			'plan'           => $request->get_param( 'plan' ),
		] );
	}

	public function publish( WP_REST_Request $request ): WP_REST_Response {
		return $this->proxy( 'setup_publish', '/setup/publish', [
			'container_path' => (string) $request->get_param( 'containerPath' ),
			'workspace_path' => (string) $request->get_param( 'workspacePath' ),
		] );
	}

	private function proxy( string $log_ctx, string $path, array $body ): WP_REST_Response {
		try {
			$result = ( new Client() )->post( $path, $body );
			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( $log_ctx, $e );
			return $this->backend_error( $e );
		}
	}
}
