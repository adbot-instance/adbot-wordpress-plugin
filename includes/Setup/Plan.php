<?php

namespace Adbot\Setup;

/**
 * Generate a setup plan based on identified gaps.
 * Ported from Adbot-Tracking-Prototype/lib/setup/plan.ts
 */
class Plan {

	public function generate( array $gaps, ?string $measurement_id ): array {
		$operations = [];
		$gap_types  = array_column( $gaps, 'type' );

		// Google Tag is always needed if missing — it's the foundation.
		if ( in_array( 'missing_google_tag', $gap_types, true ) ) {
			$operations[] = [
				'id'          => 'trigger_all_pages',
				'type'        => 'create_trigger',
				'name'        => 'All Pages Trigger',
				'description' => 'Fires on every page load to activate the Google Tag.',
				'config'      => Templates::all_pages_trigger(),
				'dependsOn'   => [],
				'gapType'     => 'missing_google_tag',
			];

			$operations[] = [
				'id'          => 'tag_google_tag',
				'type'        => 'create_tag',
				'name'        => "Google Tag ({$measurement_id})",
				'description' => "Creates the GA4 configuration tag with measurement ID {$measurement_id}.",
				'config'      => Templates::google_tag( $measurement_id ?? '' ),
				'dependsOn'   => [ 'trigger_all_pages' ],
				'gapType'     => 'missing_google_tag',
			];
		}

		// Form submission tracking.
		if ( in_array( 'missing_form_submit', $gap_types, true ) ) {
			$operations[] = [
				'id'          => 'trigger_form_submit',
				'type'        => 'create_trigger',
				'name'        => 'Form Submission Trigger',
				'description' => 'Fires when a form is submitted on any page.',
				'config'      => Templates::form_submit_trigger(),
				'dependsOn'   => [],
				'gapType'     => 'missing_form_submit',
			];

			$operations[] = [
				'id'          => 'tag_form_submit',
				'type'        => 'create_tag',
				'name'        => 'GA4 Event - form_submit',
				'description' => 'Sends a form_submit event to GA4 when a form is submitted.',
				'config'      => Templates::form_submit_event( $measurement_id ?? '', 'PLACEHOLDER' ),
				'dependsOn'   => [ 'trigger_form_submit' ],
				'gapType'     => 'missing_form_submit',
			];
		}

		// Purchase tracking.
		if ( in_array( 'missing_purchase', $gap_types, true ) ) {
			$operations[] = [
				'id'          => 'trigger_purchase',
				'type'        => 'create_trigger',
				'name'        => 'Purchase Event Trigger',
				'description' => 'Fires on the custom purchase dataLayer event.',
				'config'      => Templates::purchase_event_trigger(),
				'dependsOn'   => [],
				'gapType'     => 'missing_purchase',
			];

			$operations[] = [
				'id'          => 'tag_purchase',
				'type'        => 'create_tag',
				'name'        => 'GA4 Event - purchase',
				'description' => 'Sends a purchase event to GA4 for e-commerce tracking.',
				'config'      => Templates::purchase_event( $measurement_id ?? '', 'PLACEHOLDER' ),
				'dependsOn'   => [ 'trigger_purchase' ],
				'gapType'     => 'missing_purchase',
			];
		}

		// Click tracking.
		if ( in_array( 'missing_click_tracking', $gap_types, true ) ) {
			$operations[] = [
				'id'          => 'trigger_link_click',
				'type'        => 'create_trigger',
				'name'        => 'Link Click Trigger',
				'description' => 'Fires on all link clicks.',
				'config'      => Templates::link_click_trigger(),
				'dependsOn'   => [],
				'gapType'     => 'missing_click_tracking',
			];

			$operations[] = [
				'id'          => 'tag_link_click',
				'type'        => 'create_tag',
				'name'        => 'GA4 Event - link_click',
				'description' => 'Sends a link_click event to GA4.',
				'config'      => Templates::click_event( $measurement_id ?? '', 'PLACEHOLDER' ),
				'dependsOn'   => [ 'trigger_link_click' ],
				'gapType'     => 'missing_click_tracking',
			];
		}

		return $operations;
	}
}
