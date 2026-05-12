<?php

namespace Adbot\Backend;

/**
 * Thrown by Adbot\Backend\Client when the backend returns an error or when
 * the plugin cannot authenticate against it.
 *
 * `$code` is the snake_case error code from the backend (or a synthetic one
 * like `not_registered` / `network_error`). `$status` is the HTTP status to
 * echo back in the REST response.
 */
class Backend_Exception extends \RuntimeException {
	private string $error_code;
	private int $status;

	public function __construct( string $error_code, string $message, int $status = 500 ) {
		parent::__construct( $message );
		$this->error_code = $error_code;
		$this->status     = $status;
	}

	public function error_code(): string {
		return $this->error_code;
	}

	public function status(): int {
		return $this->status;
	}
}
