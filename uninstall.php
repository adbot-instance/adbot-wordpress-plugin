<?php
/**
 * Adbot uninstall handler.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 * Cleans up wp_options entries created by the plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'adbot_account_id' );
delete_option( 'adbot_site_id' );
delete_option( 'adbot_snippet_container_id' );
delete_option( 'adbot_snippet_active' );
delete_option( 'adbot_settings' );
delete_option( 'adbot_version' );
