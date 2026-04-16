<?php

namespace Adbot\Setup;

/**
 * GTM tag and trigger configuration templates.
 * Ported from Adbot-Tracking-Prototype/lib/setup/templates.ts
 */
class Templates {

	public static function google_tag( string $measurement_id ): array {
		return [
			'name'      => "GA4 Configuration - {$measurement_id}",
			'type'      => 'gaawc',
			'parameter' => [
				[ 'type' => 'template', 'key' => 'measurementId', 'value' => $measurement_id ],
			],
		];
	}

	public static function form_submit_event( string $measurement_id, string $trigger_id ): array {
		return [
			'name'            => 'GA4 Event - form_submit',
			'type'            => 'gaawe',
			'parameter'       => [
				[ 'type' => 'template', 'key' => 'eventName', 'value' => 'form_submit' ],
				[ 'type' => 'template', 'key' => 'measurementIdOverride', 'value' => $measurement_id ],
			],
			'firingTriggerId' => [ $trigger_id ],
		];
	}

	public static function purchase_event( string $measurement_id, string $trigger_id ): array {
		return [
			'name'            => 'GA4 Event - purchase',
			'type'            => 'gaawe',
			'parameter'       => [
				[ 'type' => 'template', 'key' => 'eventName', 'value' => 'purchase' ],
				[ 'type' => 'template', 'key' => 'measurementIdOverride', 'value' => $measurement_id ],
			],
			'firingTriggerId' => [ $trigger_id ],
		];
	}

	public static function click_event( string $measurement_id, string $trigger_id ): array {
		return [
			'name'            => 'GA4 Event - link_click',
			'type'            => 'gaawe',
			'parameter'       => [
				[ 'type' => 'template', 'key' => 'eventName', 'value' => 'link_click' ],
				[ 'type' => 'template', 'key' => 'measurementIdOverride', 'value' => $measurement_id ],
			],
			'firingTriggerId' => [ $trigger_id ],
		];
	}

	// ── Trigger Templates ────────────────────────────────────────────────

	public static function all_pages_trigger(): array {
		return [
			'name' => 'All Pages',
			'type' => 'pageview',
		];
	}

	public static function form_submit_trigger(): array {
		return [
			'name'            => 'Form Submission',
			'type'            => 'formSubmission',
			'filter'          => [
				[
					'type'      => 'matchRegex',
					'parameter' => [
						[ 'type' => 'template', 'key' => 'arg0', 'value' => '{{Page URL}}' ],
						[ 'type' => 'template', 'key' => 'arg1', 'value' => '.*' ],
					],
				],
			],
			'waitForTags'     => [ 'type' => 'boolean', 'value' => 'true' ],
			'checkValidation' => [ 'type' => 'boolean', 'value' => 'true' ],
		];
	}

	public static function purchase_event_trigger(): array {
		return [
			'name'              => 'Custom Event - purchase',
			'type'              => 'customEvent',
			'customEventFilter' => [
				[
					'type'      => 'equals',
					'parameter' => [
						[ 'type' => 'template', 'key' => 'arg0', 'value' => '{{_event}}' ],
						[ 'type' => 'template', 'key' => 'arg1', 'value' => 'purchase' ],
					],
				],
			],
		];
	}

	public static function link_click_trigger(): array {
		return [
			'name'            => 'All Link Clicks',
			'type'            => 'linkClick',
			'waitForTags'     => [ 'type' => 'boolean', 'value' => 'true' ],
			'checkValidation' => [ 'type' => 'boolean', 'value' => 'true' ],
		];
	}
}
