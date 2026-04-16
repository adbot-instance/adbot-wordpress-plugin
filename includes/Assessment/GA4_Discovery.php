<?php

namespace Adbot\Assessment;

use Adbot\Google\Analytics;

/**
 * Discover the GA4 property matching a website URL.
 * Ported from Adbot-Tracking-Prototype/lib/assessment/ga4-discovery.ts
 */
class GA4_Discovery {

	private Analytics $analytics;

	public function __construct( Analytics $analytics ) {
		$this->analytics = $analytics;
	}

	public function discover_for_url( string $url ): ?array {
		return $this->analytics->discover_for_url( $url );
	}
}
