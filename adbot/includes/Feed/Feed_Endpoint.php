<?php

namespace Adbot\Feed;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Serves the public product feed that Google Merchant Center fetches.
 *
 * Deliberately NOT a route under `adbot/v1`: Adbot::load_cache_guard() forces
 * `no-store` and DONOTCACHEPAGE on that whole namespace, which is right for
 * authenticated admin reads and exactly wrong for a file Google pulls daily and
 * that page caches should be free to absorb.
 *
 * Product data never passes through the Adbot backend — Google fetches this URL
 * directly. The backend only ever learns the URL itself.
 */
class Feed_Endpoint {

	public const TOKEN_OPTION = 'adbot_feed_token';
	public const QUERY_VAR    = 'adbot_product_feed';

	/** Path segment length; must match the rewrite rule's pattern. */
	private const TOKEN_LENGTH = 32;

	public function hook(): void {
		add_action( 'init', [ $this, 'add_rewrite_rule' ] );
		add_filter( 'query_vars', [ $this, 'register_query_var' ] );
		add_action( 'template_redirect', [ $this, 'maybe_serve' ] );
		// Deliberately no robots.txt Disallow for /adbot-feed/: Merchant Center's
		// scheduled fetch DOES honour robots.txt and fails as "denied by
		// robots.txt" (support.google.com/merchants/answer/160156). The
		// unguessable path token plus X-Robots-Tag: noindex keep it private.
	}

	public function add_rewrite_rule(): void {
		add_rewrite_rule(
			'^adbot-feed/([A-Za-z0-9]{' . self::TOKEN_LENGTH . '})/products\.xml/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	/**
	 * Registering the query var also gives us the `?adbot_product_feed=<token>`
	 * form for free, which is the only thing that works on a site using plain
	 * permalinks (where no rewrite rules run at all).
	 */
	public function register_query_var( array $vars ): array {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public function maybe_serve(): void {
		$token = (string) get_query_var( self::QUERY_VAR );
		if ( '' === $token ) {
			return;
		}

		$expected = (string) get_option( self::TOKEN_OPTION, '' );

		// An unconfigured feed must not be distinguishable from a wrong token,
		// and hash_equals keeps the comparison timing-independent — the same
		// guard Site_Registration::handle_verify_request() uses.
		if ( '' === $expected || ! hash_equals( $expected, $token ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		if ( ! self::is_supported() ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$this->send_headers();
		( new Product_Feed() )->render();
		exit;
	}

	private function send_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: application/xml; charset=UTF-8' );
		// The feed is a machine endpoint, not a page: keep it out of search
		// results even though the URL is unguessable. This header, not
		// robots.txt, is the right tool — a robots.txt Disallow would also
		// block Merchant Center's own scheduled fetch.
		header( 'X-Robots-Tag: noindex, nofollow', true );
		// Unlike the admin REST API this SHOULD be cached — Google fetches it
		// whole, once a day, and a cold generation is the expensive path.
		header( 'Cache-Control: public, max-age=3600' );
		header( 'X-Content-Type-Options: nosniff', true );
	}

	/** WooCommerce is the only product source this feed reads. */
	public static function is_supported(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * The feed's public URL, minting the token on first use.
	 *
	 * Returns '' when WooCommerce is absent so callers never advertise a URL
	 * that would 404.
	 */
	public static function url(): string {
		if ( ! self::is_supported() ) {
			return '';
		}

		return home_url( '/adbot-feed/' . self::token() . '/products.xml' );
	}

	/**
	 * Unguessable path token. This is what protects the feed: Merchant Center's
	 * fetchSettings username/password are documented for SFTP only, so HTTP
	 * Basic auth is not a reliable option for a fetched URL.
	 */
	public static function token(): string {
		$token = (string) get_option( self::TOKEN_OPTION, '' );
		if ( self::TOKEN_LENGTH === strlen( $token ) && ctype_alnum( $token ) ) {
			return $token;
		}

		// Alphanumeric only: the token is a path segment matched by the rewrite
		// rule, so punctuation from the default charset would never route.
		$token = wp_generate_password( self::TOKEN_LENGTH, false, false );
		update_option( self::TOKEN_OPTION, $token, false );

		// The rule itself is static, but a site that never had a token has also
		// never had the rule flushed into its cached set.
		self::flush_rules();

		return $token;
	}

	/**
	 * Rotate the token, invalidating the old URL. The Merchant Center data
	 * source has to be re-registered afterwards or Google keeps fetching a 404.
	 */
	public static function rotate_token(): string {
		delete_option( self::TOKEN_OPTION );
		return self::token();
	}

	public static function flush_rules(): void {
		// Cheap enough once, ruinous on every request — only ever called from
		// activation, version change, and token minting.
		flush_rewrite_rules( false );
	}
}
