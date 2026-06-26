<?php

namespace Adbot\Tracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Scans the site's own front-end HTML for an existing Google Tag Manager
 * snippet, so the plugin can avoid injecting a duplicate container.
 *
 * Detection deliberately ignores Adbot's own snippet (marked with
 * data-adbot="gtm" and the "(Adbot)" HTML comments emitted by
 * Snippet_Injector) so that a re-scan after the plugin has already injected
 * does not report the plugin's own output as a pre-existing third-party
 * install.
 *
 * This is the local, consent-independent detector. The backend runs a richer
 * scan (headless render fallback, plugin/CMS signatures) and the plugin
 * prefers that verdict when it is available — see Snippet_Controller.
 */
class Snippet_Detector {

	/**
	 * Fetch the home page and look for a GTM snippet matching $container_id
	 * that was NOT injected by Adbot.
	 *
	 * @param string $container_id Public GTM-XXXX container id.
	 * @return array{
	 *   found: bool,             // a matching, non-Adbot snippet is present
	 *   matched_container: bool, // the found GTM id equals $container_id
	 *   method: string,          // how it was found: loader|noscript|inline|other|none
	 *   other_ids: string[],     // other GTM-XXXX ids seen on the page
	 *   source: string,          // always 'local' for this detector
	 *   error: ?string           // fetch/validation error (detection inconclusive)
	 * }
	 */
	public static function detect( string $container_id ): array {
		$result = [
			'found'             => false,
			'matched_container' => false,
			'method'            => 'none',
			'other_ids'         => [],
			'source'            => 'local',
			'error'             => null,
		];

		if ( ! preg_match( '/^GTM-[A-Z0-9]+$/', $container_id ) ) {
			$result['error'] = 'invalid_container_id';
			return $result;
		}

		$html = self::fetch_home_html();
		if ( null === $html ) {
			$result['error'] = 'fetch_failed';
			return $result;
		}

		$html = self::strip_adbot_snippet( $html );

		// Every GTM-XXXX id present on the page (after removing Adbot's own).
		preg_match_all( '/GTM-[A-Z0-9]+/', $html, $matches );
		$ids = array_values( array_unique( $matches[0] ) );

		$id_re = preg_quote( $container_id, '/' );

		$method = 'none';
		if ( preg_match( '/\/gtm\.js\?[^"\'<>]*\bid=' . $id_re . '\b/i', $html ) ) {
			$method = 'loader';
		} elseif ( preg_match( '/\/ns\.html\?[^"\'<>]*\bid=' . $id_re . '\b/i', $html ) ) {
			$method = 'noscript';
		} elseif ( preg_match( '/[\'"]' . $id_re . '[\'"]/', $html ) ) {
			$method = 'inline';
		} elseif ( in_array( $container_id, $ids, true ) ) {
			$method = 'other';
		}

		$found = ( 'none' !== $method );

		$result['found']             = $found;
		$result['matched_container'] = $found;
		$result['method']            = $method;
		$result['other_ids']         = array_values( array_diff( $ids, [ $container_id ] ) );

		return $result;
	}

	/**
	 * Fetch the site's home page HTML the way an anonymous visitor sees it.
	 * Returns null on any transport / non-2xx-3xx error so the caller can treat
	 * detection as inconclusive rather than "no snippet".
	 */
	private static function fetch_home_html(): ?string {
		$response = wp_remote_get(
			home_url( '/' ),
			[
				'timeout'     => 15,
				'redirection' => 3,
				// A staging / self-hosted site may serve a self-signed cert; we
				// are only fetching our own front end, so this is safe here.
				'sslverify'   => false,
				// No cookies => never the logged-in admin variant of the page.
				'cookies'     => [],
				'headers'     => [
					'Cache-Control' => 'no-cache',
				],
				'user-agent'  => 'Adbot-WP-SnippetScan/' . ( defined( 'ADBOT_VERSION' ) ? ADBOT_VERSION : '0.0.0' ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return null;
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Remove Adbot's own GTM snippet from the HTML so it is never mistaken for a
	 * pre-existing third-party install. Mirrors the exact markers emitted by
	 * Snippet_Injector.
	 */
	private static function strip_adbot_snippet( string $html ): string {
		$html = preg_replace( '/<!-- Google Tag Manager \(Adbot\) -->.*?<!-- End Google Tag Manager \(Adbot\) -->/s', '', $html );
		$html = preg_replace( '/<!-- Google Tag Manager \(noscript\) \(Adbot\) -->.*?<!-- End Google Tag Manager \(noscript\) \(Adbot\) -->/s', '', (string) $html );
		// Defensive: drop any element still carrying the data-adbot="gtm" marker.
		$html = preg_replace( '/<(script|noscript)[^>]*data-adbot=["\']gtm["\'][^>]*>.*?<\/\1>/is', '', (string) $html );

		return null === $html ? '' : $html;
	}
}
