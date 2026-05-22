<?php
/**
 * Plugin Name: Adbot Tracking Platform
 * Description: Connect your Google marketing stack in one click. Inject a Google Tag Manager container and audit your tracking setup.
 * Version:     1.0.0
 * Author:      Adbot
 * Author URI:  https://adbot.co.za
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adbot-tracking-platform
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ADBOT_VERSION', '1.0.0' );
define( 'ADBOT_PLUGIN_FILE', __FILE__ );
define( 'ADBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ADBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ADBOT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Adbot requires no secrets or user configuration. All Google / Supabase /
 * Paystack traffic is proxied through the Adbot Tracking backend on Vercel.
 *
 * Default API root matches Backend\Client::DEFAULT_BASE (currently the
 * `adbot-tracking-platform.vercel.app` deployment). Planned production hostname:
 * `tracking.adbot.co.za` — update Client::DEFAULT_BASE when DNS is switched.
 *
 * Optional overrides in wp-config.php:
 *   define( 'ADBOT_API_BASE', 'https://preview-branch.vercel.app/api/wp' ); // staging / alternate backend
 *   define( 'ADBOT_DEBUG', true );
 */

// Autoloader.
if ( file_exists( ADBOT_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once ADBOT_PLUGIN_DIR . 'vendor/autoload.php';
}

// Activation / Deactivation.
register_activation_hook( __FILE__, [ 'Adbot\\Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'Adbot\\Deactivator', 'deactivate' ] );

// Boot.
add_action( 'plugins_loaded', function () {
	Adbot\Adbot::instance();
} );
