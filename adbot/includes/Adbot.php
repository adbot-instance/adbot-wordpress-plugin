<?php

namespace Adbot;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Adbot\Admin\Admin;
use Adbot\API\Auth_Controller;
use Adbot\API\Status_Controller;
use Adbot\API\Containers_Controller;
use Adbot\API\Audit_Controller;
use Adbot\API\Setup_Controller;
use Adbot\API\Snippet_Controller;
use Adbot\API\Settings_Controller;
use Adbot\API\Onboarding_Controller;
use Adbot\API\Payments_Controller;
use Adbot\Tracking\Snippet_Injector;
use Adbot\Backend\Site_Registration;

class Adbot {

	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->load_admin();
		$this->load_rest_api();
		$this->load_tracking();
		$this->load_site_registration();

		do_action( 'adbot_loaded' );
	}

	private function load_site_registration(): void {
		( new Site_Registration() )->hook();
	}

	private function load_admin(): void {
		if ( is_admin() ) {
			new Admin();
		}
	}

	private function load_rest_api(): void {
		add_action( 'rest_api_init', function () {
			( new Auth_Controller() )->register_routes();
			( new Status_Controller() )->register_routes();
			( new Containers_Controller() )->register_routes();
			( new Audit_Controller() )->register_routes();
			( new Setup_Controller() )->register_routes();
			( new Snippet_Controller() )->register_routes();
			( new Settings_Controller() )->register_routes();
			( new Onboarding_Controller() )->register_routes();
			( new Payments_Controller() )->register_routes();

			register_rest_route( 'adbot/v1', '/verify', [
				'methods'             => 'GET',
				'callback'            => [ Site_Registration::class, 'handle_verify_request' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'challenge' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			] );
		} );
	}

	private function load_tracking(): void {
		new Snippet_Injector();
	}
}
