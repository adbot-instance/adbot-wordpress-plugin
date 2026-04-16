<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Google\Client;
use Adbot\Google\TagManager;
use Adbot\Google\Analytics;
use Adbot\Assessment\Container_Audit;
use Adbot\Assessment\Gap_Analysis;
use Adbot\Assessment\Scoring;
use Adbot\Assessment\GA4_Discovery;
use Adbot\Assessment\Snippet_Detection;

class Audit_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/audit', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'run_audit' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'containerPath' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function run_audit( WP_REST_Request $request ): WP_REST_Response {
		$account_id     = get_option( 'adbot_account_id' );
		$container_path = $request->get_param( 'containerPath' );

		if ( ! $account_id ) {
			return $this->error_response( 'Not connected to Google.', 401 );
		}

		try {
			$google_client = Client::get_authenticated_client( $account_id );
			$tagmanager    = new TagManager( $google_client );
			$analytics     = new Analytics( $google_client );

			// Discover GA4 property for this site.
			$ga4_discovery    = new GA4_Discovery( $analytics );
			$site_url         = get_site_url();
			$ga4_info         = $ga4_discovery->discover_for_url( $site_url );
			$measurement_id   = $ga4_info['measurementId'] ?? null;

			// Get the workspace (default workspace of the container).
			$workspaces     = $tagmanager->list_workspaces( $container_path );
			$workspace_path = $workspaces[0]['path'] ?? null;

			if ( ! $workspace_path ) {
				return $this->error_response( 'No workspace found in container.', 404 );
			}

			// Audit the container.
			$container_audit = new Container_Audit( $tagmanager );
			$audit_data      = $container_audit->audit( $workspace_path );

			// Run gap analysis.
			$gap_analysis = new Gap_Analysis();
			$gaps         = $gap_analysis->analyze(
				$audit_data['tags'],
				$audit_data['triggers'],
				$measurement_id
			);

			// Calculate score.
			$scoring = new Scoring();
			$score   = $scoring->calculate( $gaps );

			// Detect snippet on the site.
			$snippet_detection = new Snippet_Detection();
			$container_id      = get_option( 'adbot_snippet_container_id', '' );
			$snippet_status    = $snippet_detection->detect( $site_url, $container_id );

			return new WP_REST_Response( [
				'containerPath'    => $container_path,
				'workspacePath'    => $workspace_path,
				'measurementId'    => $measurement_id,
				'tags'             => $audit_data['tags'],
				'triggers'         => $audit_data['triggers'],
				'variables'        => $audit_data['variables'],
				'gaps'             => $gaps,
				'score'            => $score,
				'snippetInstalled' => $snippet_status['installed'],
				'snippetEvidence'  => $snippet_status['evidence'] ?? '',
			], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Audit failed: ' . $e->getMessage(), 500 );
		}
	}
}
