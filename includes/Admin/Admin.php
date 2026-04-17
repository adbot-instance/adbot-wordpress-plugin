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

		wp_enqueue_style(
			'adbot-admin',
			ADBOT_PLUGIN_URL . 'build/index.css',
			[ 'wp-components' ],
			$asset['version']
		);

		wp_localize_script( 'adbot-admin', 'adbotAdmin', [
			'restUrl'          => esc_url_raw( rest_url( 'adbot/v1/' ) ),
			'restRoot'         => trailingslashit( esc_url_raw( rest_url() ) ),
			'nonce'            => wp_create_nonce( 'wp_rest' ),
			'pluginUrl'        => ADBOT_PLUGIN_URL,
			'version'          => ADBOT_VERSION,
			'siteUrl'          => get_site_url(),
			'siteName'         => get_bloginfo( 'name' ),
			'paystackPublicKey' => \Adbot\Payments\Paystack::public_key(),
			'fixPrice'         => \Adbot\Payments\Paystack::fix_price_major(),
			'fixPriceSubunits' => \Adbot\Payments\Paystack::fix_price_subunits(),
			'currency'         => \Adbot\Payments\Paystack::currency(),
		] );
	}
}
