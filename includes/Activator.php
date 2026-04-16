<?php

namespace Adbot;

class Activator {

	public static function activate(): void {
		// Set default options.
		if ( false === get_option( 'adbot_settings' ) ) {
			update_option( 'adbot_settings', [
				'exclude_admins' => true,
				'debug_mode'     => false,
			] );
		}

		// Mark the installed version.
		update_option( 'adbot_version', ADBOT_VERSION );
	}
}
