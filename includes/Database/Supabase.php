<?php

namespace Adbot\Database;

/**
 * Supabase REST (PostgREST) client for the shared identity layer.
 *
 * Uses service_role key for server-side access.
 * Tables: Account, GoogleConnection, GoogleProperty, wordpress_sites, snippet_installs
 */
class Supabase {

	private string $url;
	private string $key;

	public function __construct() {
		$this->url = $this->get_config( 'SUPABASE_URL' );
		$this->key = $this->get_config( 'SUPABASE_SERVICE_KEY' );
	}

	// ── Account ──────────────────────────────────────────────────────────

	public function get_account( string $account_id ): ?array {
		return $this->request( 'GET', "/rest/v1/Account?id=eq.{$account_id}&select=*" );
	}

	public function upsert_account( array $data ): array {
		$result = $this->request(
			'POST',
			'/rest/v1/Account',
			$data,
			[
				'Prefer'         => 'resolution=merge-duplicates,return=representation',
				'On-Conflict'    => 'googleSub',
			]
		);

		if ( is_array( $result ) && isset( $result[0] ) ) {
			return $result[0];
		}

		return $result;
	}

	// ── GoogleConnection ─────────────────────────────────────────────────

	public function get_google_connection( string $account_id ): ?array {
		return $this->request( 'GET', "/rest/v1/GoogleConnection?accountId=eq.{$account_id}&select=*" );
	}

	public function upsert_google_connection( array $data ): array {
		$result = $this->request(
			'POST',
			'/rest/v1/GoogleConnection',
			$data,
			[
				'Prefer'      => 'resolution=merge-duplicates,return=representation',
				'On-Conflict' => 'accountId',
			]
		);

		if ( is_array( $result ) && isset( $result[0] ) ) {
			return $result[0];
		}

		return $result;
	}

	public function update_google_connection( string $account_id, array $data ): void {
		$this->request(
			'PATCH',
			"/rest/v1/GoogleConnection?accountId=eq.{$account_id}",
			$data
		);
	}

	public function delete_google_connection( string $account_id ): void {
		$this->request(
			'DELETE',
			"/rest/v1/GoogleConnection?accountId=eq.{$account_id}"
		);
	}

	// ── GoogleProperty ───────────────────────────────────────────────────

	public function get_google_properties( string $account_id, ?string $kind = null ): array {
		$url = "/rest/v1/GoogleProperty?accountId=eq.{$account_id}&select=*";
		if ( $kind ) {
			$url .= "&kind=eq.{$kind}";
		}

		$result = $this->request( 'GET', $url );
		return is_array( $result ) ? $result : [];
	}

	public function upsert_google_property( array $data ): array {
		$result = $this->request(
			'POST',
			'/rest/v1/GoogleProperty',
			$data,
			[
				'Prefer'      => 'resolution=merge-duplicates,return=representation',
				'On-Conflict' => 'accountId,kind,externalId',
			]
		);

		if ( is_array( $result ) && isset( $result[0] ) ) {
			return $result[0];
		}

		return $result;
	}

	// ── WordPressSite ────────────────────────────────────────────────────

	public function upsert_wordpress_site( array $data ): array {
		$result = $this->request(
			'POST',
			'/rest/v1/wordpress_sites',
			$data,
			[
				'Prefer'      => 'resolution=merge-duplicates,return=representation',
				'On-Conflict' => 'site_url',
			]
		);

		if ( is_array( $result ) && isset( $result[0] ) ) {
			// Also store site ID locally.
			update_option( 'adbot_site_id', $result[0]['id'] );
			return $result[0];
		}

		return $result;
	}

	public function get_wordpress_site( string $site_id ): ?array {
		return $this->request( 'GET', "/rest/v1/wordpress_sites?id=eq.{$site_id}&select=*" );
	}

	// ── SnippetInstall ───────────────────────────────────────────────────

	public function upsert_snippet_install( array $data ): array {
		$result = $this->request(
			'POST',
			'/rest/v1/snippet_installs',
			$data,
			[
				'Prefer'      => 'resolution=merge-duplicates,return=representation',
				'On-Conflict' => 'wordpress_site_id',
			]
		);

		if ( is_array( $result ) && isset( $result[0] ) ) {
			return $result[0];
		}

		return $result;
	}

	public function delete_snippet_install( string $site_id ): void {
		$this->request(
			'DELETE',
			"/rest/v1/snippet_installs?wordpress_site_id=eq.{$site_id}"
		);
	}

	// ── HTTP ─────────────────────────────────────────────────────────────

	private function request( string $method, string $path, ?array $body = null, array $extra_headers = [] ): mixed {
		$url = rtrim( $this->url, '/' ) . $path;

		$headers = array_merge(
			[
				'apikey'        => $this->key,
				'Authorization' => 'Bearer ' . $this->key,
				'Content-Type'  => 'application/json',
			],
			$extra_headers
		);

		$args = [
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 15,
		];

		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		// For GET requests on single-row lookups, request a single object.
		if ( 'GET' === $method && ! str_contains( $path, 'select=*&' ) ) {
			$headers['Accept'] = 'application/vnd.pgrst.object+json';
			$args['headers']   = $headers;
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Supabase request failed: ' . $response->get_error_message() );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status >= 400 ) {
			$error = json_decode( $body, true );
			throw new \RuntimeException(
				'Supabase error (' . $status . '): ' . ( $error['message'] ?? $body )
			);
		}

		if ( empty( $body ) ) {
			return null;
		}

		return json_decode( $body, true );
	}

	private function get_config( string $name ): string {
		if ( defined( $name ) ) {
			return constant( $name );
		}

		$value = getenv( $name );
		if ( $value ) {
			return $value;
		}

		throw new \RuntimeException( $name . ' is not configured.' );
	}
}
