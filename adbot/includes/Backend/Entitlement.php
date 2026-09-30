<?php

namespace Adbot\Backend;

use Adbot\Consent;
use Adbot\Consent_Required_Exception;

/**
 * Owns the plugin's view of "this site has paid".
 *
 * The paid flag lives in the `adbot_onboarding` option, but the backend is the
 * authority: a customer can settle by EFT long after abandoning the Paystack
 * checkout, and the admin verifies that in /admin/payments with nothing to push
 * back to the site. So whenever we believe we are unpaid, we ask — cheaply, and
 * at most once a minute — rather than leaving a paid customer behind a paywall.
 *
 * Both the payments controller and the onboarding controller route their paid
 * writes through here so the option can only ever be written one way.
 */
class Entitlement {

	public const OPTION = 'adbot_onboarding';

	/** Negative results only. A paid site never re-checks. */
	private const CACHE_KEY = 'adbot_payment_status';
	private const CACHE_TTL = 60;
	/**
	 * Backoff after a failed call. resolve_state() runs on every read and
	 * write of the onboarding state, so without this an unreachable backend
	 * (or a revoked site token) makes every wizard interaction wait out a
	 * fresh HTTP timeout. Shorter than the negative TTL so the site picks the
	 * payment up promptly once the backend is back.
	 */
	private const CACHE_TTL_ERROR = 30;

	public static function is_paid(): bool {
		$state = get_option( self::OPTION, [] );
		return is_array( $state ) && ! empty( $state['paid'] );
	}

	/**
	 * Ask the backend whether this site holds a paid payment, and record it if
	 * so. Returns true when the site is (now) entitled.
	 *
	 * Safe to call on any request: it short-circuits once paid, skips the call
	 * entirely without consent or a site token, caches "not paid" for a minute,
	 * backs off when the backend fails, and swallows those failures — an
	 * unreachable backend must never look like a refund.
	 *
	 * Also honours the suppression a wizard reset sets, so the reconcile can't
	 * undo the reset on the very next state read.
	 */
	public static function sync( string $audit_id = '' ): bool {
		if ( self::is_paid() ) {
			return true;
		}
		if ( self::is_sync_suppressed() ) {
			return false;
		}
		if ( ! Consent::has_consent() || '' === Token_Store::get_site_token() ) {
			return false;
		}
		if ( false !== get_transient( self::CACHE_KEY ) ) {
			return false;
		}

		try {
			$query  = '' !== $audit_id ? [ 'audit_id' => $audit_id ] : [];
			$result = ( new Client() )->get( '/payments/status', $query );
		} catch ( Consent_Required_Exception $e ) {
			set_transient( self::CACHE_KEY, 'error', self::CACHE_TTL_ERROR );
			return false;
		} catch ( Backend_Exception $e ) {
			set_transient( self::CACHE_KEY, 'error', self::CACHE_TTL_ERROR );
			return false;
		}

		return self::apply_status( $result );
	}

	/**
	 * Record a `/payments/status` or `/payments/verify` response. Returns true
	 * when it reported a paid site.
	 */
	public static function apply_status( $result, string $fallback_reference = '' ): bool {
		if ( ! is_array( $result ) || empty( $result['paid'] ) ) {
			set_transient( self::CACHE_KEY, 'unpaid', self::CACHE_TTL );
			return false;
		}

		$reference = (string) ( $result['reference'] ?? $fallback_reference );
		$audit_id  = isset( $result['audit_id'] ) ? (string) $result['audit_id'] : '';

		self::mark_paid( $reference, $audit_id );
		return true;
	}

	/**
	 * Flip local state to paid and park the wizard on the apply step.
	 *
	 * `$audit_id` is the snapshot the payment actually belongs to — the backend
	 * widens its lookup beyond the audit we asked about, so adopting the one it
	 * returns keeps later audit-scoped calls pointed at the right snapshot.
	 */
	public static function mark_paid( string $reference, string $audit_id = '' ): void {
		$state = self::read_state();

		$already = ! empty( $state['paid'] )
			&& ( $state['entitlementRef'] ?? '' ) === $reference
			&& ( '' === $audit_id || ( $state['auditId'] ?? '' ) === $audit_id );
		if ( $already ) {
			return;
		}

		$state['paid']           = true;
		$state['entitlementRef'] = $reference;
		$state['step']           = 'apply';
		$state['pendingRef']     = ''; // checkout resolved — stop resuming it
		$state['syncSuppressed'] = false; // settled — nothing left to suppress
		if ( '' !== $audit_id ) {
			$state['auditId'] = $audit_id;
		}
		if ( ! in_array( 'pay', $state['completedSteps'] ?? [], true ) ) {
			$state['completedSteps'][] = 'pay';
		}

		update_option( self::OPTION, $state, false );
		delete_transient( self::CACHE_KEY );
	}

	public static function defaults(): array {
		return [
			'step'           => 'welcome',
			'completedSteps' => [],
			'paid'           => false,
			'auditId'        => '',
			'entitlementRef' => '',
			'skipped'        => false,
			'pendingRef'     => '',
			'syncSuppressed' => false,
		];
	}

	/**
	 * Whether the background reconcile in sync() is currently held off.
	 *
	 * Set by a wizard reset. The flag lives in the option rather than in a
	 * one-off argument because the wizard re-reads state immediately after
	 * resetting: a guard that covered only the reset response would be undone
	 * by the very next GET. Onboarding_Controller clears it once the user
	 * walks back to the pay step, and mark_paid() clears it on settlement.
	 */
	public static function is_sync_suppressed(): bool {
		$state = self::read_state();
		return ! empty( $state['syncSuppressed'] );
	}

	public static function set_sync_suppressed( bool $suppressed ): void {
		$state                   = self::read_state();
		$state['syncSuppressed'] = $suppressed;
		update_option( self::OPTION, $state, false );
	}

	public static function read_state(): array {
		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return array_merge( self::defaults(), $stored );
	}
}
