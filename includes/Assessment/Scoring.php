<?php

namespace Adbot\Assessment;

/**
 * Calculate a health score (0-100) based on identified gaps.
 * Ported from Adbot-Tracking-Prototype/lib/assessment/scoring.ts
 */
class Scoring {

	private const SEVERITY_WEIGHTS = [
		'critical' => 25,
		'warning'  => 10,
		'info'     => 3,
	];

	public function calculate( array $gaps ): int {
		$deductions = 0;

		foreach ( $gaps as $gap ) {
			$severity    = $gap['severity'] ?? 'info';
			$deductions += self::SEVERITY_WEIGHTS[ $severity ] ?? 0;
		}

		return max( 0, 100 - $deductions );
	}
}
