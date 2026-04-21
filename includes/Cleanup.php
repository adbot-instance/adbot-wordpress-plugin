<?php

namespace Adbot;

/**
 * Cleanup helpers for uninstall/deactivation.
 */
class Cleanup {
	/**
	 * Delete all transients whose names start with a given prefix.
	 *
	 * Deletes both transient values and their timeout rows.
	 */
	public static function delete_transients_by_prefix( string $prefix ): void {
		global $wpdb;

		$like = $wpdb->esc_like( '_transient_' . $prefix ) . '%';
		$rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $option_name ) {
			if ( is_string( $option_name ) ) {
				delete_option( $option_name );
			}
		}

		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . $prefix ) . '%';
		$timeout_rows = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like_timeout
			)
		);
		foreach ( $timeout_rows as $option_name ) {
			if ( is_string( $option_name ) ) {
				delete_option( $option_name );
			}
		}
	}
}

