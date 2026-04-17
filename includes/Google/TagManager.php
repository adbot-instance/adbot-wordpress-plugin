<?php

namespace Adbot\Google;

use Google\Client as Google_Client;
use Google\Service\TagManager as TagManager_Service;

class TagManager {

	private TagManager_Service $service;

	public function __construct( Google_Client $client ) {
		$this->service = new TagManager_Service( $client );
	}

	public function get_service(): TagManager_Service {
		return $this->service;
	}

	/**
	 * List all GTM accounts and their containers accessible to the user.
	 */
	public function list_accounts_and_containers(): array {
		$result   = [];
		$accounts = Retry::run( fn() => $this->service->accounts->listAccounts() );

		foreach ( $accounts->getAccount() ?? [] as $account ) {
			$account_path = $account->getPath();
			$containers   = Retry::run(
				fn() => $this->service->accounts_containers->listAccountsContainers( $account_path )
			);

			$container_list = [];
			foreach ( $containers->getContainer() ?? [] as $container ) {
				$container_list[] = [
					'path'        => $container->getPath(),
					'containerId' => $container->getPublicId(),
					'name'        => $container->getName(),
					'domainName'  => $container->getDomainName() ?? [],
				];
			}

			$result[] = [
				'accountId' => $account->getAccountId(),
				'name'      => $account->getName(),
				'path'      => $account_path,
				'containers' => $container_list,
			];
		}

		return $result;
	}

	/**
	 * List workspaces for a container.
	 */
	public function list_workspaces( string $container_path ): array {
		$workspaces = $this->service->accounts_containers_workspaces
			->listAccountsContainersWorkspaces( $container_path );

		$result = [];
		foreach ( $workspaces->getWorkspace() ?? [] as $workspace ) {
			$result[] = [
				'path'        => $workspace->getPath(),
				'workspaceId' => $workspace->getWorkspaceId(),
				'name'        => $workspace->getName(),
			];
		}

		return $result;
	}

	/**
	 * List all tags in a workspace.
	 */
	public function list_tags( string $workspace_path ): array {
		$tags   = $this->service->accounts_containers_workspaces_tags
			->listAccountsContainersWorkspacesTags( $workspace_path );
		$result = [];

		foreach ( $tags->getTag() ?? [] as $tag ) {
			$result[] = [
				'tagId'              => $tag->getTagId(),
				'name'               => $tag->getName(),
				'type'               => $tag->getType(),
				'parameter'          => $this->parameters_to_array( $tag->getParameter() ?? [] ),
				'firingTriggerId'    => $tag->getFiringTriggerId() ?? [],
				'blockingTriggerId'  => $tag->getBlockingTriggerId() ?? [],
				'paused'             => $tag->getPaused() ?? false,
				'path'               => $tag->getPath(),
			];
		}

		return $result;
	}

	/**
	 * List all triggers in a workspace.
	 */
	public function list_triggers( string $workspace_path ): array {
		$triggers = $this->service->accounts_containers_workspaces_triggers
			->listAccountsContainersWorkspacesTriggers( $workspace_path );
		$result   = [];

		foreach ( $triggers->getTrigger() ?? [] as $trigger ) {
			$result[] = [
				'triggerId'    => $trigger->getTriggerId(),
				'name'         => $trigger->getName(),
				'type'         => $trigger->getType(),
				'customEventFilter' => $this->conditions_to_array( $trigger->getCustomEventFilter() ?? [] ),
				'filter'       => $this->conditions_to_array( $trigger->getFilter() ?? [] ),
				'autoEventFilter' => $this->conditions_to_array( $trigger->getAutoEventFilter() ?? [] ),
				'path'         => $trigger->getPath(),
			];
		}

		return $result;
	}

	/**
	 * List all variables in a workspace.
	 */
	public function list_variables( string $workspace_path ): array {
		$variables = $this->service->accounts_containers_workspaces_variables
			->listAccountsContainersWorkspacesVariables( $workspace_path );
		$result    = [];

		foreach ( $variables->getVariable() ?? [] as $variable ) {
			$result[] = [
				'variableId' => $variable->getVariableId(),
				'name'       => $variable->getName(),
				'type'       => $variable->getType(),
				'parameter'  => $this->parameters_to_array( $variable->getParameter() ?? [] ),
				'path'       => $variable->getPath(),
			];
		}

		return $result;
	}

	/**
	 * Create a tag in a workspace.
	 */
	public function create_tag( string $workspace_path, array $tag_config ): array {
		$created = Retry::run( function () use ( $workspace_path, $tag_config ) {
			$tag = new \Google\Service\TagManager\Tag( $tag_config );

			return $this->service->accounts_containers_workspaces_tags
				->create( $workspace_path, $tag );
		} );

		return [
			'tagId' => $created->getTagId(),
			'name'  => $created->getName(),
			'type'  => $created->getType(),
			'path'  => $created->getPath(),
		];
	}

	/**
	 * Create a trigger in a workspace.
	 */
	public function create_trigger( string $workspace_path, array $trigger_config ): array {
		$created = Retry::run( function () use ( $workspace_path, $trigger_config ) {
			$trigger = new \Google\Service\TagManager\Trigger( $trigger_config );

			return $this->service->accounts_containers_workspaces_triggers
				->create( $workspace_path, $trigger );
		} );

		return [
			'triggerId' => $created->getTriggerId(),
			'name'      => $created->getName(),
			'type'      => $created->getType(),
			'path'      => $created->getPath(),
		];
	}

	/**
	 * Create a version from the current workspace state.
	 */
	public function create_version( string $workspace_path, string $name ): array {
		$response = Retry::run( function () use ( $workspace_path, $name ) {
			$option = new \Google\Service\TagManager\CreateContainerVersionRequestVersionOptions( [
				'name' => $name,
			] );

			return $this->service->accounts_containers_workspaces
				->create_version( $workspace_path, $option );
		} );

		$version = $response->getContainerVersion();

		return [
			'versionId' => $version ? $version->getContainerVersionId() : null,
			'name'      => $version ? $version->getName() : null,
			'path'      => $version ? $version->getPath() : null,
		];
	}

	/**
	 * Publish a container version.
	 */
	public function publish_version( string $version_path ): array {
		$response = Retry::run( function () use ( $version_path ) {
			return $this->service->accounts_containers_versions
				->publish( $version_path );
		} );

		$version = $response->getContainerVersion();

		return [
			'versionId' => $version ? $version->getContainerVersionId() : null,
			'name'      => $version ? $version->getName() : null,
		];
	}

	private function parameters_to_array( $parameters ): array {
		$result = [];
		foreach ( $parameters as $param ) {
			$result[] = [
				'key'   => $param->getKey(),
				'type'  => $param->getType(),
				'value' => $param->getValue(),
				'list'  => $param->getList(),
				'map'   => $param->getMap(),
			];
		}
		return $result;
	}

	private function conditions_to_array( $conditions ): array {
		$result = [];
		foreach ( $conditions as $condition ) {
			$result[] = [
				'type'      => $condition->getType(),
				'parameter' => $this->parameters_to_array( $condition->getParameter() ?? [] ),
			];
		}
		return $result;
	}
}
