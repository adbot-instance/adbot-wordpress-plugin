<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Database\Supabase;
use Adbot\Payments\Paystack;

class Payments_Controller extends REST_Controller {

	private const ONBOARDING_OPTION = 'adbot_onboarding';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/payments/initialize', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'initialize' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/payments/verify', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'reference' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );

		register_rest_route( $this->namespace, '/payments/webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'webhook' ],
			'permission_callback' => '__return_true', // Verified via HMAC signature.
		] );
	}

	public function initialize( WP_REST_Request $request ): WP_REST_Response {
		$account_id = get_option( 'adbot_account_id' );
		if ( ! $account_id ) {
			return $this->error_response( 'Not connected to Google.', 401 );
		}

		$email = $this->resolve_email( $account_id );
		if ( ! $email ) {
			return $this->error_response( 'No email on file. Reconnect Google to continue.', 400 );
		}

		try {
			$paystack = new Paystack();
			$audit_id = (string) ( get_option( self::ONBOARDING_OPTION )['auditId'] ?? '' );

			$result = $paystack->initialize_transaction(
				$email,
				Paystack::fix_price_subunits(),
				[
					'accountId' => $account_id,
					'siteUrl'   => get_site_url(),
					'auditId'   => $audit_id,
				]
			);

			// Stash pending reference so webhook can correlate even if client disappears.
			$state               = $this->read_state();
			$state['pendingRef'] = $result['reference'];
			update_option( self::ONBOARDING_OPTION, $state, false );

			return new WP_REST_Response( [
				'reference'        => $result['reference'],
				'authorizationUrl' => $result['authorizationUrl'],
				'accessCode'       => $result['accessCode'],
				'publicKey'        => Paystack::public_key(),
				'amount'           => Paystack::fix_price_subunits(),
				'currency'         => Paystack::currency(),
				'email'            => $email,
			], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Paystack initialize failed: ' . $e->getMessage(), 500 );
		}
	}

	public function verify( WP_REST_Request $request ): WP_REST_Response {
		$reference = (string) $request->get_param( 'reference' );

		try {
			$paystack = new Paystack();
			$result   = $paystack->verify_transaction( $reference );

			if ( 'success' !== $result['status'] ) {
				return $this->error_response( 'Payment not successful: ' . $result['status'], 402 );
			}

			if ( (int) $result['amount'] < Paystack::fix_price_subunits() ) {
				return $this->error_response( 'Payment amount below expected price.', 402 );
			}

			$this->mark_paid( $reference );

			return new WP_REST_Response( [
				'paid'      => true,
				'reference' => $reference,
			], 200 );
		} catch ( \Exception $e ) {
			return $this->error_response( 'Verify failed: ' . $e->getMessage(), 500 );
		}
	}

	public function webhook( WP_REST_Request $request ): WP_REST_Response {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'x_paystack_signature' ) ?: '';

		$paystack = new Paystack();
		if ( ! $paystack->verify_webhook_signature( $payload, $signature ) ) {
			return new WP_REST_Response( [ 'ok' => false ], 401 );
		}

		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) ) {
			return new WP_REST_Response( [ 'ok' => false ], 400 );
		}

		if ( ( $event['event'] ?? '' ) === 'charge.success' ) {
			$data      = $event['data'] ?? [];
			$reference = (string) ( $data['reference'] ?? '' );
			$status    = (string) ( $data['status'] ?? '' );
			$amount    = (int) ( $data['amount'] ?? 0 );

			if ( 'success' === $status && $reference && $amount >= Paystack::fix_price_subunits() ) {
				$this->mark_paid( $reference );
			}
		}

		return new WP_REST_Response( [ 'ok' => true ], 200 );
	}

	private function mark_paid( string $reference ): void {
		$state = $this->read_state();

		// Idempotent: if already paid with this reference, do nothing.
		if ( ! empty( $state['paid'] ) && ( $state['entitlementRef'] ?? '' ) === $reference ) {
			return;
		}

		$state['paid']           = true;
		$state['entitlementRef'] = $reference;
		$state['step']           = 'apply';
		if ( ! in_array( 'pay', $state['completedSteps'] ?? [], true ) ) {
			$state['completedSteps'][] = 'pay';
		}

		update_option( self::ONBOARDING_OPTION, $state, false );
	}

	private function read_state(): array {
		$defaults = [
			'step'           => 'welcome',
			'completedSteps' => [],
			'paid'           => false,
			'auditId'        => '',
			'entitlementRef' => '',
			'skipped'        => false,
			'pendingRef'     => '',
		];
		$stored = get_option( self::ONBOARDING_OPTION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}
		return array_merge( $defaults, $stored );
	}

	private function resolve_email( string $account_id ): ?string {
		try {
			$supabase = new Supabase();
			$account  = $supabase->get_account( $account_id );
			if ( $account && ! empty( $account['email'] ) ) {
				return (string) $account['email'];
			}
		} catch ( \Exception $e ) {
			// Fall through to admin email.
		}
		$admin = get_option( 'admin_email' );
		return is_string( $admin ) ? $admin : null;
	}
}
