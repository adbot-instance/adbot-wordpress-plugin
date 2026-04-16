<?php

namespace Adbot\Assessment;

use Adbot\Google\TagManager;

/**
 * Read and classify all tags, triggers, and variables from a GTM workspace.
 * Ported from Adbot-Tracking-Prototype/lib/assessment/container-audit.ts
 */
class Container_Audit {

	private TagManager $tagmanager;

	public function __construct( TagManager $tagmanager ) {
		$this->tagmanager = $tagmanager;
	}

	public function audit( string $workspace_path ): array {
		$raw_tags      = $this->tagmanager->list_tags( $workspace_path );
		$raw_triggers  = $this->tagmanager->list_triggers( $workspace_path );
		$raw_variables = $this->tagmanager->list_variables( $workspace_path );

		// Build constant variable lookup for resolving {{Variable}} references.
		$constants_by_name = [];
		foreach ( $raw_variables as $var ) {
			if ( 'c' === ( $var['type'] ?? '' ) ) {
				$value = $this->get_param_value( $var['parameter'] ?? [], 'value' );
				if ( $var['name'] && $value ) {
					$constants_by_name[ $var['name'] ] = $value;
				}
			}
		}

		// Classify variables.
		$variables = array_map( function ( $var ) {
			return [
				'variableId'    => $var['variableId'] ?? '',
				'name'          => $var['name'] ?? '',
				'type'          => $var['type'] ?? 'unknown',
				'categoryLabel' => GTM_Classification::classify_variable( $var['type'] ?? 'unknown' ),
			];
		}, $raw_variables );

		// Classify triggers.
		$triggers = array_map( function ( $trigger ) {
			$classification = GTM_Classification::classify_trigger( $trigger['type'] ?? 'unknown' );
			$filter_count   = count( $trigger['customEventFilter'] ?? [] )
				+ count( $trigger['filter'] ?? [] )
				+ count( $trigger['autoEventFilter'] ?? [] );

			return [
				'triggerId'    => $trigger['triggerId'] ?? '',
				'name'         => $trigger['name'] ?? '',
				'type'         => $trigger['type'] ?? 'unknown',
				'category'     => $classification['category'],
				'categoryLabel' => $classification['label'],
				'filterCount'  => $filter_count,
				'path'         => $trigger['path'] ?? '',
			];
		}, $raw_triggers );

		// Classify tags.
		$tags = array_map( function ( $tag ) use ( $constants_by_name ) {
			$classification = GTM_Classification::classify_tag( $tag['type'] ?? 'unknown' );
			$params         = $tag['parameter'] ?? [];

			$measurement_id = null;
			if ( in_array( $tag['type'] ?? '', [ 'gaawc', 'gaawe', 'googtag', 'gtag' ], true ) ) {
				$measurement_id = $this->extract_measurement_id( $params, $constants_by_name );
			}

			$event_name = null;
			if ( 'gaawe' === ( $tag['type'] ?? '' ) ) {
				$raw_event  = $this->get_param_value( $params, 'eventName' );
				$event_name = $this->resolve_value( $raw_event, $constants_by_name );
			}

			return [
				'tagId'           => $tag['tagId'] ?? '',
				'name'            => $tag['name'] ?? '',
				'type'            => $tag['type'] ?? 'unknown',
				'category'        => $classification['category'],
				'categoryLabel'   => $classification['label'],
				'firingTriggerId' => $tag['firingTriggerId'] ?? [],
				'paused'          => $tag['paused'] ?? false,
				'measurementId'   => $measurement_id,
				'eventName'       => $event_name,
				'path'            => $tag['path'] ?? '',
			];
		}, $raw_tags );

		return [
			'tags'      => $tags,
			'triggers'  => $triggers,
			'variables' => $variables,
		];
	}

	private function get_param_value( array $params, string $key ): ?string {
		foreach ( $params as $param ) {
			if ( ( $param['key'] ?? '' ) === $key ) {
				return $param['value'] ?? null;
			}
		}
		return null;
	}

	private function resolve_value( ?string $value, array $constants_by_name ): ?string {
		if ( ! $value ) {
			return null;
		}

		if ( preg_match( '/^\{\{(.+?)\}\}$/', trim( $value ), $matches ) ) {
			$ref_name = trim( $matches[1] );
			return $constants_by_name[ $ref_name ] ?? $value;
		}

		return $value;
	}

	private function extract_measurement_id( array $params, array $constants_by_name ): ?string {
		$direct = $this->get_param_value( $params, 'measurementId' )
			?? $this->get_param_value( $params, 'measurementIdOverride' );

		$resolved = $this->resolve_value( $direct, $constants_by_name );
		if ( $resolved ) {
			return $resolved;
		}

		// Fallback: find any GA4-looking ID in params.
		foreach ( $params as $param ) {
			$val = $this->resolve_value( $param['value'] ?? null, $constants_by_name );
			if ( $val && preg_match( '/^G-[A-Z0-9]+$/i', $val ) ) {
				return $val;
			}
		}

		return null;
	}
}
