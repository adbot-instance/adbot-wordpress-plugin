<?php

namespace Adbot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized opt-in gate for outbound requests to external services.
 *
 * WordPress.org guideline 7 expects explicit user action before transmitting data.
 */
class Consent {
	public const OPTION = 'adbot_consent_given';

	public static function has_consent(): bool {
		return (bool) get_option( self::OPTION, false );
	}

	/**
	 * Throws Consent_Required_Exception if the user has not opted in.
	 *
	 * Callers should catch this at the controller layer and return a 403
	 * WP_Error so end users see a friendly message instead of a 500.
	 */
	public static function require_consent( string $service_label = 'external services' ): void {
		if ( self::has_consent() ) {
			return;
		}

		throw new Consent_Required_Exception(
			sprintf(
				/* translators: %s: third-party service name. */
				esc_html__( 'Adbot consent required before contacting %s.', 'adbot-tracking-platform' ),
				esc_html( $service_label )
			)
		);
	}
}

