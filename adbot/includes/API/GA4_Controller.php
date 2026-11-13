<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

/**
 * Lists the connected account's GA4 properties (proxied from the Adbot backend)
 * and persists the user's chosen Measurement ID, so the setup plan creates GA4
 * tags for the correct property instead of relying on URL auto-discovery.
 */
class GA4_Controller extends REST_Controller {

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/ga4/properties', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_properties' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'refresh' => [
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
				],
			],
		] );

		register_rest_route( $this->namespace, '/ga4/select', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'select_property' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'measurementId' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return 1 === preg_match( '/^G-[A-Z0-9]+$/', (string) $value );
					},
				],
				// Numeric GA4 property id and its name, for the Analytics charts.
				// Optional so older callers (the wizard before 1.0.14) still work.
				'propertyId'    => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return '' === (string) $value || 1 === preg_match( '/^[0-9]+$/', (string) $value );
					},
				],
				'propertyName'  => [
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		] );
	}

	public function list_properties( WP_REST_Request $request ): WP_REST_Response {
		$cache_key = 'adbot_ga4_properties';
		$force     = (bool) $request->get_param( 'refresh' );

		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( $cached, 200 );
			}
		}

		try {
			$result = ( new Client() )->get( '/ga4/properties', [], Client::SLOW_TIMEOUT );
			set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( $result, 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'ga4_properties', $e );
			return $this->backend_error( $e );
		}
	}

	public function select_property( WP_REST_Request $request ): WP_REST_Response {
		$measurement_id = (string) $request->get_param( 'measurementId' );
		update_option( 'adbot_ga4_measurement_id', $measurement_id );

		$property_id = (string) $request->get_param( 'propertyId' );
		if ( '' !== $property_id ) {
			update_option( Insights_Controller::OPTION_GA4_PROPERTY_ID, $property_id, false );
			update_option( Insights_Controller::OPTION_GA4_PROPERTY_NAME, (string) $request->get_param( 'propertyName' ), false );
		}
		Insights_Controller::flush( 'ga4' );

		return new WP_REST_Response( [
			'measurementId' => $measurement_id,
			'propertyId'    => (string) get_option( Insights_Controller::OPTION_GA4_PROPERTY_ID, '' ),
		], 200 );
	}
}
