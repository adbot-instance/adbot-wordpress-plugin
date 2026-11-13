<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Channels\Sitemap;
use Adbot\Channels\Site_Verification;
use Adbot\Consent_Required_Exception;

/**
 * Search Console channel: pick a property, prove ownership, submit the sitemap.
 *
 * Proxies the Adbot backend exactly as GA4_Controller does — the plugin never
 * holds Google credentials. What is local to WordPress is the ownership proof
 * (served from wp_head) and the sitemap URL, neither of which the backend can
 * know on its own.
 */
class GSC_Controller extends REST_Controller {

	public const OPTION_SITE_URL      = 'adbot_gsc_site_url';
	public const OPTION_SITEMAP_URL   = 'adbot_gsc_sitemap_url';
	public const OPTION_SUBMITTED_AT  = 'adbot_gsc_sitemap_submitted_at';
	public const OPTION_VERIFIED_AT   = 'adbot_gsc_verified_at';

	private const SITES_CACHE = 'adbot_gsc_sites';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/gsc/sites', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_sites' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'refresh' => [
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
				],
			],
		] );

		register_rest_route( $this->namespace, '/gsc/select', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'select_site' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'siteUrl' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'esc_url_raw',
					'validate_callback' => function ( $value ) {
						return (bool) wp_http_validate_url( (string) $value );
					},
				],
				'permissionLevel' => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( $this->namespace, '/gsc/verify', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify_site' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/gsc/sitemap', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'sitemap_status' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'submit_sitemap' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );
	}

	public function list_sites( WP_REST_Request $request ): WP_REST_Response {
		$force = (bool) $request->get_param( 'refresh' );

		if ( ! $force ) {
			$cached = get_transient( self::SITES_CACHE );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( $this->decorate_sites( $cached ), 200 );
			}
		}

		try {
			$result = ( new Client() )->get( '/gsc/sites', [], Client::SLOW_TIMEOUT );
			set_transient( self::SITES_CACHE, $result, 5 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( $this->decorate_sites( $result ), 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gsc_sites', $e );
			return $this->backend_error( $e );
		}
	}

	/**
	 * Attach the local facts the backend cannot see: the selected property, and
	 * whether this WordPress install can offer a sitemap at all.
	 */
	private function decorate_sites( array $result ): array {
		$sitemap_url = Sitemap::url();

		$result['selected']  = (string) get_option( self::OPTION_SITE_URL, '' );
		$result['verifiedAt'] = (string) get_option( self::OPTION_VERIFIED_AT, '' );
		$result['sitemap']   = [
			'url'                => $sitemap_url,
			'available'          => '' !== $sitemap_url,
			'coreSitemapsActive' => Sitemap::is_enabled(),
			'conflictingPlugin'  => Sitemap::conflicting_plugin(),
			'submittedUrl'       => (string) get_option( self::OPTION_SITEMAP_URL, '' ),
			'submittedAt'        => (string) get_option( self::OPTION_SUBMITTED_AT, '' ),
		];

		return $result;
	}

	public function select_site( WP_REST_Request $request ): WP_REST_Response {
		$site_url = (string) $request->get_param( 'siteUrl' );

		try {
			$result = ( new Client() )->post( '/gsc/select', [
				'site_url'         => $site_url,
				'permission_level' => (string) $request->get_param( 'permissionLevel' ),
			] );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gsc_select', $e );
			return $this->backend_error( $e );
		}

		// Store the canonical form the backend echoes back, so the local option
		// and the backend's config can never disagree about trailing slashes.
		$stored = (string) ( $result['site_url'] ?? $site_url );
		update_option( self::OPTION_SITE_URL, $stored, false );

		return new WP_REST_Response( [ 'siteUrl' => $stored ], 200 );
	}

	/**
	 * Two-phase ownership proof.
	 *
	 * 1. Ask the backend for the token and publish it in wp_head.
	 * 2. Ask the backend to claim — Google fetches this site and looks for it.
	 *
	 * It has to be two calls because only WordPress can publish the tag and
	 * only Google can check it, and the tag must be live before the claim.
	 */
	public function verify_site( WP_REST_Request $request ): WP_REST_Response {
		try {
			$client = new Client();

			$token_result = $client->post( '/gsc/verify/token', [], Client::SLOW_TIMEOUT );
			$token        = (string) ( $token_result['token'] ?? '' );

			if ( '' === $token ) {
				return $this->error_response(
					__( 'Google did not return a verification token. Try again in a moment.', 'adbot-tracking-platform' ),
					502
				);
			}

			Site_Verification::set_token( $token );

			// The tag is rendered from an option on the next front-end request,
			// so it is already live for Google's fetch. Page caches are the one
			// risk here: purge so Google is not served a cached head.
			$this->purge_front_page_caches();

			$result = $client->post( '/gsc/verify', [], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gsc_verify', $e );
			return $this->backend_error( $e );
		}

		// The backend answers 200 for a completed check whether or not Google
		// found the tag, and the card treats any stored timestamp as
		// "verified". So only a positive result is recorded. A failed re-check
		// deliberately leaves an earlier successful timestamp alone: Google
		// keeps an owner verified until the token is removed, and one bad
		// fetch (a cached head, a timeout) does not revoke that.
		$verified    = ! empty( $result['verified'] );
		$verified_at = '';
		if ( $verified ) {
			// empty() rather than ??: a backend that sends verified_at: "" would
			// otherwise store an empty timestamp, which the card reads as
			// "not verified" despite the positive result.
			$verified_at = ! empty( $result['verified_at'] ) ? (string) $result['verified_at'] : gmdate( 'c' );
			update_option( self::OPTION_VERIFIED_AT, $verified_at, false );
		}

		$site_url = (string) ( $result['site_url'] ?? '' );
		if ( '' !== $site_url ) {
			update_option( self::OPTION_SITE_URL, $site_url, false );
		}

		delete_transient( self::SITES_CACHE );

		return new WP_REST_Response( [
			'verified'   => $verified,
			'added'      => (bool) ( $result['added'] ?? false ),
			'addError'   => $result['add_error'] ?? null,
			'siteUrl'    => $site_url,
			'verifiedAt' => $verified_at,
		], 200 );
	}

	public function submit_sitemap( WP_REST_Request $request ): WP_REST_Response {
		$sitemap_url = Sitemap::url();

		if ( '' === $sitemap_url ) {
			$plugin = Sitemap::conflicting_plugin();
			return $this->error_response(
				'' !== $plugin
					? sprintf(
						/* translators: %s: SEO plugin name. */
						__( 'WordPress sitemaps are switched off, most likely by %s. Submit that plugin\'s sitemap in Search Console instead.', 'adbot-tracking-platform' ),
						$plugin
					)
					: __( 'WordPress sitemaps are switched off on this site, so there is nothing to submit.', 'adbot-tracking-platform' ),
				409
			);
		}

		// Submitting a URL that 404s leaves a permanent error in Search Console
		// that cannot be cleared from here, so check before asking Google.
		if ( ! Sitemap::is_reachable( $sitemap_url ) ) {
			return $this->error_response(
				sprintf(
					/* translators: %s: sitemap URL. */
					__( 'Adbot could not load %s. Open it in a browser to check it works, then try again.', 'adbot-tracking-platform' ),
					$sitemap_url
				),
				409
			);
		}

		try {
			$result = ( new Client() )->post( '/gsc/sitemap', [
				'sitemap_url' => $sitemap_url,
			], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gsc_sitemap_submit', $e );
			return $this->backend_error( $e );
		}

		$submitted_at = (string) ( $result['submitted_at'] ?? gmdate( 'c' ) );
		update_option( self::OPTION_SITEMAP_URL, $sitemap_url, false );
		update_option( self::OPTION_SUBMITTED_AT, $submitted_at, false );

		return new WP_REST_Response( [
			'submitted'   => true,
			'sitemapUrl'  => $sitemap_url,
			'submittedAt' => $submitted_at,
		], 200 );
	}

	public function sitemap_status( WP_REST_Request $request ): WP_REST_Response {
		$local = [
			'url'                => Sitemap::url(),
			'coreSitemapsActive' => Sitemap::is_enabled(),
			'conflictingPlugin'  => Sitemap::conflicting_plugin(),
			'submittedUrl'       => (string) get_option( self::OPTION_SITEMAP_URL, '' ),
			'submittedAt'        => (string) get_option( self::OPTION_SUBMITTED_AT, '' ),
		];

		try {
			$result = ( new Client() )->get( '/gsc/sitemap', [], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			// A status read must not break the card. The local view alone still
			// tells the user what was submitted and when.
			$this->log_exception( 'gsc_sitemap_status', $e );
			return new WP_REST_Response( [ 'local' => $local, 'remote' => null, 'error' => $e->getMessage() ], 200 );
		}

		return new WP_REST_Response( [ 'local' => $local, 'remote' => $result ], 200 );
	}

	/**
	 * Google fetches the site's home page to find the verification tag. A page
	 * cache that already holds a head without it would fail the claim for as
	 * long as the cache lives, so nudge the same caches the version-upgrade
	 * purge in Adbot::maybe_purge_caches_on_upgrade() knows about. Every call is
	 * a no-op when the corresponding plugin is absent.
	 */
	private function purge_front_page_caches(): void {
		do_action( 'litespeed_purge_all' );
		do_action( 'cache_enabler_clear_complete_cache' );
		do_action( 'comet_cache_wipe_cache' );
		if ( function_exists( 'rocket_clean_domain' ) ) {
			rocket_clean_domain();
		}
		if ( function_exists( 'w3tc_flush_all' ) ) {
			w3tc_flush_all();
		}
		if ( function_exists( 'wp_cache_clear_cache' ) ) {
			wp_cache_clear_cache();
		}
	}
}
