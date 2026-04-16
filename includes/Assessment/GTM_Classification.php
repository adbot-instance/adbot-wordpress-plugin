<?php

namespace Adbot\Assessment;

/**
 * Classify GTM tags, triggers, and variables by type.
 * Ported from Adbot-Tracking-Prototype/lib/assessment/gtm-classification.ts
 */
class GTM_Classification {

	private const TAG_TYPE_MAP = [
		'gaawc'   => [ 'category' => 'ga4_config', 'label' => 'GA4 Configuration' ],
		'googtag' => [ 'category' => 'ga4_config', 'label' => 'Google Tag' ],
		'gtag'    => [ 'category' => 'ga4_config', 'label' => 'Google Tag' ],
		'gaawe'   => [ 'category' => 'ga4_event', 'label' => 'GA4 Event' ],
		'awct'    => [ 'category' => 'google_ads_conversion', 'label' => 'Google Ads Conversion' ],
		'sp'      => [ 'category' => 'google_ads_remarketing', 'label' => 'Google Ads Remarketing' ],
		'html'    => [ 'category' => 'custom_html', 'label' => 'Custom HTML' ],
		'img'     => [ 'category' => 'custom_image', 'label' => 'Custom Image' ],
		'gclidw'  => [ 'category' => 'conversion_linker', 'label' => 'Conversion Linker' ],
		'flc'     => [ 'category' => 'floodlight_counter', 'label' => 'Floodlight Counter' ],
		'fls'     => [ 'category' => 'floodlight_sales', 'label' => 'Floodlight Sales' ],
	];

	private const TRIGGER_TYPE_MAP = [
		'pageview'          => [ 'category' => 'pageview', 'label' => 'Page View' ],
		'domReady'          => [ 'category' => 'dom_ready', 'label' => 'DOM Ready' ],
		'windowLoaded'      => [ 'category' => 'window_loaded', 'label' => 'Window Loaded' ],
		'click'             => [ 'category' => 'click_all', 'label' => 'Click - All Elements' ],
		'linkClick'         => [ 'category' => 'click_links', 'label' => 'Click - Just Links' ],
		'formSubmission'    => [ 'category' => 'form_submission', 'label' => 'Form Submission' ],
		'customEvent'       => [ 'category' => 'custom_event', 'label' => 'Custom Event' ],
		'timer'             => [ 'category' => 'timer', 'label' => 'Timer' ],
		'historyChange'     => [ 'category' => 'history_change', 'label' => 'History Change' ],
		'scrollDepth'       => [ 'category' => 'scroll_depth', 'label' => 'Scroll Depth' ],
		'elementVisibility' => [ 'category' => 'element_visibility', 'label' => 'Element Visibility' ],
		'youtubeVideo'      => [ 'category' => 'youtube_video', 'label' => 'YouTube Video' ],
	];

	private const VARIABLE_TYPE_MAP = [
		'v'    => 'Data Layer Variable',
		'jsm'  => 'Custom JavaScript',
		'j'    => 'JavaScript Variable',
		'k'    => '1st Party Cookie',
		'c'    => 'Constant',
		'gas'  => 'Google Analytics Settings',
		'aev'  => 'Auto-Event Variable',
		'u'    => 'URL',
		'd'    => 'DOM Element',
		'e'    => 'Custom Event',
		'f'    => 'HTTP Referrer',
		'r'    => 'Random Number',
		'smm'  => 'Lookup Table',
		'remm' => 'RegEx Table',
		'vis'  => 'Element Visibility',
		'ctv'  => 'Container Version Number',
	];

	public static function classify_tag( string $type ): array {
		return self::TAG_TYPE_MAP[ $type ] ?? [ 'category' => 'other', 'label' => $type ];
	}

	public static function classify_trigger( string $type ): array {
		return self::TRIGGER_TYPE_MAP[ $type ] ?? [ 'category' => 'other', 'label' => $type ];
	}

	public static function classify_variable( string $type ): string {
		return self::VARIABLE_TYPE_MAP[ $type ] ?? $type;
	}
}
