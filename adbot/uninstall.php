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

/**
 * Delete plugin data for the current site.
 */
function adbot_uninstall_single_site(): void {
	// Options.
	delete_option( 'adbot_account_id' );
	delete_option( 'adbot_site_id' );
	delete_option( 'adbot_site_token_enc' );
	delete_option( 'adbot_snippet_container_id' );
	delete_option( 'adbot_snippet_container_path' );
	delete_option( 'adbot_snippet_active' );
	delete_option( 'adbot_settings' );
	delete_option( 'adbot_consent_given' );
	delete_option( 'adbot_onboarding' );
	delete_option( 'adbot_version' );

	// Written by the GA4 / snippet-detection features but never cleaned up
	// before this — an uninstall left them behind in wp_options.
	delete_option( 'adbot_ga4_measurement_id' );
	delete_option( 'adbot_snippet_external_detected' );
	delete_option( 'adbot_snippet_detection' );
	delete_option( 'adbot_google_connected_last' );

	// Search Console channel.
	delete_option( 'adbot_gsc_site_url' );
	delete_option( 'adbot_gsc_sitemap_url' );
	delete_option( 'adbot_gsc_sitemap_submitted_at' );
	delete_option( 'adbot_gsc_verified_at' );
	delete_option( 'adbot_site_verification_token' );

	// Merchant Center channel.
	delete_option( 'adbot_gmc_account_name' );
	delete_option( 'adbot_gmc_account_label' );
	delete_option( 'adbot_gmc_claimed_at' );
	delete_option( 'adbot_gmc_feed_registered_at' );
	delete_option( 'adbot_feed_token' );
	delete_option( 'adbot_channel_status' );

	// Daily channel poll.
	wp_clear_scheduled_hook( 'adbot_daily_channel_sync' );

	// Transients (by prefix).
	global $wpdb;

	$adbot_transient_prefixes = [
		'adbot_containers',
		'adbot_audit_',
		'adbot_site_challenge',
		'adbot_site_register_attempt',
		// Previously leaked on uninstall.
		'adbot_ga4_properties',
		'adbot_google_connected_cache',
		'adbot_payment_status',
		// Channel caches.
		'adbot_gsc_',
		'adbot_gmc_',
	];

	foreach ( $adbot_transient_prefixes as $prefix ) {
		$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like_timeout ) );
	}
}

if ( is_multisite() ) {
	$adbot_site_ids = get_sites( [ 'fields' => 'ids' ] );
	foreach ( $adbot_site_ids as $adbot_site_id ) {
		switch_to_blog( (int) $adbot_site_id );
		adbot_uninstall_single_site();
		restore_current_blog();
	}
} else {
	adbot_uninstall_single_site();
}
