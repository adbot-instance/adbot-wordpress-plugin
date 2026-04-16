<?php

namespace Adbot\Auth;

/**
 * AES-256-GCM encryption compatible with the JS implementation
 * in Adbot-Shopify-App and Adbot-Tracking-Prototype.
 *
 * Format: iv(hex):tag(hex):ciphertext(hex)
 *
 * Uses the same TOKEN_ENCRYPTION_KEY (base64-encoded 32 bytes)
 * shared across all Adbot surfaces.
 */
class Encryption {

	private const ALGORITHM  = 'aes-256-gcm';
	private const IV_LENGTH  = 16;
	private const TAG_LENGTH = 16;

	private string $key;

	public function __construct() {
		$key = defined( 'ADBOT_ENCRYPTION_KEY' )
			? ADBOT_ENCRYPTION_KEY
			: getenv( 'TOKEN_ENCRYPTION_KEY' );

		if ( ! $key ) {
			throw new \RuntimeException( 'TOKEN_ENCRYPTION_KEY is not configured.' );
		}

		$this->key = base64_decode( $key, true );

		if ( strlen( $this->key ) !== 32 ) {
			throw new \RuntimeException( 'TOKEN_ENCRYPTION_KEY must be 32 bytes (base64-encoded).' );
		}
	}

	public function encrypt( string $plaintext ): string {
		$iv = random_bytes( self::IV_LENGTH );

		$ciphertext = openssl_encrypt(
			$plaintext,
			self::ALGORITHM,
			$this->key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			self::TAG_LENGTH
		);

		if ( false === $ciphertext ) {
			throw new \RuntimeException( 'Encryption failed.' );
		}

		return bin2hex( $iv ) . ':' . bin2hex( $tag ) . ':' . bin2hex( $ciphertext );
	}

	public function decrypt( string $encoded ): string {
		$parts = explode( ':', $encoded );

		if ( count( $parts ) !== 3 ) {
			throw new \RuntimeException( 'Invalid ciphertext format.' );
		}

		[ $iv_hex, $tag_hex, $ciphertext_hex ] = $parts;

		$iv         = hex2bin( $iv_hex );
		$tag        = hex2bin( $tag_hex );
		$ciphertext = hex2bin( $ciphertext_hex );

		if ( false === $iv || false === $tag || false === $ciphertext ) {
			throw new \RuntimeException( 'Invalid hex encoding in ciphertext.' );
		}

		$plaintext = openssl_decrypt(
			$ciphertext,
			self::ALGORITHM,
			$this->key,
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);

		if ( false === $plaintext ) {
			throw new \RuntimeException( 'Decryption failed.' );
		}

		return $plaintext;
	}
}
