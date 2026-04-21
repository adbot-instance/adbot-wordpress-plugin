<?php

namespace Adbot\Backend;

/**
 * Encrypted storage for the per-site bearer token issued by the Adbot backend.
 *
 * Uses AES-256-GCM with a key derived from WordPress AUTH_KEY / SECURE_AUTH_KEY
 * salts. No key is shipped with the plugin.
 */
class Token_Store {
	private const OPTION_SITE_ID    = 'adbot_site_id';
	private const OPTION_SITE_TOKEN = 'adbot_site_token_enc';

	public static function get_site_id(): string {
		$value = get_option( self::OPTION_SITE_ID, '' );
		return is_string( $value ) ? $value : '';
	}

	public static function set_site_id( string $site_id ): void {
		update_option( self::OPTION_SITE_ID, $site_id, false );
	}

	public static function get_site_token(): string {
		$cipher = (string) get_option( self::OPTION_SITE_TOKEN, '' );
		if ( '' === $cipher ) {
			return '';
		}
		$plain = self::decrypt( $cipher );
		return $plain ?? '';
	}

	public static function set_site_token( string $token ): void {
		update_option( self::OPTION_SITE_TOKEN, self::encrypt( $token ), false );
	}

	public static function forget(): void {
		delete_option( self::OPTION_SITE_ID );
		delete_option( self::OPTION_SITE_TOKEN );
	}

	private static function key(): string {
		$salts  = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : '' ) . ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : '' );
		$salts .= ( defined( 'LOGGED_IN_KEY' ) ? LOGGED_IN_KEY : '' ) . ( defined( 'NONCE_KEY' ) ? NONCE_KEY : '' );
		return hash_hmac( 'sha256', 'adbot-site-token-key-v1', $salts, true );
	}

	private static function encrypt( string $plain ): string {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $plain ); // Fallback; still obscures raw token in DB.
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return base64_encode( $plain );
		}
		return 'v1:' . base64_encode( $iv . $tag . $cipher );
	}

	private static function decrypt( string $stored ): ?string {
		if ( str_starts_with( $stored, 'v1:' ) && function_exists( 'openssl_decrypt' ) ) {
			$raw = base64_decode( substr( $stored, 3 ), true );
			if ( false === $raw || strlen( $raw ) < 29 ) {
				return null;
			}
			$iv     = substr( $raw, 0, 12 );
			$tag    = substr( $raw, 12, 16 );
			$cipher = substr( $raw, 28 );
			$plain  = openssl_decrypt( $cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag );
			return false === $plain ? null : $plain;
		}
		$decoded = base64_decode( $stored, true );
		return false === $decoded ? null : $decoded;
	}
}
