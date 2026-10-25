<?php
/**
 * Plugin Name: پشتیبانک — Poshtibank
 * Plugin URI:  https://github.com/Rmaawn/Poshtibank
 * Description: همه پشتیبانی سایت، در یک افزونه. سیستم چت آنلاین حرفه‌ای و رایگان وردپرس متصل به تلگرام و بله، بدون سرور واسط.
 * Version:     1.0.3
 * Author:      آرمان محبعلی
 * Author URI:  https://armanmohebali.ir/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: poshtibank
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Poshtibank
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Currently active plugin version.
 */
define( 'POSHTIBANK_VERSION', '1.0.3' );

/**
 * Database schema version.
 */
define( 'POSHTIBANK_DB_VERSION', '1.0.0' );

/**
 * Plugin root file path.
 */
define( 'POSHTIBANK_FILE', __FILE__ );

/**
 * Plugin root directory path with trailing slash.
 */
define( 'POSHTIBANK_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Plugin root directory URL with trailing slash.
 */
define( 'POSHTIBANK_URL', plugin_dir_url( __FILE__ ) );

/**
 * Plugin basename.
 */
define( 'POSHTIBANK_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Code that runs during plugin activation.
 */
function activate_poshtibank() {
	require_once POSHTIBANK_PATH . 'includes/class-poshtibank-activator.php';
	Poshtibank_Activator::activate();
}

/**
 * Code that runs during plugin deactivation.
 */
function deactivate_poshtibank() {
	require_once POSHTIBANK_PATH . 'includes/class-poshtibank-deactivator.php';
	Poshtibank_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_poshtibank' );
register_deactivation_hook( __FILE__, 'deactivate_poshtibank' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once POSHTIBANK_PATH . 'includes/class-poshtibank.php';

/**
 * Begins execution of the plugin.
 */
function run_poshtibank() {
	$plugin = Poshtibank::get_instance();
	$plugin->run();
}
run_poshtibank();
