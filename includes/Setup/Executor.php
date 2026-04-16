<?php

namespace Adbot\Setup;

use Adbot\Google\TagManager;

/**
 * Execute a setup plan — create triggers and tags with reconciliation.
 * Ported from Adbot-Tracking-Prototype/lib/setup/executor.ts
 */
class Executor {

	private TagManager $tagmanager;

	public function __construct( TagManager $tagmanager ) {
		$this->tagmanager = $tagmanager;
	}

	public function execute( string $workspace_path, array $operations ): array {
		$results     = [];
		$created_ids = [];

		// Get existing tags and triggers for reconciliation.
		$existing_tags     = $this->tagmanager->list_tags( $workspace_path );
		$existing_triggers = $this->tagmanager->list_triggers( $workspace_path );

		// Sort: triggers first, then tags.
		usort( $operations, function ( $a, $b ) {
			if ( 'create_trigger' === $a['type'] && 'create_tag' === $b['type'] ) {
				return -1;
			}
			if ( 'create_tag' === $a['type'] && 'create_trigger' === $b['type'] ) {
				return 1;
			}
			return 0;
		} );

		foreach ( $operations as $op ) {
			// Check dependencies.
			$deps_ok = true;
			foreach ( $op['dependsOn'] ?? [] as $dep ) {
				if ( ! isset( $created_ids[ $dep ] ) ) {
					$deps_ok = false;
					$results[] = [
						'operationId' => $op['id'],
						'name'        => $op['name'],
						'success'     => false,
						'error'       => "Dependency {$dep} was not created successfully",
					];
					break;
				}
			}

			if ( ! $deps_ok ) {
				continue;
			}

			try {
				if ( 'create_trigger' === $op['type'] ) {
					$config    = $op['config'];
					$reconcile = Reconcile::match_existing_trigger( $existing_triggers, $config );

					if ( 'skip' === $reconcile['action'] ) {
						$created_ids[ $op['id'] ] = $reconcile['existingId'] ?? '';
						$results[] = [
							'operationId' => $op['id'],
							'name'        => $op['name'],
							'success'     => true,
							'createdId'   => $reconcile['existingId'],
							'action'      => 'skipped',
						];
						continue;
					}

					$trigger = $this->tagmanager->create_trigger( $workspace_path, $config );
					$created_ids[ $op['id'] ] = $trigger['triggerId'] ?? '';
					$results[] = [
						'operationId' => $op['id'],
						'name'        => $op['name'],
						'success'     => true,
						'createdId'   => $trigger['triggerId'] ?? null,
						'action'      => 'created',
					];
				} elseif ( 'create_tag' === $op['type'] ) {
					$config = $op['config'];

					// Resolve trigger ID placeholders.
					if ( ! empty( $config['firingTriggerId'] ) ) {
						$config['firingTriggerId'] = array_map( function ( $id ) use ( $op, $created_ids ) {
							if ( 'PLACEHOLDER' === $id ) {
								foreach ( $op['dependsOn'] ?? [] as $dep ) {
									if ( isset( $created_ids[ $dep ] ) ) {
										return $created_ids[ $dep ];
									}
								}
							}
							return $id;
						}, $config['firingTriggerId'] );
					}

					// Google Tag (gaawc) without triggers — attach All Pages.
					if ( 'gaawc' === ( $config['type'] ?? '' ) && empty( $config['firingTriggerId'] ) ) {
						foreach ( $op['dependsOn'] ?? [] as $dep ) {
							if ( str_contains( $dep, 'all_pages' ) && isset( $created_ids[ $dep ] ) ) {
								$config['firingTriggerId'] = [ $created_ids[ $dep ] ];
								break;
							}
						}
					}

					$reconcile = Reconcile::match_existing_tag( $existing_tags, $config, '' );

					if ( 'skip' === $reconcile['action'] ) {
						$created_ids[ $op['id'] ] = $reconcile['existingId'] ?? '';
						$results[] = [
							'operationId' => $op['id'],
							'name'        => $op['name'],
							'success'     => true,
							'createdId'   => $reconcile['existingId'],
							'action'      => 'skipped',
						];
						continue;
					}

					$tag = $this->tagmanager->create_tag( $workspace_path, $config );
					$created_ids[ $op['id'] ] = $tag['tagId'] ?? '';
					$results[] = [
						'operationId' => $op['id'],
						'name'        => $op['name'],
						'success'     => true,
						'createdId'   => $tag['tagId'] ?? null,
						'action'      => 'created',
					];
				}
			} catch ( \Exception $e ) {
				$results[] = [
					'operationId' => $op['id'],
					'name'        => $op['name'],
					'success'     => false,
					'error'       => $e->getMessage(),
				];
			}
		}

		return $results;
	}
}
