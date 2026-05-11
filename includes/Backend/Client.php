<?php

namespace Adbot\Backend;

use Adbot\Consent;
use Adbot\Consent_Required_Exception;

/**
 * HTTP client for the Adbot backend (Vercel).
 *
 * The backend owns all third-party credentials (Google client secret,
 * Paystack secret, Supabase service key). The plugin never contacts those
 * services directly.
 */
class Client {
	public const DEFAULT_BASE = 'https://adbot-tracking-platform.vercel.app/api/wp';

	public static function base_url(): string {
		$override = defined( 'ADBOT_API_BASE' ) ? (string) ADBOT_API_BASE : '';
		return untrailingslashit( '' !== $override ? $override : self::DEFAULT_BASE );
	}

	public function get( string $path, array $query = [] ): array {
		return $this->request( 'GET', $path, $query, null );
	}

	public function post( string $path, array $body = [] ): array {
		return $this->request( 'POST', $path, [], $body );
	}

	public function delete( string $path ): array {
		return $this->request( 'DELETE', $path, [], null );
	}

	/**
	 * Raw request — used by /sites/register and /sites/verify which do NOT
	 * require the site_token yet.
	 */
	public function unauthenticated_post( string $path, array $body ): array {
		return $this->request( 'POST', $path, [], $body, false );
	}

	private function request( string $method, string $path, array $query, ?array $body, bool $require_auth = true ): array {
		Consent::require_consent( 'the Adbot backend' );

		$url = self::base_url() . '/' . ltrim( $path, '/' );
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'User-Agent'   => 'Adbot-WP/' . ( defined( 'ADBOT_VERSION' ) ? ADBOT_VERSION : '0.0.0' ) . '; ' . get_site_url(),
		];

		if ( $require_auth ) {
			$token = Token_Store::get_site_token();
			if ( '' === $token ) {
				throw new Backend_Exception( 'not_registered', __( 'Adbot has not finished registering this site with the Adbot service. Please reload the page in a moment.', 'adbot' ), 503 );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		];
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Backend_Exception( 'network_error', $response->get_error_message(), 502 );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$raw    = (string) wp_remote_retrieve_body( $response );
		$json   = json_decode( $raw, true );

		if ( $status >= 200 && $status < 300 ) {
			return is_array( $json ) ? $json : [];
		}

		$code    = is_array( $json ) && isset( $json['code'] ) ? (string) $json['code'] : 'backend_error';
		$message = is_array( $json ) && isset( $json['message'] )
			? (string) $json['message']
			: sprintf( /* translators: %d: HTTP status code */ __( 'Adbot backend returned HTTP %d.', 'adbot' ), $status );

		throw new Backend_Exception( $code, $message, $status );
	}
}
