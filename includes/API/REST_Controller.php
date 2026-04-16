<?php

namespace Adbot\API;

use WP_REST_Controller;
use WP_REST_Request;

abstract class REST_Controller extends WP_REST_Controller {

	protected $namespace = 'adbot/v1';

	protected function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function permission_callback(): bool {
		return $this->check_admin_permission();
	}

	protected function error_response( string $message, int $status = 400 ): \WP_REST_Response {
		return new \WP_REST_Response( [ 'error' => $message ], $status );
	}
}
