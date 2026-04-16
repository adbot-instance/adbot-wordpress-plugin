<?php

namespace Adbot\Assessment;

/**
 * Analyze a GTM container's tags and triggers against best practices.
 * Identifies 10 gap types. Ported from Adbot-Tracking-Prototype.
 */
class Gap_Analysis {

	public function analyze( array $tags, array $triggers, ?string $measurement_id ): array {
		$gaps = [];

		// 1. Missing Google Tag (GA4 configuration).
		$ga4_config_tags = array_filter( $tags, fn( $t ) => 'ga4_config' === ( $t['category'] ?? '' ) );
		$has_google_tag  = false;

		foreach ( $ga4_config_tags as $t ) {
			if ( ! $measurement_id || ( $t['measurementId'] ?? '' ) === $measurement_id ) {
				$has_google_tag = true;
				break;
			}
		}

		if ( ! $has_google_tag ) {
			$gaps[] = [
				'id'             => 'missing_google_tag',
				'type'           => 'missing_google_tag',
				'severity'       => 'critical',
				'title'          => 'Missing Google Tag',
				'description'    => $measurement_id
					? "No Google Tag (GA4 Configuration) found for measurement ID {$measurement_id}."
					: 'No Google Tag (GA4 Configuration) found in this container.',
				'recommendation' => 'Create a Google Tag with the GA4 measurement ID to enable basic analytics tracking.',
			];
		}

		// 2. Missing page_view tracking.
		$has_page_view = false;
		foreach ( $tags as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );
			$evt  = $t['eventName'] ?? '';

			if ( ( 'ga4_event' === $cat || 'gaawe' === $type ) &&
				( 'page_view' === $evt || ( str_contains( $name, 'page' ) && str_contains( $name, 'view' ) ) ) ) {
				$has_page_view = true;
				break;
			}
		}

		if ( ! $has_page_view && ! $has_google_tag ) {
			$gaps[] = [
				'id'             => 'missing_page_view',
				'type'           => 'missing_page_view',
				'severity'       => 'critical',
				'title'          => 'Missing Page View Tracking',
				'description'    => 'No page_view event tag or Google Tag found. Page views are not being tracked.',
				'recommendation' => 'The Google Tag automatically sends page_view events. Creating the Google Tag will resolve this.',
			];
		}

		// 3. Missing form submission tracking.
		$has_form_tag     = false;
		$has_form_trigger = false;

		foreach ( $tags as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );
			$evt  = $t['eventName'] ?? '';

			if ( ( 'ga4_event' === $cat || 'gaawe' === $type ) &&
				( in_array( $evt, [ 'form_submit', 'generate_lead' ], true ) ||
				  str_contains( $name, 'form' ) || str_contains( $name, 'submit' ) ||
				  str_contains( $name, 'lead' ) || str_contains( $name, 'contact' ) ) ) {
				$has_form_tag = true;
				break;
			}
		}

		foreach ( $triggers as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );

			if ( 'form_submission' === $cat || 'formSubmission' === $type || str_contains( $name, 'form' ) ) {
				$has_form_trigger = true;
				break;
			}
		}

		if ( ! $has_form_tag && ! $has_form_trigger ) {
			$gaps[] = [
				'id'             => 'missing_form_submit',
				'type'           => 'missing_form_submit',
				'severity'       => 'warning',
				'title'          => 'Missing Form Submission Tracking',
				'description'    => 'No form submission event tag or trigger found. Form submissions are not being tracked.',
				'recommendation' => 'Create a GA4 Event tag for form submissions with a Form Submission trigger.',
			];
		}

		// 4. Missing purchase/ecommerce tracking.
		$has_purchase = false;
		foreach ( $tags as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );
			$evt  = $t['eventName'] ?? '';

			if ( ( 'ga4_event' === $cat || 'gaawe' === $type ) &&
				( 'purchase' === $evt || str_contains( $name, 'purchase' ) ||
				  str_contains( $name, 'transaction' ) || str_contains( $name, 'ecommerce' ) ) ) {
				$has_purchase = true;
				break;
			}
		}

		if ( ! $has_purchase ) {
			$gaps[] = [
				'id'             => 'missing_purchase',
				'type'           => 'missing_purchase',
				'severity'       => 'warning',
				'title'          => 'Missing Purchase/E-commerce Tracking',
				'description'    => 'No purchase or e-commerce event tags found.',
				'recommendation' => 'If this site has e-commerce functionality, create a GA4 Event tag for the purchase event with a Custom Event trigger.',
			];
		}

		// 5. Missing click tracking.
		$has_click_tag     = false;
		$has_click_trigger = false;

		foreach ( $tags as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );
			$evt  = $t['eventName'] ?? '';

			if ( ( 'ga4_event' === $cat || 'gaawe' === $type ) &&
				( 'link_click' === $evt || str_contains( $name, 'click' ) || str_contains( $name, 'link' ) ) ) {
				$has_click_tag = true;
				break;
			}
		}

		foreach ( $triggers as $t ) {
			$cat  = $t['category'] ?? '';
			$type = $t['type'] ?? '';
			$name = strtolower( $t['name'] ?? '' );

			if ( in_array( $cat, [ 'click_all', 'click_links' ], true ) ||
				 in_array( $type, [ 'linkClick', 'click' ], true ) ||
				 str_contains( $name, 'click' ) ) {
				$has_click_trigger = true;
				break;
			}
		}

		if ( ! $has_click_tag && ! $has_click_trigger ) {
			$gaps[] = [
				'id'             => 'missing_click_tracking',
				'type'           => 'missing_click_tracking',
				'severity'       => 'info',
				'title'          => 'No Click Tracking',
				'description'    => 'No click or link click tracking is configured.',
				'recommendation' => 'Consider adding click tracking for important CTAs and outbound links.',
			];
		}

		// 6. Custom event triggers without conditions.
		$unconditioned = array_filter( $triggers, function ( $t ) {
			$is_custom = 'custom_event' === ( $t['category'] ?? '' ) || 'customEvent' === ( $t['type'] ?? '' );
			return $is_custom && 0 === ( $t['filterCount'] ?? 0 );
		} );

		if ( ! empty( $unconditioned ) ) {
			$names = implode( ', ', array_map( fn( $t ) => $t['name'] ?? '', $unconditioned ) );
			$count = count( $unconditioned );

			$gaps[] = [
				'id'             => 'trigger_missing_conditions',
				'type'           => 'trigger_missing_conditions',
				'severity'       => 'warning',
				'title'          => 'Triggers Without Conditions',
				'description'    => "{$count} custom event trigger(s) have no filter conditions: {$names}.",
				'recommendation' => 'Review these triggers to ensure they have appropriate conditions to avoid firing on unintended events.',
			];
		}

		// 7. Duplicate GA4 config tags.
		if ( count( $ga4_config_tags ) > 1 ) {
			$unique_ids = array_unique( array_filter( array_map( fn( $t ) => $t['measurementId'] ?? '', $ga4_config_tags ) ) );

			if ( count( $unique_ids ) > 1 ) {
				$ids_str = implode( ', ', $unique_ids );
				$count   = count( $ga4_config_tags );

				$gaps[] = [
					'id'             => 'duplicate_ga4_config',
					'type'           => 'duplicate_ga4_config',
					'severity'       => 'warning',
					'title'          => 'Multiple GA4 Configuration Tags',
					'description'    => "Found {$count} GA4 Configuration tags with different measurement IDs: {$ids_str}. This can cause duplicate data collection.",
					'recommendation' => 'Review your GA4 Configuration tags and ensure only one is active per measurement ID.',
				];
			}
		}

		// 8. Tags with no firing triggers.
		$tags_no_triggers = array_filter( $tags, function ( $t ) {
			return ! ( $t['paused'] ?? false ) && empty( $t['firingTriggerId'] );
		} );

		if ( ! empty( $tags_no_triggers ) ) {
			$names = implode( ', ', array_map( fn( $t ) => $t['name'] ?? '', $tags_no_triggers ) );
			$count = count( $tags_no_triggers );

			$gaps[] = [
				'id'             => 'tag_no_triggers',
				'type'           => 'tag_no_triggers',
				'severity'       => 'warning',
				'title'          => 'Tags Without Firing Triggers',
				'description'    => "{$count} tag(s) have no firing triggers and will never fire: {$names}.",
				'recommendation' => 'Assign appropriate firing triggers to these tags, or remove them if no longer needed.',
			];
		}

		// 9. Mismatched measurement IDs.
		if ( $measurement_id ) {
			$mismatched = array_filter( $tags, function ( $t ) use ( $measurement_id ) {
				$cat = $t['category'] ?? '';
				$type = $t['type'] ?? '';
				$mid = $t['measurementId'] ?? '';

				return ( 'ga4_event' === $cat || 'gaawe' === $type ) &&
					$mid && $mid !== $measurement_id;
			} );

			if ( ! empty( $mismatched ) ) {
				$details = implode( ', ', array_map( fn( $t ) => ( $t['name'] ?? '' ) . ' -> ' . ( $t['measurementId'] ?? '' ), $mismatched ) );
				$count   = count( $mismatched );

				$gaps[] = [
					'id'             => 'mismatched_measurement_id',
					'type'           => 'mismatched_measurement_id',
					'severity'       => 'warning',
					'title'          => 'Mismatched Measurement IDs',
					'description'    => "{$count} GA4 Event tag(s) use a different measurement ID than the discovered property ({$measurement_id}): {$details}.",
					'recommendation' => 'Verify these tags are intentionally sending to a different GA4 property, or update them to use the correct measurement ID.',
				];
			}
		}

		// 10. Paused tags.
		$paused_tags = array_filter( $tags, fn( $t ) => $t['paused'] ?? false );

		if ( ! empty( $paused_tags ) ) {
			$names = implode( ', ', array_map( fn( $t ) => $t['name'] ?? '', $paused_tags ) );
			$count = count( $paused_tags );

			$gaps[] = [
				'id'             => 'paused_tags',
				'type'           => 'paused_tags',
				'severity'       => 'info',
				'title'          => 'Paused Tags',
				'description'    => "{$count} tag(s) are currently paused: {$names}.",
				'recommendation' => 'Review paused tags and either resume them if still needed or remove them to keep the container clean.',
			];
		}

		return $gaps;
	}
}
