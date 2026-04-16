<?php
/**
 * Load `.env.local` then `.env` from the plugin directory.
 * Does not override variables already present in the environment (server / wp-config.php).
 *
 * @package Adbot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parse dotenv-style files and apply Adbot-specific aliases.
 */
function adbot_load_env_files(): void {
	static $done = false;

	if ( $done ) {
		return;
	}
	$done = true;

	if ( ! defined( 'ADBOT_PLUGIN_DIR' ) ) {
		return;
	}

	foreach ( [ '.env.local', '.env' ] as $file ) {
		$path = ADBOT_PLUGIN_DIR . $file;

		if ( ! is_readable( $path ) ) {
			continue;
		}

		$lines = file( $path, FILE_IGNORE_NEW_LINES );

		if ( false === $lines ) {
			continue;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || str_starts_with( $line, '#' ) ) {
				continue;
			}

			if ( ! str_contains( $line, '=' ) ) {
				continue;
			}

			$key   = trim( substr( $line, 0, strpos( $line, '=' ) ) );
			$value = trim( substr( $line, strpos( $line, '=' ) + 1 ) );

			if ( '' === $key ) {
				continue;
			}

			// Strip matching quotes.
			if (
				( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) && strlen( $value ) >= 2 )
				|| ( str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) && strlen( $value ) >= 2 )
			) {
				$value = substr( $value, 1, -1 );
			}

			if ( '' !== $value && false === getenv( $key ) ) {
				putenv( "{$key}={$value}" );
				$_ENV[ $key ]    = $value;
				$_SERVER[ $key ] = $value;
			}
		}
	}

	adbot_apply_env_aliases();
}

/**
 * Map common framework-style names to what Adbot PHP expects.
 */
function adbot_apply_env_aliases(): void {
	$map = [
		'GOOGLE_CLIENT_ID'     => [ 'client_id', 'GOOGLE_CLIENT_ID' ],
		'GOOGLE_CLIENT_SECRET' => [ 'client_secret', 'GOOGLE_CLIENT_SECRET' ],
		'SUPABASE_URL'         => [ 'NEXT_PUBLIC_SUPABASE_URL', 'SUPABASE_URL' ],
		'SUPABASE_SERVICE_KEY' => [
			'SUPABASE_SERVICE_KEY',
			'SUPABASE_ANON_KEY',
			'NEXT_SUPABASE_ANON_KEY',
			'NEXT_PUBLIC_SUPABASE_ANON_KEY',
			'NEXT_PUBLIC_SUPABASE_PUBLISHABLE_DEFAULT_KEY',
		],
		'TOKEN_ENCRYPTION_KEY' => [ 'TOKEN_ENCRYPTION_KEY', 'ADBOT_ENCRYPTION_KEY' ],
	];

	foreach ( $map as $canonical => $sources ) {
		if ( getenv( $canonical ) ) {
			continue;
		}

		foreach ( $sources as $src ) {
			$v = getenv( $src );

			if ( $v ) {
				putenv( "{$canonical}={$v}" );
				$_ENV[ $canonical ]    = $v;
				$_SERVER[ $canonical ] = $v;
				break;
			}
		}
	}
}
