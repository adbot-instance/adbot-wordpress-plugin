<?php

namespace Adbot\Channels;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Adbot\Consent;
use Adbot\Backend\Client;
use Adbot\Backend\Token_Store;
use Adbot\Backend\Backend_Exception;
use Adbot\Consent_Required_Exception;
use Adbot\API\GSC_Controller;
use Adbot\API\GMC_Controller;

/**
 * Daily refresh of the channel statuses Google owns.
 *
 * The plugin's first scheduled task. Everything else here is request-driven,
 * but a sitemap's fetch result and a product feed's item count are only known
 * to Google and only change on Google's schedule — polling them on page load
 * means the Channels screen waits on two cold backend round-trips every time.
 *
 * The cached snapshot is what the screen paints first; a live read then
 * refreshes it. Nothing is polled for a site that has not connected a channel,
 * so this adds no traffic for the majority of installs.
 */
class Sync {

	public const HOOK   = 'adbot_daily_channel_sync';
	public const OPTION = 'adbot_channel_status';

	public function hook(): void {
		add_action( self::HOOK, [ $this, 'run' ] );
	}

	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			// Offset the first run so a mass activation across a host does not
			// stampede the backend at the top of the hour.
			wp_schedule_event( time() + wp_rand( 300, 3600 ), 'daily', self::HOOK );
		}
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Refresh whichever channels are actually in use.
	 *
	 * Failures are recorded, not thrown: a cron run that dies leaves the
	 * snapshot stale with no indication why, and an unreachable backend must
	 * never look like a broken feed.
	 */
	public function run(): void {
		if ( ! Consent::has_consent() || '' === Token_Store::get_site_token() ) {
			return;
		}

		$status = [ 'checkedAt' => gmdate( 'c' ) ];
		$client = new Client();

		if ( '' !== (string) get_option( GSC_Controller::OPTION_SITE_URL, '' ) ) {
			$status['gsc'] = $this->read( $client, '/gsc/sitemap' );
		}

		if ( '' !== (string) get_option( GMC_Controller::OPTION_REGISTERED_AT, '' ) ) {
			$status['gmc'] = $this->read( $client, '/gmc/feed' );
		}

		if ( count( $status ) > 1 ) {
			update_option( self::OPTION, $status, false );
		}
	}

	/**
	 * @return array{ok:bool, data?:array, error?:string}
	 */
	private function read( Client $client, string $path ): array {
		try {
			return [ 'ok' => true, 'data' => $client->get( $path, [], Client::SLOW_TIMEOUT ) ];
		} catch ( Consent_Required_Exception $e ) {
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		} catch ( Backend_Exception $e ) {
			return [ 'ok' => false, 'error' => $e->getMessage() ];
		}
	}

	/** Last cached snapshot, or an empty array when nothing has run yet. */
	public static function snapshot(): array {
		$stored = get_option( self::OPTION, [] );
		return is_array( $stored ) ? $stored : [];
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}
}
