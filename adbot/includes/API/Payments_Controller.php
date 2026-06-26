<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

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
	}

	public function initialize( WP_REST_Request $request ): WP_REST_Response {
		try {
			$state    = $this->read_state();
			$audit_id = (string) ( $state['auditId'] ?? '' );
			if ( '' === $audit_id ) {
				return $this->error_response(
					__( 'Run the tracking audit before unlocking fixes.', 'adbot-tracking-platform' ),
					400
				);
			}
			$result = ( new Client() )->post( '/payments/initialize', [
				'audit_id' => $audit_id,
			] );

			if ( ! empty( $result['reference'] ) ) {
				$state['pendingRef'] = (string) $result['reference'];
				update_option( self::ONBOARDING_OPTION, $state, false );
			}

			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'payments_initialize', $e );
			return $this->backend_error( $e );
		}
	}

	public function verify( WP_REST_Request $request ): WP_REST_Response {
		$reference = (string) $request->get_param( 'reference' );

		try {
			$result = ( new Client() )->post( '/payments/verify', [
				'reference' => $reference,
			] );

			if ( ! empty( $result['paid'] ) ) {
				$this->mark_paid( $reference );
			}

			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'payments_verify', $e );
			return $this->backend_error( $e );
		}
	}

	private function mark_paid( string $reference ): void {
		$state = $this->read_state();
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
}
