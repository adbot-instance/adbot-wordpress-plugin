<?php

namespace Adbot\Google;

use Google\Client as Google_Client;
use Google\Service\GoogleAnalyticsAdmin as Analytics_Admin;

class Analytics {

	private Analytics_Admin $service;

	public function __construct( Google_Client $client ) {
		$this->service = new Analytics_Admin( $client );
	}

	public function get_service(): Analytics_Admin {
		return $this->service;
	}

	/**
	 * List all GA4 accounts accessible to the user.
	 */
	public function list_accounts(): array {
		$accounts = $this->service->accounts->listAccounts();
		$result   = [];

		foreach ( $accounts->getAccounts() ?? [] as $account ) {
			$result[] = [
				'name'        => $account->getName(),
				'displayName' => $account->getDisplayName(),
			];
		}

		return $result;
	}

	/**
	 * List GA4 properties for an account.
	 */
	public function list_properties( string $account_name ): array {
		$properties = $this->service->properties->listProperties( [
			'filter' => "parent:{$account_name}",
		] );
		$result = [];

		foreach ( $properties->getProperties() ?? [] as $property ) {
			$result[] = [
				'name'        => $property->getName(),
				'displayName' => $property->getDisplayName(),
				'propertyType' => $property->getPropertyType(),
			];
		}

		return $result;
	}

	/**
	 * List data streams for a property.
	 */
	public function list_data_streams( string $property_name ): array {
		$streams = $this->service->properties_dataStreams
			->listPropertiesDataStreams( $property_name );
		$result  = [];

		foreach ( $streams->getDataStreams() ?? [] as $stream ) {
			$web_stream = $stream->getWebStreamData();
			$result[]   = [
				'name'            => $stream->getName(),
				'displayName'     => $stream->getDisplayName(),
				'type'            => $stream->getType(),
				'measurementId'   => $web_stream ? $web_stream->getMeasurementId() : null,
				'defaultUri'      => $web_stream ? $web_stream->getDefaultUri() : null,
			];
		}

		return $result;
	}

	/**
	 * Search all properties and streams to find a GA4 property matching a URL.
	 */
	public function discover_for_url( string $url ): ?array {
		$parsed_host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $parsed_host ) {
			return null;
		}

		// Normalise: strip www.
		$normalized = preg_replace( '/^www\./', '', $parsed_host );

		$accounts = $this->list_accounts();

		foreach ( $accounts as $account ) {
			$properties = $this->list_properties( $account['name'] );

			foreach ( $properties as $property ) {
				$streams = $this->list_data_streams( $property['name'] );

				foreach ( $streams as $stream ) {
					if ( 'WEB_DATA_STREAM' !== $stream['type'] || empty( $stream['defaultUri'] ) ) {
						continue;
					}

					$stream_host = wp_parse_url( $stream['defaultUri'], PHP_URL_HOST );
					$stream_host = preg_replace( '/^www\./', '', $stream_host ?? '' );

					if ( $stream_host === $normalized && ! empty( $stream['measurementId'] ) ) {
						return [
							'propertyName' => $property['name'],
							'displayName'  => $property['displayName'],
							'measurementId' => $stream['measurementId'],
							'streamName'   => $stream['name'],
						];
					}
				}
			}
		}

		return null;
	}
}
