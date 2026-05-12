<?php

namespace Adbot;

class Deactivator {

	public static function deactivate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites( [ 'fields' => 'ids' ] );
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::deactivate_single_site();
				restore_current_blog();
			}
			return;
		}

		self::deactivate_single_site();
	}

	private static function deactivate_single_site(): void {
		// Remove the active snippet flag so GTM stops being injected.
		delete_option( 'adbot_snippet_active' );

		// Clear transient caches.
		Cleanup::delete_transients_by_prefix( 'adbot_containers' );
		Cleanup::delete_transients_by_prefix( 'adbot_audit_' );
		Cleanup::delete_transients_by_prefix( 'adbot_site_challenge' );
		Cleanup::delete_transients_by_prefix( 'adbot_site_register_attempt' );
	}
}
