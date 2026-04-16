<?php

namespace Adbot\Google;

/**
 * Exponential backoff for transient Google Tag Manager API failures.
 */
final class Retry {

	/**
	 * @template T
	 * @param callable():T $callback
	 * @return T
	 */
	public static function run( callable $callback, int $max_attempts = 5, int $base_delay_ms = 400 ): mixed {
		$attempt = 0;

		while ( true ) {
			try {
				return $callback();
			} catch ( \Throwable $e ) {
				++$attempt;

				if ( $attempt >= $max_attempts || ! self::is_retryable( $e ) ) {
					throw $e;
				}

				usleep( (int) ( $base_delay_ms * ( 2 ** ( $attempt - 1 ) ) * 1000 ) );
			}
		}
	}

	private static function is_retryable( \Throwable $e ): bool {
		$code = (int) $e->getCode();

		if ( in_array( $code, [ 408, 429, 500, 502, 503, 504 ], true ) ) {
			return true;
		}

		$msg = strtolower( $e->getMessage() );

		return str_contains( $msg, 'ratelimit' )
			|| str_contains( $msg, 'rate limit' )
			|| str_contains( $msg, 'quota' );
	}
}
