<?php

namespace Adbot\Admin;

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Adbot', 'adbot' ),
			__( 'Adbot', 'adbot' ),
			'manage_options',
			'adbot',
			[ $this, 'render_page' ],
			'dashicons-chart-area',
			30
		);
	}

	public function render_page(): void {
		echo '<div id="adbot-admin-root"></div>';
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_adbot' !== $hook_suffix ) {
			return;
		}

		$asset_file = ADBOT_PLUGIN_DIR . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'adbot-admin',
			ADBOT_PLUGIN_URL . 'build/index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'adbot-admin',
			ADBOT_PLUGIN_URL . 'build/index.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_localize_script( 'adbot-admin', 'adbotAdmin', [
			'restUrl'   => esc_url_raw( rest_url( 'adbot/v1/' ) ),
			'restRoot'  => trailingslashit( esc_url_raw( rest_url() ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => ADBOT_PLUGIN_URL,
			'version'   => ADBOT_VERSION,
			'siteUrl'   => get_site_url(),
			'siteName'  => get_bloginfo( 'name' ),
		] );
	}
}
