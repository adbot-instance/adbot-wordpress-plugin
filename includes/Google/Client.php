<?php

namespace Adbot\Google;

use Google\Client as Google_Client;
use Adbot\Auth\Encryption;
use Adbot\Auth\OAuth;
use Adbot\Database\Supabase;

class Client {

	/**
	 * Returns an authenticated Google client for the given Adbot account.
	 * Transparently refreshes the access token if it's within 5 minutes of expiry
	 * and persists the refreshed credentials back to GoogleConnection.
	 */
	public static function get_authenticated_client( string $account_id ): Google_Client {
		$supabase   = new Supabase();
		$connection = $supabase->get_google_connection( $account_id );

		if ( ! $connection ) {
			throw new \RuntimeException( 'No Google connection found for account.' );
		}

		$encryption   = new Encryption();
		$access_token  = $encryption->decrypt( $connection['accessTokenEnc'] );
		$refresh_token = $encryption->decrypt( $connection['refreshTokenEnc'] );

		$oauth  = new OAuth();
		$client = $oauth->get_client();

		$client->setAccessToken( [
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'expires_in'    => max( 0, strtotime( $connection['tokenExpiry'] ) - time() ),
			'created'       => time(),
		] );

		// Auto-refresh if within 5 minutes of expiry.
		$expiry_time = strtotime( $connection['tokenExpiry'] );
		if ( $expiry_time < time() + 300 ) {
			$client->fetchAccessTokenWithRefreshToken( $refresh_token );
			$new_token = $client->getAccessToken();

			if ( ! empty( $new_token['access_token'] ) ) {
				$new_expiry = gmdate(
					'Y-m-d\TH:i:s\Z',
					time() + ( $new_token['expires_in'] ?? 3600 )
				);

				$update_data = [
					'accessTokenEnc' => $encryption->encrypt( $new_token['access_token'] ),
					'tokenExpiry'    => $new_expiry,
				];

				if ( ! empty( $new_token['refresh_token'] ) ) {
					$update_data['refreshTokenEnc'] = $encryption->encrypt( $new_token['refresh_token'] );
				}

				$supabase->update_google_connection( $account_id, $update_data );
			}
		}

		return $client;
	}
}
