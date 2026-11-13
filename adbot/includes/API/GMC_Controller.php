<?php

namespace Adbot\API;

use WP_REST_Request;
use WP_REST_Response;
use Adbot\Backend\Client;
use Adbot\Backend\Backend_Exception;
use Adbot\Channels\Store;
use Adbot\Feed\Feed_Endpoint;
use Adbot\Consent_Required_Exception;

/**
 * Merchant Center channel: pick an account, claim the website, register the
 * product feed as a scheduled fetch.
 *
 * The feed itself is served by Adbot\Feed\Feed_Endpoint and fetched by Google
 * directly — product data never travels through the Adbot backend. All this
 * controller sends is the URL.
 */
class GMC_Controller extends REST_Controller {

	public const OPTION_ACCOUNT_NAME  = 'adbot_gmc_account_name';
	public const OPTION_ACCOUNT_LABEL = 'adbot_gmc_account_label';
	public const OPTION_CLAIMED_AT    = 'adbot_gmc_claimed_at';
	public const OPTION_REGISTERED_AT = 'adbot_gmc_feed_registered_at';

	private const ACCOUNTS_CACHE = 'adbot_gmc_accounts';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/gmc/accounts', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'list_accounts' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'refresh' => [
					'type'              => 'boolean',
					'sanitize_callback' => 'rest_sanitize_boolean',
				],
			],
		] );

		register_rest_route( $this->namespace, '/gmc/select', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'select_account' ],
			'permission_callback' => [ $this, 'permission_callback' ],
			'args'                => [
				'accountName' => [
					'required'          => true,
					'type'              => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return 1 === preg_match( '#^accounts/\d+$#', (string) $value );
					},
				],
			],
		] );

		register_rest_route( $this->namespace, '/gmc/claim', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'claim_website' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );

		register_rest_route( $this->namespace, '/gmc/feed', [
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'feed_status' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'register_feed' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/gmc/feed/fetch', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'fetch_feed' ],
			'permission_callback' => [ $this, 'permission_callback' ],
		] );
	}

	public function list_accounts( WP_REST_Request $request ): WP_REST_Response {
		$force = (bool) $request->get_param( 'refresh' );

		if ( ! $force ) {
			$cached = get_transient( self::ACCOUNTS_CACHE );
			if ( is_array( $cached ) ) {
				return new WP_REST_Response( $this->decorate( $cached ), 200 );
			}
		}

		try {
			$result = ( new Client() )->get( '/gmc/accounts', [], Client::SLOW_TIMEOUT );
			set_transient( self::ACCOUNTS_CACHE, $result, 5 * MINUTE_IN_SECONDS );
			return new WP_REST_Response( $this->decorate( $result ), 200 );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gmc_accounts', $e );
			return $this->backend_error( $e );
		}
	}

	/**
	 * Attach the local store facts the card needs to decide what to offer:
	 * whether WooCommerce is even present, the feed URL, and why the feed
	 * cannot be registered yet when that is the case.
	 */
	private function decorate( array $result ): array {
		$readiness = Store::feed_readiness();

		$result['selected']     = (string) get_option( self::OPTION_ACCOUNT_NAME, '' );
		$result['claimedAt']    = (string) get_option( self::OPTION_CLAIMED_AT, '' );
		$result['registeredAt'] = (string) get_option( self::OPTION_REGISTERED_AT, '' );
		$result['feed']         = [
			'url'             => Feed_Endpoint::url(),
			'wooActive'       => Store::is_woocommerce_active(),
			'ready'           => $readiness['ready'],
			'blockedReason'   => $readiness['reason'],
			'warnings'        => $readiness['warnings'] ?? [],
			'contentLanguage' => Store::content_language(),
			'feedLabel'       => Store::feed_label(),
			'timeZone'        => Store::time_zone(),
		];

		return $result;
	}

	public function select_account( WP_REST_Request $request ): WP_REST_Response {
		$account_name = (string) $request->get_param( 'accountName' );

		try {
			$result = ( new Client() )->post( '/gmc/select', [
				'account_name' => $account_name,
			], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gmc_select', $e );
			return $this->backend_error( $e );
		}

		$account = is_array( $result['account'] ?? null ) ? $result['account'] : [];
		update_option( self::OPTION_ACCOUNT_NAME, $account_name, false );
		Insights_Controller::flush( 'gmc' );
		update_option( self::OPTION_ACCOUNT_LABEL, (string) ( $account['accountName'] ?? '' ), false );

		return new WP_REST_Response( [
			'accountName'  => $account_name,
			'accountLabel' => (string) ( $account['accountName'] ?? '' ),
		], 200 );
	}

	public function claim_website( WP_REST_Request $request ): WP_REST_Response {
		try {
			$result = ( new Client() )->post( '/gmc/claim', [], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gmc_claim', $e );
			return $this->backend_error( $e );
		}

		$claimed    = (bool) ( $result['claimed'] ?? false );
		$claimed_at = (string) ( $result['claimed_at'] ?? '' );

		if ( $claimed ) {
			update_option( self::OPTION_CLAIMED_AT, '' !== $claimed_at ? $claimed_at : gmdate( 'c' ), false );
		}

		return new WP_REST_Response( [ 'claimed' => $claimed, 'claimedAt' => $claimed_at ], 200 );
	}

	public function register_feed( WP_REST_Request $request ): WP_REST_Response {
		$readiness = Store::feed_readiness();
		if ( ! $readiness['ready'] ) {
			return $this->error_response( $readiness['reason'], 409 );
		}

		$feed_url = Feed_Endpoint::url();
		if ( '' === $feed_url ) {
			return $this->error_response(
				__( 'Adbot could not build a product feed URL for this site.', 'adbot-tracking-platform' ),
				409
			);
		}

		try {
			$result = ( new Client() )->post( '/gmc/feed', [
				'feed_url'         => $feed_url,
				'content_language' => Store::content_language(),
				'feed_label'       => Store::feed_label(),
				'time_zone'        => Store::time_zone(),
			], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gmc_feed_register', $e );
			return $this->backend_error( $e );
		}

		$registered_at = (string) ( $result['registered_at'] ?? gmdate( 'c' ) );
		update_option( self::OPTION_REGISTERED_AT, $registered_at, false );

		return new WP_REST_Response( [
			'registered'   => (bool) ( $result['registered'] ?? false ),
			'feedUrl'      => $feed_url,
			'registeredAt' => $registered_at,
			'dataSource'   => $result['data_source'] ?? null,
		], 200 );
	}

	public function fetch_feed( WP_REST_Request $request ): WP_REST_Response {
		try {
			$result = ( new Client() )->post( '/gmc/feed/fetch', [], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			$this->log_exception( 'gmc_feed_fetch', $e );
			return $this->backend_error( $e );
		}

		return new WP_REST_Response( [ 'fetching' => (bool) ( $result['fetching'] ?? false ) ], 200 );
	}

	public function feed_status( WP_REST_Request $request ): WP_REST_Response {
		$readiness = Store::feed_readiness();
		$local     = [
			'url'             => Feed_Endpoint::url(),
			'wooActive'       => Store::is_woocommerce_active(),
			'ready'           => $readiness['ready'],
			'blockedReason'   => $readiness['reason'],
			'warnings'        => $readiness['warnings'] ?? [],
			'accountName'     => (string) get_option( self::OPTION_ACCOUNT_NAME, '' ),
			'accountLabel'    => (string) get_option( self::OPTION_ACCOUNT_LABEL, '' ),
			'claimedAt'       => (string) get_option( self::OPTION_CLAIMED_AT, '' ),
			'registeredAt'    => (string) get_option( self::OPTION_REGISTERED_AT, '' ),
			'contentLanguage' => Store::content_language(),
			'feedLabel'       => Store::feed_label(),
			'timeZone'        => Store::time_zone(),
		];

		try {
			$result = ( new Client() )->get( '/gmc/feed', [], Client::SLOW_TIMEOUT );
		} catch ( Consent_Required_Exception $e ) {
			return $this->error_response( $e->getMessage(), 403 );
		} catch ( Backend_Exception $e ) {
			// Same reasoning as the Search Console status read: a failed poll
			// must not blank the card.
			$this->log_exception( 'gmc_feed_status', $e );
			return new WP_REST_Response( [ 'local' => $local, 'remote' => null, 'error' => $e->getMessage() ], 200 );
		}

		return new WP_REST_Response( [ 'local' => $local, 'remote' => $result ], 200 );
	}
}
