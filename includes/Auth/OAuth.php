<?php

namespace Adbot\Auth;

use Google\Client as Google_Client;

class OAuth {

	private Google_Client $client;

	private const SCOPES = [
		'https://www.googleapis.com/auth/userinfo.email',
		'https://www.googleapis.com/auth/userinfo.profile',
		// GA4
		'https://www.googleapis.com/auth/analytics.readonly',
		'https://www.googleapis.com/auth/analytics.edit',
		'https://www.googleapis.com/auth/analytics.provision',
		// GTM
		'https://www.googleapis.com/auth/tagmanager.readonly',
		'https://www.googleapis.com/auth/tagmanager.edit.containers',
		'https://www.googleapis.com/auth/tagmanager.publish',
		// Google Ads
		'https://www.googleapis.com/auth/adwords',
		// Google Merchant Center
		'https://www.googleapis.com/auth/content',
		// Google Business Profile
		'https://www.googleapis.com/auth/business.manage',
	];

	public function __construct() {
		$this->client = new Google_Client();
		$this->client->setClientId( $this->get_config( 'GOOGLE_CLIENT_ID' ) );
		$this->client->setClientSecret( $this->get_config( 'GOOGLE_CLIENT_SECRET' ) );
		$this->client->setRedirectUri( $this->get_redirect_uri() );
		$this->client->setAccessType( 'offline' );
		$this->client->setPrompt( 'consent' );
		$this->client->setIncludeGrantedScopes( true );
		$this->client->setScopes( self::SCOPES );
	}

	public function get_scopes(): array {
		return self::SCOPES;
	}

	public function get_auth_url( string $state ): string {
		$this->client->setState( $state );
		return $this->client->createAuthUrl();
	}

	public function exchange_code( string $code ): array {
		$token = $this->client->fetchAccessTokenWithAuthCode( $code );

		if ( isset( $token['error'] ) ) {
			throw new \RuntimeException( 'Token exchange failed: ' . ( $token['error_description'] ?? $token['error'] ) );
		}

		return $token;
	}

	public function get_user_info( string $access_token ): array {
		$response = wp_remote_get( 'https://www.googleapis.com/oauth2/v2/userinfo', [
			'headers' => [ 'Authorization' => 'Bearer ' . $access_token ],
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Failed to fetch user info: ' . $response->get_error_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['id'] ) ) {
			throw new \RuntimeException( 'Invalid user info response.' );
		}

		return $body;
	}

	public function get_client(): Google_Client {
		return $this->client;
	}

	private function get_redirect_uri(): string {
		return rest_url( 'adbot/v1/auth/google/callback' );
	}

	private function get_config( string $name ): string {
		// Check for WordPress constant first, then env var.
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
