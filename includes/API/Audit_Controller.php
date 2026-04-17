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

	private const TRANSIENT_PREFIX = 'adbot_audit_';
	private const TTL              = 2 * HOUR_IN_SECONDS;

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

		register_rest_route( $this->namespace, '/audit/(?P<id>[a-z0-9]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_audit' ],
			'permission_callback' => [ $this, 'permission_callback' ],
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

			$ga4_discovery  = new GA4_Discovery( $analytics );
			$site_url       = get_site_url();
			$ga4_info       = $ga4_discovery->discover_for_url( $site_url );
			$measurement_id = $ga4_info['measurementId'] ?? null;

			$workspaces     = $tagmanager->list_workspaces( $container_path );
			$workspace_path = $workspaces[0]['path'] ?? null;

			if ( ! $workspace_path ) {
				return $this->error_response( 'No workspace found in container.', 404 );
			}

			$container_audit = new Container_Audit( $tagmanager );
			$audit_data      = $container_audit->audit( $workspace_path );

			$gap_analysis = new Gap_Analysis();
			$gaps         = $gap_analysis->analyze(
				$audit_data['tags'],
				$audit_data['triggers'],
				$measurement_id
			);

			$scoring = new Scoring();
			$score   = $scoring->calculate( $gaps );

			$snippet_detection = new Snippet_Detection();
			$container_id      = get_option( 'adbot_snippet_container_id', '' );
			$snippet_status    = $snippet_detection->detect( $site_url, $container_id );

			$audit_id = $this->new_audit_id();

			$payload = [
				'auditId'          => $audit_id,
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
				'createdAt'        => time(),
			];

			set_transient( self::TRANSIENT_PREFIX . $audit_id, $payload, self::TTL );

			return new WP_REST_Response( $payload, 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Audit failed: ' . $e->getMessage(), 500 );
		}
	}

	public function get_audit( WP_REST_Request $request ): WP_REST_Response {
		$id = (string) $request->get_param( 'id' );
		$data = get_transient( self::TRANSIENT_PREFIX . $id );

		if ( ! $data ) {
			return $this->error_response( 'Audit not found or expired.', 404 );
		}

		return new WP_REST_Response( $data, 200 );
	}

	private function new_audit_id(): string {
		return strtolower( wp_generate_password( 16, false, false ) );
	}
}
