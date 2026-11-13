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

		// The product feed is served through a rewrite rule registered on
		// `init`. Rules only take effect once flushed into the cached set, so
		// without this the feed URL 404s until permalinks are next saved.
		\Adbot\Feed\Feed_Endpoint::flush_rules();

		// Daily refresh of the Search Console / Merchant Center statuses.
		// No-ops on every request until a channel is actually connected.
		\Adbot\Channels\Sync::schedule();

		// Send the next admin request to the Adbot screen so the welcome wizard can load first.
		set_transient( 'adbot_activation_redirect', '1', 120 );
	}
}
