<?php

namespace Adbot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Adbot\Admin\Admin;
use Adbot\API\Auth_Controller;
use Adbot\API\Status_Controller;
use Adbot\API\Containers_Controller;
use Adbot\API\Audit_Controller;
use Adbot\API\Setup_Controller;
use Adbot\API\Snippet_Controller;
use Adbot\API\Settings_Controller;
use Adbot\API\Onboarding_Controller;
use Adbot\API\Payments_Controller;
use Adbot\Tracking\Snippet_Injector;
use Adbot\Backend\Site_Registration;

class Adbot {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_cache_guard();
		$this->load_admin();
		$this->load_rest_api();
		$this->load_tracking();
		$this->load_site_registration();

		// One-time cache flush after a plugin version upgrade, in case an older
		// build let a cache store stale /adbot/v1 REST responses before the guard
		// above existed. (Fresh installs seed adbot_version in the activation hook,
		// so this correctly no-ops there.) Runs on `init`, once all cache plugins
		// have loaded and registered their purge hooks.
		add_action( 'init', [ $this, 'maybe_purge_caches_on_upgrade' ] );

		do_action( 'adbot_loaded' );
	}

	/**
	 * Flush page / CDN caches exactly once per plugin version change, so a cache
	 * entry written by an older build (which may hold a stale authenticated
	 * /adbot/v1 REST response — see load_cache_guard()) cannot outlive the update.
	 * Each purge call is a no-op when the corresponding cache is not installed.
	 */
	public function maybe_purge_caches_on_upgrade(): void {
		if ( ADBOT_VERSION === (string) get_option( 'adbot_version', '' ) ) {
			return;
		}
		update_option( 'adbot_version', ADBOT_VERSION );

		do_action( 'litespeed_purge_all' );
		do_action( 'litespeed_purge_url', '/wp-json/adbot/v1/' );
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

	/**
	 * Prevent ANY page / object / CDN cache from storing authenticated
	 * /adbot/v1 REST responses.
	 *
	 * Root cause (proven on a live LiteSpeed Cache site): LiteSpeed
	 * private-cached the logged-in admin's REST GETs (x-litespeed-cache: hit)
	 * and ignored WordPress's own `Cache-Control: no-cache, no-store, private`
	 * header. The React admin SPA then kept reading STALE responses — e.g.
	 * /status reporting an empty container id long after one was installed —
	 * producing "No GTM container selected" / "settings won't save" symptoms.
	 * The DB writes were fine; only the cached HTTP GET was stale.
	 *
	 * WordPress's default nocache headers are demonstrably insufficient, so we
	 * emit each major caching layer's OWN explicit opt-out signal. Every signal
	 * below is a harmless no-op when the corresponding cache is not installed,
	 * which keeps this fix cache-plugin-AGNOSTIC.
	 *
	 * rest_pre_dispatch fires as the first line of WP_REST_Server::dispatch()
	 * for EVERY request (GET / POST / OPTIONS / subroutes) reaching the REST
	 * server, long before any cache plugin's shutdown-time store decision. It is
	 * a short-circuit filter, so we MUST return $result unchanged.
	 */
	private function load_cache_guard(): void {
		add_filter( 'rest_pre_dispatch', function ( $result, $server, $request ) {
			$route = ltrim( is_object( $request ) ? (string) $request->get_route() : '', '/' );

			// Scope strictly to this plugin's namespace — the bare index route and
			// any subroute — while leaving every other namespace untouched (and not
			// matching siblings such as "adbot/v1x").
			if ( 'adbot/v1' !== $route && 0 !== strpos( $route, 'adbot/v1/' ) ) {
				return $result;
			}

			// De-facto-standard page-cache write opt-out honoured by WP Rocket,
			// W3 Total Cache, WP Super Cache, Comet Cache, WP Fastest Cache,
			// Batcache, SG Optimizer, Hummingbird and most host caches. Guarded
			// because a constant cannot be redefined.
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true );
			}

			// LiteSpeed Cache (the proven culprit) ignores standard Cache-Control
			// for REST and obeys only its own control API. This action flips its
			// BM_NOTCACHEABLE bit so the response is stored in NEITHER public NOR
			// private cache. It is a no-op when LiteSpeed is absent. Must be
			// set_nocache, NOT set_private — private cache is what served the
			// stale value in the incident.
			do_action( 'litespeed_control_set_nocache', 'adbot authenticated REST API must never be cached' );

			// WordPress nocache headers plus raw belt-and-suspenders headers:
			// X-LiteSpeed-Cache-Control covers a server-level LiteSpeed / QUIC.cloud
			// edge without the plugin, and the CDN-Cache-Control variants are read by
			// Cloudflare / Fastly / Hostinger edges in preference to standard
			// Cache-Control. All guarded together so we never warn if headers were
			// already flushed (nocache_headers() calls header() internally too).
			if ( ! headers_sent() ) {
				nocache_headers();
				header( 'X-LiteSpeed-Cache-Control: no-cache, no-store', true );
				header( 'CDN-Cache-Control: no-store', true );
				header( 'Cloudflare-CDN-Cache-Control: no-store', true );
			}

			return $result;
		}, 10, 3 );
	}

	private function load_site_registration(): void {
		( new Site_Registration() )->hook();
	}

	private function load_admin(): void {
		if ( is_admin() ) {
			new Admin();
		}
	}

	private function load_rest_api(): void {
		add_action( 'rest_api_init', function () {
			( new Auth_Controller() )->register_routes();
			( new Status_Controller() )->register_routes();
			( new Containers_Controller() )->register_routes();
			( new Audit_Controller() )->register_routes();
			( new Setup_Controller() )->register_routes();
			( new Snippet_Controller() )->register_routes();
			( new Settings_Controller() )->register_routes();
			( new Onboarding_Controller() )->register_routes();
			( new Payments_Controller() )->register_routes();

			register_rest_route( 'adbot/v1', '/verify', [
				'methods'             => 'GET',
				'callback'            => [ Site_Registration::class, 'handle_verify_request' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'challenge' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			] );
		} );
	}

	private function load_tracking(): void {
		new Snippet_Injector();
	}
}
