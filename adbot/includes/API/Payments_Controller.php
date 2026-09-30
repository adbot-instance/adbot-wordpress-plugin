<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Backend\Entitlement;
use Adbot\Consent_Required_Exception;

class Payments_Controller extends REST_Controller {

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

		register_rest_route( $this->namespace, '/payments/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	/**
	 * Backend-side payment status for this site. Catches payments settled
	 * outside the inline Paystack checkout — an EFT the Adbot admin verified
	 * by hand, a charge booked against a different audit, or a purchase made
	 * on adbot.co.za before the plugin was installed — and unlocks the wizard
	 * the same way a verified Paystack charge does.
	 */
	public function status( WP_REST_Request $request ): WP_REST_Response {
		try {
			$state = $this->read_state();
			$query = [];
			if ( ! empty( $state['auditId'] ) ) {
				$query['audit_id'] = (string) $state['auditId'];
			}
			$result = ( new Client() )->get( '/payments/status', $query );

			Entitlement::apply_status( $result );

			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'payments_status', $e );
			return $this->backend_error( $e );
		}
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
				update_option( Entitlement::OPTION, $state, false );
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

			Entitlement::apply_status( $result, $reference );

			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'payments_verify', $e );
			return $this->backend_error( $e );
		}
	}

	private function read_state(): array {
		return Entitlement::read_state();
	}
}
