<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Backend\Token_Store;
use Adbot\Channels\Sitemap;
use Adbot\Channels\Store;
use Adbot\Feed\Feed_Endpoint;

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

		$sitemap_url = Sitemap::url();

		$connected  = false;
		$account    = null;
		$scope_sets = [];

		if ( '' !== Token_Store::get_site_token() ) {
			try {
				$result     = ( new Client() )->get( '/auth/status' );
				$connected  = (bool) ( $result['connected'] ?? false );
				$account    = $result['account'] ?? null;
				// Which channel scope bundles the stored token already covers.
				// Lets a card offer its picker straight away instead of having
				// to provoke a 403 from Google to discover a missing scope.
				$scope_sets = is_array( $result['scopeSets'] ?? null ) ? $result['scopeSets'] : [];
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
			'snippetHead'             => '' !== (string) $snippet_container_id ? \Adbot\Tracking\Snippet_Injector::head_snippet( (string) $snippet_container_id ) : '',
			'snippetBody'             => '' !== (string) $snippet_container_id ? \Adbot\Tracking\Snippet_Injector::body_snippet( (string) $snippet_container_id ) : '',
			'ga4MeasurementId'        => (string) get_option( 'adbot_ga4_measurement_id', '' ),
			'ga4PropertyId'           => (string) get_option( Insights_Controller::OPTION_GA4_PROPERTY_ID, '' ),
			'ga4PropertyName'         => (string) get_option( Insights_Controller::OPTION_GA4_PROPERTY_NAME, '' ),
			'googleScopeSets'         => array_values( $scope_sets ),

			// Search Console channel.
			'gscSiteUrl'              => (string) get_option( GSC_Controller::OPTION_SITE_URL, '' ),
			'gscVerifiedAt'           => (string) get_option( GSC_Controller::OPTION_VERIFIED_AT, '' ),
			'gscSitemapUrl'           => (string) get_option( GSC_Controller::OPTION_SITEMAP_URL, '' ),
			'gscSitemapSubmittedAt'   => (string) get_option( GSC_Controller::OPTION_SUBMITTED_AT, '' ),
			'sitemapUrl'              => $sitemap_url,
			'sitemapAvailable'        => '' !== $sitemap_url,

			// Merchant Center channel.
			'gmcAccountName'          => (string) get_option( GMC_Controller::OPTION_ACCOUNT_NAME, '' ),
			'gmcAccountLabel'         => (string) get_option( GMC_Controller::OPTION_ACCOUNT_LABEL, '' ),
			'gmcClaimedAt'            => (string) get_option( GMC_Controller::OPTION_CLAIMED_AT, '' ),
			'gmcFeedRegisteredAt'     => (string) get_option( GMC_Controller::OPTION_REGISTERED_AT, '' ),
			'wooActive'               => Store::is_woocommerce_active(),
			'productFeedUrl'          => Feed_Endpoint::url(),

			'siteUrl'              => get_site_url(),
			'wpVersion'            => get_bloginfo( 'version' ),
			'pluginVersion'        => ADBOT_VERSION,
		], 200 );
	}
}
