<?php

namespace Adbot\Assessment;

/**
 * Detect whether a GTM container snippet is installed on a website.
 * Ported from Adbot-Tracking-Prototype/lib/assessment/snippet-detection.ts
 */
class Snippet_Detection {

	public function detect( string $website_url, string $container_id ): array {
		$response = wp_remote_get( $website_url, [
			'timeout'    => 10,
			'user-agent' => 'Adbot/1.0 (WordPress Plugin)',
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'installed' => false,
				'evidence'  => 'Could not fetch website: ' . $response->get_error_message(),
			];
		}

		$html            = wp_remote_retrieve_body( $response );
		$normalized      = strtolower( $html );
		$public_id_upper = strtoupper( $container_id );
		$public_id_lower = strtolower( $container_id );

		// Collect any GTM container IDs present on the page.
		preg_match_all( '/GTM-[0-9A-Z]+/i', $html, $id_matches );
		$all_ids = array_unique( array_map( 'strtoupper', $id_matches[0] ?? [] ) );

		// Check for gtm.js URL with this container ID.
		$escaped_id   = preg_quote( $public_id_upper, '/' );
		$has_gtm_js   = (bool) preg_match( '/googletagmanager\.com\/gtm\.js\?[^"\'<>]*\bid=' . $escaped_id . '\b/i', $html );

		// Check for noscript iframe.
		$has_noscript  = (bool) preg_match( '/googletagmanager\.com\/ns\.html\?[^"\'<>]*\bid=' . $escaped_id . '\b/i', $html );

		// Check for inline loader.
		$has_inline    = (bool) preg_match( '/[\'"]' . $escaped_id . '[\'"]\s*\)/i', $html );

		$has_any_signal = str_contains( $normalized, 'googletagmanager.com/gtm.js' )
			|| str_contains( $normalized, 'googletagmanager.com/ns.html' )
			|| str_contains( $normalized, 'gtm.start' )
			|| str_contains( $normalized, 'datalayer' );

		if ( $has_gtm_js && $has_noscript ) {
			return [
				'installed' => true,
				'evidence'  => "Found GTM snippet ({$container_id}) in both <head> script and <noscript> iframe",
			];
		}

		if ( $has_gtm_js || $has_inline ) {
			return [
				'installed' => true,
				'evidence'  => $has_gtm_js
					? "Found GTM snippet ({$container_id}) via gtm.js URL reference"
					: "Found GTM snippet ({$container_id}) via inline loader snippet",
			];
		}

		if ( $has_noscript ) {
			return [
				'installed' => true,
				'evidence'  => "Found GTM noscript iframe for {$container_id} (main script tag not detected — may be loaded dynamically)",
			];
		}

		// Check if other GTM containers are present.
		$other_ids          = array_diff( $all_ids, [ $public_id_upper ] );
		$has_this_id        = in_array( $public_id_upper, $all_ids, true ) || str_contains( $normalized, $public_id_lower );

		if ( ! $has_this_id && ! empty( $other_ids ) ) {
			return [
				'installed' => false,
				'evidence'  => 'Detected GTM on the page, but not container ' . $container_id . '. Found other container ID(s): ' . implode( ', ', $other_ids ),
			];
		}

		if ( $has_any_signal && ! $has_this_id ) {
			return [
				'installed' => false,
				'evidence'  => "Detected GTM-related signals but could not confirm container {$container_id} in the initial HTML. This can happen if the container is injected after cookie consent.",
			];
		}

		return [
			'installed' => false,
			'evidence'  => "GTM container {$container_id} not found in page HTML",
		];
	}
}
