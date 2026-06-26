<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;
use Adbot\Tracking\Snippet_Detector;
use Adbot\Tracking\Snippet_Injector;

class Snippet_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/snippet/install', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'install_snippet' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'containerId' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						// Strict boolean: preg_match() returns int 0 on no-match, and
						// WP only rejects on `false === $valid`, so `=== 1` is required.
						return 1 === preg_match( '/^GTM-[A-Z0-9]+$/', (string) $value );
					},
				],
				'containerPath' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
				'ga4MeasurementId' => [
					'required'          => false,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return empty( $value ) || preg_match( '/^G-[A-Z0-9]+$/', $value );
					},
				],
			],
		] );

		register_rest_route( $this->namespace, '/snippet/uninstall', [
			'methods'             => 'DELETE',
			'callback'            => [ $this, 'uninstall_snippet' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/snippet/status', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_snippet_status' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/snippet/rescan', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rescan_snippet' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function install_snippet( WP_REST_Request $request ): WP_REST_Response {
		$container_id    = (string) $request->get_param( 'containerId' );
		$container_path  = (string) $request->get_param( 'containerPath' );
		$ga4_measurement = (string) $request->get_param( 'ga4MeasurementId' );

		// Always remember the chosen container, even if we end up not injecting
		// (because a matching snippet is already on the site). The audit and any
		// later re-scan rely on this.
		update_option( 'adbot_snippet_container_id', $container_id );
		if ( '' !== $container_path ) {
			update_option( 'adbot_snippet_container_path', $container_path );
		}

		// 1. Look at the live site for a matching snippet we did NOT put there.
		//    The local scan works without consent; the backend may refine it.
		$detection = Snippet_Detector::detect( $container_id );

		// 2. Tell the backend so it can record the install AND run its own,
		//    richer detection (headless render fallback, plugin/CMS signatures).
		//    Failure is non-critical — we fall back to the local scan.
		try {
			$backend = ( new Client() )->post( '/gtm/snippet/install', [
				'container_id'       => $container_id,
				'container_path'     => $container_path,
				'ga4_measurement_id' => $ga4_measurement,
				'site_url'           => get_site_url(),
			], Client::SLOW_TIMEOUT );

			if ( isset( $backend['detection'] ) && is_array( $backend['detection'] ) ) {
				$detection = self::merge_detection( $detection, $backend['detection'] );
			}
		} catch ( Consent_Required_Exception | Backend_Exception $e ) {
			$this->log_exception( 'snippet_install_record', $e );
		}

		$external = ! empty( $detection['found'] );

		// 3. Only inject our own snippet when no pre-existing one is on the page;
		//    otherwise every tag would fire twice.
		$this->store_detection( $external, $detection );

		return new WP_REST_Response( [
			'installed'        => true,
			'containerId'      => $container_id,
			'injected'         => ! $external,
			'externalDetected' => $external,
			'detection'        => $detection,
			'snippetHead'      => Snippet_Injector::head_snippet( $container_id ),
			'snippetBody'      => Snippet_Injector::body_snippet( $container_id ),
		], 200 );
	}

	public function uninstall_snippet( WP_REST_Request $request ): WP_REST_Response {
		delete_option( 'adbot_snippet_active' );
		delete_option( 'adbot_snippet_container_id' );
		delete_option( 'adbot_snippet_container_path' );
		delete_option( 'adbot_snippet_external_detected' );
		delete_option( 'adbot_snippet_detection' );

		try {
			( new Client() )->post( '/gtm/snippet/uninstall' );
		} catch ( Consent_Required_Exception | Backend_Exception $e ) {
			$this->log_exception( 'snippet_uninstall_record', $e );
		}

		return new WP_REST_Response( [ 'uninstalled' => true ], 200 );
	}

	public function get_snippet_status( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( $this->snippet_status_payload(), 200 );
	}

	/**
	 * Re-scan the live site for the currently selected container and update
	 * whether Adbot injects its own snippet. Lets the user fix things after they
	 * add or remove a third-party snippet without re-running the whole wizard.
	 */
	public function rescan_snippet( WP_REST_Request $request ): WP_REST_Response {
		$container_id = (string) get_option( 'adbot_snippet_container_id', '' );
		if ( '' === $container_id ) {
			return new WP_REST_Response( [ 'error' => 'no_container' ], 400 );
		}

		$detection = Snippet_Detector::detect( $container_id );

		try {
			$backend = ( new Client() )->post( '/gtm/snippet/detect', [
				'container_id' => $container_id,
				'site_url'     => get_site_url(),
			], Client::SLOW_TIMEOUT );

			if ( isset( $backend['detection'] ) && is_array( $backend['detection'] ) ) {
				$detection = self::merge_detection( $detection, $backend['detection'] );
			}
		} catch ( Consent_Required_Exception | Backend_Exception $e ) {
			$this->log_exception( 'snippet_rescan', $e );
		}

		$external = ! empty( $detection['found'] );
		$this->store_detection( $external, $detection );

		return new WP_REST_Response( $this->snippet_status_payload(), 200 );
	}

	/**
	 * Persist the detection verdict and toggle our injector accordingly.
	 * When a matching third-party snippet is present we keep the injector OFF.
	 */
	private function store_detection( bool $external, array $detection ): void {
		update_option( 'adbot_snippet_active', ! $external );
		update_option( 'adbot_snippet_external_detected', $external );
		update_option( 'adbot_snippet_detection', $detection );
	}

	private function snippet_status_payload(): array {
		$container_id = (string) get_option( 'adbot_snippet_container_id', '' );
		return [
			'containerId'      => $container_id,
			'containerPath'    => (string) get_option( 'adbot_snippet_container_path', '' ),
			'active'           => (bool) get_option( 'adbot_snippet_active' ),
			'externalDetected' => (bool) get_option( 'adbot_snippet_external_detected' ),
			'detection'        => get_option( 'adbot_snippet_detection', null ),
			'snippetHead'      => '' !== $container_id ? Snippet_Injector::head_snippet( $container_id ) : '',
			'snippetBody'      => '' !== $container_id ? Snippet_Injector::body_snippet( $container_id ) : '',
		];
	}

	/**
	 * Combine the local scan with the backend's verdict. The backend wins when
	 * it produced a usable result (no fetch error / anti-bot block); otherwise
	 * we keep the local verdict and just carry across any extra IDs it saw.
	 */
	private static function merge_detection( array $local, array $backend ): array {
		$backend_usable = empty( $backend['error'] ) && empty( $backend['blocked'] );

		$other = array_values( array_unique( array_merge(
			(array) ( $local['other_ids'] ?? [] ),
			(array) ( $backend['other_ids'] ?? [] )
		) ) );

		if ( $backend_usable ) {
			$found = ! empty( $backend['found'] );
			return [
				'found'             => $found,
				'matched_container' => $found,
				'method'            => (string) ( $backend['method'] ?? ( $local['method'] ?? 'none' ) ),
				'evidence'          => (string) ( $backend['evidence'] ?? '' ),
				'other_ids'         => $other,
				'source'            => 'backend',
				'error'             => null,
			];
		}

		// Backend couldn't help (blocked / errored): keep the local verdict.
		$local['other_ids']      = $other;
		$local['backend_status'] = ! empty( $backend['blocked'] ) ? 'blocked' : 'error';
		return $local;
	}
}
