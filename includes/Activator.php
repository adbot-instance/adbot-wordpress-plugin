<?php

namespace Adbot;

class Activator {

	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( [ 'fields' => 'ids' ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_single_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_single_site();
	}

	private static function activate_single_site(): void {
		// Seed default options only on first install — never overwrite existing values.
		add_option( 'adbot_settings', [
			'exclude_admins' => true,
			'debug_mode'     => false,
		] );

		// Consent gate for outbound requests (defaults to false, user opts in via UI).
		add_option( \Adbot\Consent::OPTION, false );

		// Mark the installed version.
		update_option( 'adbot_version', ADBOT_VERSION );
	}
}
