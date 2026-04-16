<?php

namespace Adbot\Setup;

/**
 * Reconcile desired tags/triggers against existing ones to avoid duplicates.
 * Ported from Adbot-Tracking-Prototype/lib/setup/reconcile.ts
 */
class Reconcile {

	public static function match_existing_trigger( array $existing_triggers, array $desired ): array {
		foreach ( $existing_triggers as $existing ) {
			if ( ( $existing['type'] ?? '' ) !== ( $desired['type'] ?? '' ) ) {
				continue;
			}

			// For customEvent triggers, match on the event filter value.
			if ( 'customEvent' === ( $desired['type'] ?? '' ) ) {
				$desired_event  = self::get_custom_event_value( $desired );
				$existing_event = self::get_custom_event_value( $existing );

				if ( $desired_event && $existing_event === $desired_event ) {
					return [
						'action'       => 'skip',
						'existingId'   => $existing['triggerId'] ?? null,
						'existingPath' => $existing['path'] ?? null,
						'reason'       => "{$existing['type']} trigger for \"{$desired_event}\" already exists as \"{$existing['name']}\"",
					];
				}
				continue;
			}

			// For pageview, formSubmission, linkClick — match by type alone.
			if ( in_array( $desired['type'] ?? '', [ 'pageview', 'formSubmission', 'linkClick' ], true ) ) {
				return [
					'action'       => 'skip',
					'existingId'   => $existing['triggerId'] ?? null,
					'existingPath' => $existing['path'] ?? null,
					'reason'       => "{$existing['type']} trigger already exists as \"{$existing['name']}\"",
				];
			}

			// Fallback: match by name.
			if ( strtolower( $existing['name'] ?? '' ) === strtolower( $desired['name'] ?? '' ) ) {
				return [
					'action'       => 'skip',
					'existingId'   => $existing['triggerId'] ?? null,
					'existingPath' => $existing['path'] ?? null,
					'reason'       => "Trigger \"{$existing['name']}\" already exists",
				];
			}
		}

		return [ 'action' => 'create', 'reason' => 'No matching trigger found' ];
	}

	public static function match_existing_tag( array $existing_tags, array $desired, string $measurement_id ): array {
		foreach ( $existing_tags as $existing ) {
			if ( ( $existing['type'] ?? '' ) !== ( $desired['type'] ?? '' ) ) {
				continue;
			}

			// GA4 Configuration tag — match by type + measurementId.
			if ( 'gaawc' === ( $desired['type'] ?? '' ) ) {
				$existing_mid = self::get_param_value( $existing['parameter'] ?? [], 'measurementId' );
				if ( $existing_mid === $measurement_id ) {
					return [
						'action'       => 'skip',
						'existingId'   => $existing['tagId'] ?? null,
						'existingPath' => $existing['path'] ?? null,
						'reason'       => "GA4 Configuration tag for {$measurement_id} already exists as \"{$existing['name']}\"",
					];
				}
				continue;
			}

			// GA4 Event tag — match by type + eventName.
			if ( 'gaawe' === ( $desired['type'] ?? '' ) ) {
				$desired_event  = self::get_param_value( $desired['parameter'] ?? [], 'eventName' );
				$existing_event = self::get_param_value( $existing['parameter'] ?? [], 'eventName' );

				if ( $desired_event && $existing_event === $desired_event ) {
					$existing_ref = self::get_param_value( $existing['parameter'] ?? [], 'measurementIdOverride' )
						?? self::get_param_value( $existing['parameter'] ?? [], 'measurementId' );
					$desired_ref  = self::get_param_value( $desired['parameter'] ?? [], 'measurementIdOverride' )
						?? self::get_param_value( $desired['parameter'] ?? [], 'measurementId' );

					if ( $existing_ref === $desired_ref ) {
						return [
							'action'       => 'skip',
							'existingId'   => $existing['tagId'] ?? null,
							'existingPath' => $existing['path'] ?? null,
							'reason'       => "GA4 Event tag for \"{$desired_event}\" already exists as \"{$existing['name']}\"",
						];
					}

					return [
						'action'       => 'update',
						'existingId'   => $existing['tagId'] ?? null,
						'existingPath' => $existing['path'] ?? null,
						'reason'       => "GA4 Event tag for \"{$desired_event}\" exists but has different configuration",
					];
				}
				continue;
			}
		}

		return [ 'action' => 'create', 'reason' => 'No matching tag found' ];
	}

	private static function get_param_value( array $params, string $key ): ?string {
		foreach ( $params as $param ) {
			if ( ( $param['key'] ?? '' ) === $key ) {
				return $param['value'] ?? null;
			}
		}
		return null;
	}

	private static function get_custom_event_value( array $trigger ): ?string {
		$filters = $trigger['customEventFilter'] ?? [];
		if ( empty( $filters ) ) {
			return null;
		}

		$params = $filters[0]['parameter'] ?? [];
		foreach ( $params as $param ) {
			if ( 'arg1' === ( $param['key'] ?? '' ) ) {
				return $param['value'] ?? null;
			}
		}

		return null;
	}
}
