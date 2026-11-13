<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chart data for each Google product page (Analytics, Search Console,
 * Merchant Center, Business Profile) and the Overview previews.
 *
 * Proxies the Adbot backend's GET /insights/<product>, which returns the same
 * ProductInsights shape the Shopify app uses: scorecards, time-series charts
 * and breakdowns, plus a `source` of "live" or "none". The plugin never holds
 * Google credentials and never invents numbers: anything that is not live
 * data comes back as an explained empty state.
 *
 * Expected conditions (nothing selected, an older backend without this route,
 * Business Profile not launched) answer 200 with an empty state rather than an
 * error, so a product page never breaks because one source is unavailable.
 */
class Insights_Controller extends REST_Controller {

	public const PRODUCTS = [ 'ga4', 'gsc', 'gmc', 'gbp' ];
	public const RANGES   = [ '7d', '28d', '90d' ];

	/** GA4 property chosen for reporting (numeric id), set by GA4_Controller. */
	public const OPTION_GA4_PROPERTY_ID   = 'adbot_ga4_property_id';
	public const OPTION_GA4_PROPERTY_NAME = 'adbot_ga4_property_name';

	private const CACHE_PREFIX = 'adbot_insights_';
	private const CACHE_TTL    = 15 * MINUTE_IN_SECONDS;

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/insights/(?P<product>ga4|gsc|gmc|gbp)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_insights' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'range'   => [
					'type'    => 'string',
					'enum'    => self::RANGES,
					'default' => '28d',
				],
				'refresh' => [
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
				],
			],
		] );
	}

	public function get_insights( WP_REST_Request $request ): WP_REST_Response {
		$product = (string) $request->get_param( 'product' );
		$range   = (string) $request->get_param( 'range' );
		if ( ! in_array( $range, self::RANGES, true ) ) {
			$range = '28d';
		}

		// Business Profile is not launched yet: no backend or Google call.
		if ( 'gbp' === $product ) {
			return new WP_REST_Response( $this->empty_state( $product, $range, [ 'comingSoon' => true ] ), 200 );
		}

		$query = [ 'range' => $range ];
		if ( 'ga4' === $product ) {
			$property_id = (string) get_option( self::OPTION_GA4_PROPERTY_ID, '' );
			if ( '' === $property_id ) {
				return new WP_REST_Response(
					$this->empty_state( $product, $range, [
						'emptyReason' => __( 'Choose a GA4 property first.', 'adbot-tracking-platform' ),
					] ),
					200
				);
			}
			$query['property_id'] = $property_id;
		}

		$cache_key = self::CACHE_PREFIX . $product . '_' . $range;
		if ( ! $request->get_param( 'refresh' ) ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( $cached, 200 );
			}
		}

		try {
			$result = ( new Client() )->get( '/insights/' . $product, $query, Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			if ( 404 === $e->status() ) {
				// A backend that predates charts. Not an error for the user.
				return new WP_REST_Response(
					$this->empty_state( $product, $range, [
						'emptyReason' => __( 'Charts for this service are not available yet. Check back after the next Adbot update.', 'adbot-tracking-platform' ),
					] ),
					200
				);
			}
			$this->log_exception( 'insights_' . $product, $e );
			return $this->backend_error( $e );
		}

		$result = $this->sanitize_payload( $result, $product, $range );
		// Only cache real data; an empty state should re-check sooner.
		if ( 'live' === $result['source'] ) {
			set_transient( $cache_key, $result, self::CACHE_TTL );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Drop cached chart data, for one product or all of them. Called when a
	 * selection changes (property, site, account) and on disconnect.
	 */
	public static function flush( ?string $product = null ): void {
		$products = $product ? [ $product ] : self::PRODUCTS;
		foreach ( $products as $p ) {
			foreach ( self::RANGES as $r ) {
				delete_transient( self::CACHE_PREFIX . $p . '_' . $r );
			}
		}
	}

	/**
	 * Guarantee the shape the UI relies on, whatever the backend sends.
	 * The backend is trusted for values; this only fills gaps and enforces
	 * that `source` is never anything but "live" or "none" (no sample data).
	 */
	private function sanitize_payload( array $data, string $product, string $range ): array {
		$base = $this->empty_state( $product, $range );
		$data = array_merge( $base, $data );

		$data['product'] = $product;
		$data['source']  = 'live' === ( $data['source'] ?? '' ) ? 'live' : 'none';
		foreach ( [ 'scorecards', 'charts', 'breakdowns' ] as $key ) {
			if ( ! is_array( $data[ $key ] ) || 'none' === $data['source'] ) {
				$data[ $key ] = 'none' === $data['source'] ? [] : array_values( (array) $data[ $key ] );
			}
		}

		return $data;
	}

	private function empty_state( string $product, string $range, array $overrides = [] ): array {
		return array_merge( [
			'product'      => $product,
			'source'       => 'none',
			'connected'    => false,
			'comingSoon'   => false,
			'rangeKey'     => $range,
			'compareLabel' => __( 'previous period', 'adbot-tracking-platform' ),
			'currency'     => '',
			'scorecards'   => [],
			'charts'       => [],
			'breakdowns'   => [],
			'emptyReason'  => '',
		], $overrides );
	}
}
