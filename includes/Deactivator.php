<?php

namespace Adbot;

class Deactivator {

	public static function deactivate(): void {
		// Remove the active snippet flag so GTM stops being injected.
		delete_option( 'adbot_snippet_active' );
	}
}
