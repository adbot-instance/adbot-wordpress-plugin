<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

class Audit_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/audit', [
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'run_audit' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'containerPath' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return is_string( $value )
								&& (bool) preg_match( '#^accounts/[0-9]+/containers/[0-9]+$#', $value );
						},
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/audit/(?P<id>[a-z0-9_-]+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_audit' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'id' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return is_string( $value ) && (bool) preg_match( '/^[a-z0-9_-]+$/', $value );
					},
				],
			],
		] );
	}

	public function run_audit( WP_REST_Request $request ): WP_REST_Response {
		try {
			$result = ( new Client() )->post( '/audit/run', [
				'container_path' => (string) $request->get_param( 'containerPath' ),
			] );
			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'audit_run', $e );
			return $this->backend_error( $e );
		}
	}

	public function get_audit( WP_REST_Request $request ): WP_REST_Response {
		$id = (string) $request->get_param( 'id' );
		try {
			$result = ( new Client() )->get( '/audit/' . rawurlencode( $id ) );
			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'audit_get', $e );
			return $this->backend_error( $e );
		}
	}
}
