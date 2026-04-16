<?php

namespace Adbot\Tracking;

class Snippet_Injector {

	public function __construct() {
		add_action( 'wp_head', [ $this, 'inject_head_snippet' ], 1 );
		add_action( 'wp_body_open', [ $this, 'inject_body_snippet' ], 1 );
	}

	public function inject_head_snippet(): void {
		$container_id = $this->get_active_container_id();
		if ( ! $container_id ) {
			return;
		}

		if ( $this->should_exclude_current_user() ) {
			return;
		}

		$container_id = esc_js( $container_id );

		echo "<!-- Google Tag Manager (Adbot) -->\n";
		echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
		echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
		echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
		echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
		echo "})(window,document,'script','dataLayer','" . $container_id . "');</script>\n";
		echo "<!-- End Google Tag Manager (Adbot) -->\n";
	}

	public function inject_body_snippet(): void {
		$container_id = $this->get_active_container_id();
		if ( ! $container_id ) {
			return;
		}

		if ( $this->should_exclude_current_user() ) {
			return;
		}

		$container_id = esc_attr( $container_id );

		echo "<!-- Google Tag Manager (noscript) (Adbot) -->\n";
		echo '<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=' . $container_id . '"' . "\n";
		echo 'height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>' . "\n";
		echo "<!-- End Google Tag Manager (noscript) (Adbot) -->\n";
	}

	private function get_active_container_id(): ?string {
		if ( ! get_option( 'adbot_snippet_active' ) ) {
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
