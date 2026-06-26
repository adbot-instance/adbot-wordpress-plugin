<?php

namespace Adbot\Tracking;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snippet_Injector {

	private bool $body_snippet_injected = false;

	public function __construct() {
		add_action( 'wp_head', [ $this, 'inject_head_snippet' ], 1 );
		add_action( 'wp_body_open', [ $this, 'inject_body_snippet' ], 1 );
		// Fallback for themes that don't call wp_body_open().
		add_action( 'wp_footer', [ $this, 'inject_body_snippet_fallback' ], 1 );
	}

	public function inject_head_snippet(): void {
		$container_id = $this->get_active_container_id();
		if ( ! $container_id || $this->should_exclude_current_user() ) {
			return;
		}

		// Built from the same source the admin UI displays, so what we show the
		// user is byte-for-byte what is injected. The dynamic id is escaped inside
		// head_snippet(); the rest is a static, trusted template.
		echo self::head_snippet( $container_id ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function inject_body_snippet(): void {
		$container_id = $this->get_active_container_id();
		if ( ! $container_id || $this->should_exclude_current_user() ) {
			return;
		}

		echo self::body_snippet( $container_id ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		$this->body_snippet_injected = true;
	}

	/**
	 * The exact `<head>` GTM loader snippet Adbot injects for a container.
	 * Single source of truth shared by the injector and the REST/admin UI so the
	 * code shown to the user always matches what is on the page.
	 */
	public static function head_snippet( string $container_id ): string {
		return "<!-- Google Tag Manager (Adbot) -->\n"
			. '<script id="adbot-gtm-loader" data-adbot="gtm">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({\'gtm.start\':' . "\n"
			. "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n"
			. "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n"
			. "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n"
			. "})(window,document,'script','dataLayer','" . esc_js( $container_id ) . "');</script>\n"
			. '<!-- End Google Tag Manager (Adbot) -->';
	}

	/**
	 * The exact `<body>` (noscript) GTM snippet Adbot injects for a container.
	 */
	public static function body_snippet( string $container_id ): string {
		return "<!-- Google Tag Manager (noscript) (Adbot) -->\n"
			. '<noscript data-adbot="gtm"><iframe id="adbot-gtm-noscript" src="https://www.googletagmanager.com/ns.html?id=' . esc_attr( $container_id ) . '"' . "\n"
			. 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n"
			. '<!-- End Google Tag Manager (noscript) (Adbot) -->';
	}

	public function inject_body_snippet_fallback(): void {
		if ( $this->body_snippet_injected ) {
			return;
		}

		$this->inject_body_snippet();
	}

	private function get_active_container_id(): ?string {
		if ( ! get_option( 'adbot_snippet_active' ) ) {
			return null;
		}

		// A matching snippet from the theme / another plugin is already on the
		// site — never add a second copy (it would double-fire every tag).
		if ( get_option( 'adbot_snippet_external_detected' ) ) {
			return null;
		}

		$container_id = get_option( 'adbot_snippet_container_id' );
		if ( ! $container_id || ! preg_match( '/^GTM-[A-Z0-9]+$/', $container_id ) ) {
			return null;
		}

		return $container_id;
	}

	private function should_exclude_current_user(): bool {
		$settings = get_option( 'adbot_settings', [] );

		if ( ! empty( $settings['exclude_admins'] ) && current_user_can( 'manage_options' ) ) {
			return true;
		}

		return false;
	}
}
