<?php

namespace Adbot;

use Adbot\Admin\Admin;
use Adbot\API\Auth_Controller;
use Adbot\API\Status_Controller;
use Adbot\API\Containers_Controller;
use Adbot\API\Audit_Controller;
use Adbot\API\Setup_Controller;
use Adbot\API\Snippet_Controller;
use Adbot\API\Settings_Controller;
use Adbot\Tracking\Snippet_Injector;

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

		do_action( 'adbot_loaded' );
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
		} );
	}

	private function load_tracking(): void {
		new Snippet_Injector();
	}
}
