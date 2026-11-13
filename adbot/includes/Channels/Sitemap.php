<?php

namespace Adbot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the sitemap URL to hand to Search Console.
 *
 * Scoped deliberately to WordPress core's own sitemap. Yoast SEO, Rank Math and
 * All in One SEO each disable core sitemaps (via the `wp_sitemaps_enabled`
 * filter) and serve their own index at their own path — guessing at that path
 * and submitting a 404 to Google is worse than telling the user we can't find
 * one, so we detect and explain instead.
 */
class Sitemap {

	/**
	 * The core sitemap index URL, or '' when core sitemaps are not serving.
	 *
	 * Uses the sitemaps server's own index URL rather than hardcoding
	 * /wp-sitemap.xml, so a site that has filtered the slug still resolves.
	 */
	public static function url(): string {
		if ( ! self::is_enabled() ) {
			return '';
		}

		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();
			if ( $server && isset( $server->index ) && method_exists( $server->index, 'get_index_url' ) ) {
				return (string) $server->index->get_index_url();
			}
		}

		return home_url( '/wp-sitemap.xml' );
	}

	/**
	 * Whether core sitemaps are switched on. `wp_sitemaps_enabled()` already
	 * accounts for the blog_public option and the filter SEO plugins use.
	 */
	public static function is_enabled(): bool {
		if ( ! function_exists( 'wp_sitemaps_enabled' ) ) {
			return false; // WordPress < 5.5.
		}
		return (bool) wp_sitemaps_enabled();
	}

	/**
	 * Which third-party SEO plugin is taking over sitemaps, for the message on
	 * the Search Console card. Returns '' when none is recognised.
	 */
	public static function conflicting_plugin(): string {
		$known = [
			'Yoast SEO'      => 'WPSEO_VERSION',
			'Rank Math'      => 'RANK_MATH_VERSION',
			'All in One SEO' => 'AIOSEO_VERSION',
			'SEOPress'       => 'SEOPRESS_VERSION',
		];

		foreach ( $known as $label => $constant ) {
			if ( defined( $constant ) ) {
				return $label;
			}
		}

		return '';
	}

	/**
	 * Confirm the URL actually serves before asking Google to fetch it.
	 *
	 * A HEAD request against our own site is cheap, and a submitted 404 shows
	 * up in Search Console as a persistent error the user cannot clear from
	 * here. Failures to reach ourselves are treated as "reachable": a host that
	 * blocks loopback requests is common, and Google is not subject to it.
	 */
	public static function is_reachable( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$response = wp_remote_head( $url, [
			'timeout'   => 10,
			'redirection' => 3,
		] );

		if ( is_wp_error( $response ) ) {
			return true;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		return $status >= 200 && $status < 400;
	}
}
