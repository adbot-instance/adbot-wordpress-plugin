<?php
/**
 * Plugin Name: Adbot
 * Plugin URI:  https://adbot.co.za
 * Description: Connect your Google marketing stack (GTM, GA4, Ads, Merchant Center, Business Profile) in one click. Automatically injects your GTM container and audits your tracking setup.
 * Version:     1.0.0
 * Author:      Adbot
 * Author URI:  https://adbot.co.za
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adbot
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
