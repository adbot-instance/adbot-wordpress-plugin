<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Google\Client;
use Adbot\Google\TagManager;
use Adbot\Setup\Plan;
use Adbot\Setup\Executor;
use Adbot\Setup\Publish;

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
		$gaps           = $request->get_param( 'gaps' );
		$measurement_id = $request->get_param( 'measurementId' );

		try {
			$plan       = new Plan();
			$operations = $plan->generate( $gaps, $measurement_id );

			return new WP_REST_Response( [ 'operations' => $operations ], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Plan generation failed: ' . $e->getMessage(), 500 );
		}
	}

	public function execute_plan( WP_REST_Request $request ): WP_REST_Response {
		$account_id     = get_option( 'adbot_account_id' );
		$container_path = $request->get_param( 'containerPath' );
		$workspace_path = $request->get_param( 'workspacePath' );
		$operations     = $request->get_param( 'plan' );

		if ( ! $account_id ) {
			return $this->error_response( 'Not connected to Google.', 401 );
		}

		try {
			$google_client = Client::get_authenticated_client( $account_id );
			$tagmanager    = new TagManager( $google_client );
			$executor      = new Executor( $tagmanager );

			$results = $executor->execute( $workspace_path, $operations );

			return new WP_REST_Response( [ 'results' => $results ], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Execution failed: ' . $e->getMessage(), 500 );
		}
	}

	public function publish( WP_REST_Request $request ): WP_REST_Response {
		$account_id     = get_option( 'adbot_account_id' );
		$container_path = $request->get_param( 'containerPath' );
		$workspace_path = $request->get_param( 'workspacePath' );

		if ( ! $account_id ) {
			return $this->error_response( 'Not connected to Google.', 401 );
		}

		try {
			$google_client = Client::get_authenticated_client( $account_id );
			$tagmanager    = new TagManager( $google_client );
			$publisher     = new Publish( $tagmanager );

			$version = $publisher->publish( $container_path, $workspace_path );

			return new WP_REST_Response( [ 'version' => $version ], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Publish failed: ' . $e->getMessage(), 500 );
		}
	}
}
