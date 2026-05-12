<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Consent;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Backend\Token_Store;

class Onboarding_Controller extends REST_Controller {

	private const OPTION = 'adbot_onboarding';

	private const STEPS = [
		'welcome',
		'connect',
		'property',
		'audit',
		'report',
		'pay',
		'apply',
		'done',
	];

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/onboarding', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'update_state' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'step' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function ( $value ) {
							return is_string( $value ) && in_array( $value, self::STEPS, true );
						},
					],
					'markCompleted' => [
						'sanitize_callback' => function ( $value ) {
							if ( is_array( $value ) ) {
								return array_values( array_filter( array_map( 'sanitize_text_field', $value ) ) );
							}
							if ( is_string( $value ) ) {
								return sanitize_text_field( $value );
							}
							return $value;
						},
						'validate_callback' => function ( $value ) {
							if ( is_array( $value ) ) {
								foreach ( $value as $v ) {
									if ( ! is_string( $v ) || ! in_array( $v, self::STEPS, true ) ) {
										return false;
									}
								}
								return true;
							}
							return is_string( $value ) && in_array( $value, self::STEPS, true );
						},
					],
					'auditId' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'skipped' => [
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/onboarding/reset', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'reset_state' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function get_state( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->resolve_state(), 200 );
	}

	public function update_state( WP_REST_Request $request ): WP_REST_Response {
		$state = $this->read_state();

		$step = $request->get_param( 'step' );
		if ( is_string( $step ) && in_array( $step, self::STEPS, true ) ) {
			$state['step'] = $step;
			if ( ! in_array( $step, $state['completedSteps'], true ) && 'welcome' !== $step ) {
				// no-op; advance() marks completion separately
			}
		}

		$completed = $request->get_param( 'markCompleted' );
		if ( is_array( $completed ) ) {
			foreach ( $completed as $c ) {
				if ( is_string( $c ) && in_array( $c, self::STEPS, true )
					&& ! in_array( $c, $state['completedSteps'], true ) ) {
					$state['completedSteps'][] = $c;
				}
			}
		} elseif ( is_string( $completed ) && in_array( $completed, self::STEPS, true ) ) {
			if ( ! in_array( $completed, $state['completedSteps'], true ) ) {
				$state['completedSteps'][] = $completed;
			}
		}

		if ( $request->has_param( 'auditId' ) ) {
			$state['auditId'] = sanitize_text_field( (string) $request->get_param( 'auditId' ) );
		}

		if ( $request->has_param( 'skipped' ) ) {
			$state['skipped'] = (bool) $request->get_param( 'skipped' );
		}

		update_option( self::OPTION, $state, false );

		return new WP_REST_Response( $this->resolve_state(), 200 );
	}

	public function reset_state( WP_REST_Request $request ): WP_REST_Response {
		delete_option( self::OPTION );
		return new WP_REST_Response( $this->resolve_state(), 200 );
	}

	private function read_state(): array {
		$defaults = [
			'step'           => 'welcome',
			'completedSteps' => [],
			'paid'           => false,
			'auditId'        => '',
			'entitlementRef' => '',
			'skipped'        => false,
		];

		$stored = get_option( self::OPTION, [] );
		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_merge( $defaults, $stored );
	}

	/**
	 * Merge stored state with live status so the resolved step always reflects reality.
	 */
	/**
	 * Same notion of “Google connected” as Status_Controller / Dashboard (backend auth status).
	 */
	private function is_google_connected(): bool {
		if ( ! Consent::has_consent() ) {
			return false;
		}
		if ( '' === Token_Store::get_site_token() ) {
			return false;
		}
		try {
			$result = ( new Client() )->get( '/auth/status' );

			return (bool) ( $result['connected'] ?? false );
		} catch ( Backend_Exception $e ) {
			return false;
		}
	}

	private function resolve_state(): array {
		$state = $this->read_state();

		$connected       = $this->is_google_connected();
		$snippet_active  = (bool) get_option( 'adbot_snippet_active' );
		$container_id    = (string) get_option( 'adbot_snippet_container_id', '' );

		// Reconcile completedSteps with live state in both directions.
		// Add: if the infrastructure for a step is in place, mark it done.
		// Remove: if the prerequisite no longer holds (e.g. user disconnected),
		// drop any downstream "completed" flags so the UI shows the real status.
		$completed = array_values( array_unique( (array) $state['completedSteps'] ) );

		$prune = [];
		if ( ! $connected ) {
			$prune = [ 'connect', 'property', 'audit', 'report', 'pay', 'apply' ];
		} elseif ( ! $snippet_active ) {
			$prune = [ 'property', 'audit', 'report', 'pay', 'apply' ];
		}
		if ( $prune ) {
			$completed = array_values( array_diff( $completed, $prune ) );
		}

		if ( $connected && ! in_array( 'connect', $completed, true ) ) {
			$completed[] = 'connect';
		}
		if ( $snippet_active && ! in_array( 'property', $completed, true ) ) {
			$completed[] = 'property';
		}

		$state['completedSteps'] = $completed;

		// Auto-correct only for the "not yet started" case — don't clobber user's
		// explicit navigation (including Back). If the user hasn't advanced past
		// welcome but live state has progressed, bump them forward so they don't
		// re-do earlier steps.
		$order   = array_flip( self::STEPS );
		$min     = $order[ $state['step'] ] ?? 0;
		$is_init = in_array( $state['step'], [ 'welcome' ], true );

		// If the user disconnected mid-flow, drop them back to connect.
		if ( ! $connected && $min > $order['connect'] ) {
			$state['step'] = 'connect';
		} elseif ( ! $snippet_active && $state['step'] !== 'done' && $min > $order['property'] ) {
			$state['step'] = 'property';
		} elseif ( $is_init && $connected && ! $snippet_active ) {
			$state['step'] = 'property';
		} elseif ( $is_init && $connected && $snippet_active ) {
			$state['step'] = 'audit';
		}

		$state['connected']       = $connected;
		$state['snippetActive']   = $snippet_active;
		$state['containerId']     = $container_id;
		$state['steps']           = self::STEPS;

		// Persist any auto-adjusted completions/step pointer.
		update_option( self::OPTION, [
			'step'           => $state['step'],
			'completedSteps' => array_values( array_unique( $state['completedSteps'] ) ),
			'paid'           => (bool) $state['paid'],
			'auditId'        => (string) $state['auditId'],
			'entitlementRef' => (string) $state['entitlementRef'],
			'skipped'        => (bool) $state['skipped'],
		], false );

		return $state;
	}
}
