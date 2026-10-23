<?php
/**
 * Define the internationalization functionality.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_I18n
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 */
class Poshtibank_I18n {

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'poshtibank',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);
	}
}
