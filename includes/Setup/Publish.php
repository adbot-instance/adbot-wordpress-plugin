<?php

namespace Adbot\Setup;

use Adbot\Google\TagManager;

/**
 * Create a version and publish it to live.
 * Ported from Adbot-Tracking-Prototype/lib/setup/publish.ts
 */
class Publish {

	private TagManager $tagmanager;

	public function __construct( TagManager $tagmanager ) {
		$this->tagmanager = $tagmanager;
	}

	public function publish( string $container_path, string $workspace_path ): array {
		$version_name = 'Adbot Setup - ' . gmdate( 'Y-m-d H:i:s' );

		// Create a version from the workspace.
		$version = $this->tagmanager->create_version( $workspace_path, $version_name );

		if ( empty( $version['path'] ) ) {
			throw new \RuntimeException( 'Failed to create container version.' );
		}

		// Publish the version.
		$published = $this->tagmanager->publish_version( $version['path'] );

		return [
			'versionId'   => $published['versionId'] ?? $version['versionId'],
			'versionName' => $published['name'] ?? $version_name,
			'published'   => true,
		];
	}
}
