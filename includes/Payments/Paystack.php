<?php

namespace Adbot\Payments;

/**
 * Paystack REST client.
 * https://paystack.com/docs/api/
 */
class Paystack {

	private const BASE = 'https://api.paystack.co';

	public static function public_key(): string {
		return (string) ( getenv( 'PAYSTACK_PUBLIC_KEY' ) ?: '' );
	}

	public static function secret_key(): string {
		return (string) ( getenv( 'PAYSTACK_SECRET_KEY' ) ?: '' );
	}

	public static function currency(): string {
		return (string) ( getenv( 'ADBOT_FIX_CURRENCY' ) ?: 'ZAR' );
	}

	/**
	 * Fix price in the smallest currency unit (kobo / cents).
	 */
	public static function fix_price_subunits(): int {
		$major = (float) ( getenv( 'ADBOT_FIX_PRICE' ) ?: '499' );
		return (int) round( $major * 100 );
	}

	public static function fix_price_major(): float {
		return self::fix_price_subunits() / 100;
	}

	public function initialize_transaction( string $email, int $amount_subunits, array $metadata = [] ): array {
		$ref = 'adbot_' . wp_generate_password( 12, false, false );

		$body = [
			'email'        => $email,
			'amount'       => $amount_subunits,
			'currency'     => self::currency(),
			'reference'    => $ref,
			'metadata'     => $metadata,
			'callback_url' => admin_url( 'admin.php?page=adbot' ),
		];

		$response = wp_remote_post( self::BASE . '/transaction/initialize', [
			'headers' => $this->auth_headers(),
			'body'    => wp_json_encode( $body ),
			'timeout' => 20,
		] );

		$decoded = $this->decode( $response );
		if ( empty( $decoded['status'] ) ) {
			throw new \Exception( $decoded['message'] ?? 'Paystack initialize failed.' );
		}

		return [
			'reference'        => $decoded['data']['reference'] ?? $ref,
			'authorizationUrl' => $decoded['data']['authorization_url'] ?? '',
			'accessCode'       => $decoded['data']['access_code'] ?? '',
		];
	}

	public function verify_transaction( string $reference ): array {
		$response = wp_remote_get( self::BASE . '/transaction/verify/' . rawurlencode( $reference ), [
			'headers' => $this->auth_headers(),
			'timeout' => 20,
		] );

		$decoded = $this->decode( $response );
		if ( empty( $decoded['status'] ) ) {
			throw new \Exception( $decoded['message'] ?? 'Paystack verify failed.' );
		}

		$data = $decoded['data'] ?? [];
		return [
			'status'    => $data['status'] ?? 'unknown',
			'reference' => $data['reference'] ?? $reference,
			'amount'    => (int) ( $data['amount'] ?? 0 ),
			'currency'  => $data['currency'] ?? '',
			'paidAt'    => $data['paid_at'] ?? null,
			'metadata'  => $data['metadata'] ?? [],
			'customer'  => $data['customer'] ?? [],
			'raw'       => $data,
		];
	}

	public function verify_webhook_signature( string $payload, string $signature ): bool {
		$secret = self::secret_key();
		if ( '' === $secret || '' === $signature ) {
			return false;
		}
		$expected = hash_hmac( 'sha512', $payload, $secret );
		return hash_equals( $expected, $signature );
	}

	private function auth_headers(): array {
		return [
			'Authorization' => 'Bearer ' . self::secret_key(),
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
	}

	private function decode( $response ): array {
		if ( is_wp_error( $response ) ) {
			throw new \Exception( $response->get_error_message() );
		}
		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );
		if ( ! is_array( $json ) ) {
			throw new \Exception( 'Invalid Paystack response.' );
		}
		return $json;
	}
}
