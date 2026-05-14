<?php

namespace Adbot\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_init', [ $this, 'maybe_activation_redirect' ] );
	}

	/**
	 * After activation, load the Adbot admin app once so the welcome onboarding appears first.
	 */
	public function maybe_activation_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( ! get_transient( 'adbot_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'adbot_activation_redirect' );
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=adbot' ) );
		exit;
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Adbot', 'adbot-tracking-platform' ),
			__( 'Adbot', 'adbot-tracking-platform' ),
			'manage_options',
			'adbot',
			[ $this, 'render_page' ],
			$this->menu_icon(),
			30
		);
	}

	/**
	 * Inline SVG mark for the Adbot admin menu item.
	 * Kept monochrome so WordPress can recolor it for active/hover states.
	 * Motif: a rounded "target" with a bolt — tracking + automation.
	 */
	private function menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="black">'
			. '<path d="M10 1.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm0 2a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13Z"/>'
			. '<path d="M10.8 5.4 6.9 11h2.4l-.7 3.6 3.9-5.6H10.1l.7-3.6Z"/>'
			. '</svg>';

		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
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

		wp_set_script_translations( 'adbot-admin', 'adbot-tracking-platform', ADBOT_PLUGIN_DIR . 'languages' );

		wp_enqueue_style(
			'adbot-admin',
			ADBOT_PLUGIN_URL . 'build/index.css',
			[ 'wp-components' ],
			$asset['version']
		);

		$images_url = ADBOT_PLUGIN_URL . 'assets/images/';

		wp_localize_script( 'adbot-admin', 'adbotAdmin', [
			'restUrl'   => esc_url_raw( rest_url( 'adbot/v1/' ) ),
			'restRoot'  => trailingslashit( esc_url_raw( rest_url() ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'pluginUrl' => ADBOT_PLUGIN_URL,
			'version'   => ADBOT_VERSION,
			'siteUrl'   => get_site_url(),
			'siteName'  => get_bloginfo( 'name' ),
			'brand'     => [
				'name'        => __( 'Tracking', 'adbot-tracking-platform' ),
				'tagline'     => __( 'Automated Google Analytics and Tag Manager setup', 'adbot-tracking-platform' ),
				/* translators: Logo image for screen readers. */
				'logoAlt'     => __( 'Tracking setup logo', 'adbot-tracking-platform' ),
				'logoUrl'     => esc_url_raw( $images_url . 'adbot/adbot.jpg' ),
				'setupTitle'  => __( 'Tracking setup', 'adbot-tracking-platform' ),
			],
			'images'    => [
				'adbot' => esc_url_raw( $images_url . 'adbot/adbot.jpg' ),
				'gtm'   => esc_url_raw( $images_url . 'google-products/google-tag-manager.svg' ),
				'ga4'   => esc_url_raw( $images_url . 'google-products/ga4-logo.svg' ),
				'gads'  => esc_url_raw( $images_url . 'google-products/gads-logo.svg' ),
				'gsc'   => esc_url_raw( $images_url . 'google-products/gsc-logo.svg' ),
			],
		] );
	}
}
