<?php

namespace Adbot\API;

use WP_REST_Controller;
use WP_REST_Request;

abstract class REST_Controller extends WP_REST_Controller {

	protected $namespace = 'adbot/v1';

	protected function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Default permission callback for admin-only routes.
	 *
	 * In addition to capability checks, require a REST cookie nonce for any
	 * state-changing request method. Public endpoints (e.g. webhooks) must
	 * implement their own permission_callback verification.
	 */
	public function permission_callback( WP_REST_Request $request ): bool {
		if ( ! $this->check_admin_permission() ) {
			return false;
		}

		$method = strtoupper( $request->get_method() );
		if ( in_array( $method, [ 'GET', 'HEAD', 'OPTIONS' ], true ) ) {
			return true;
		}

		$nonce = (string) $request->get_header( 'X-WP-Nonce' );
		if ( '' === $nonce ) {
			return false;
		}

		return (bool) wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Log an exception to the WordPress debug log without leaking details
	 * through the REST response.
	 */
	protected function log_exception( string $context, \Throwable $e ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[adbot][%s] %s', $context, $e->getMessage() ) );
		}
	}

	/**
	 * Convert a backend exception into a user-facing REST error response.
	 */
	protected function backend_error( \Adbot\Backend\Backend_Exception $e ): \WP_REST_Response {
		return new \WP_REST_Response(
			[
				'code'    => $e->error_code(),
				'message' => $e->getMessage(),
				'data'    => [ 'status' => $e->status() ],
			],
			$e->status()
		);
	}

	protected function error_response( string $message, int $status = 400 ): \WP_REST_Response {
		// Return in WP_Error format so @wordpress/api-fetch surfaces `message` on the thrown error.
		return new \WP_REST_Response(
			[
				'code'    => 'adbot_error',
				'message' => $message,
				'error'   => $message,
				'data'    => [ 'status' => $status ],
			],
			$status
		);
	}
}
