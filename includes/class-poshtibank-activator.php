<?php
/**
 * Fired during plugin activation.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Activator
 *
 * Handles tasks that need to run once when the plugin is activated:
 * - Table creation via dbDelta
 * - Initializing default options
 * - Scheduling background cron tasks
 */
class Poshtibank_Activator {

	/**
	 * Run activation logic.
	 */
	public static function activate() {
		// 1. Ensure required classes are loaded.
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-db.php';
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-schema.php';
		require_once POSHTIBANK_PATH . 'includes/cron/class-poshtibank-cron.php';
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-settings.php';

		// 2. Create custom database tables.
		Poshtibank_Schema::create_tables();

		// 3. Initialize default settings.
		self::initialize_options();

		// 4. Schedule cron background tasks.
		Poshtibank_Cron::schedule_events();

		// 5. Store current version.
		update_option( 'poshtibank_version', POSHTIBANK_VERSION );
	}

	/**
	 * Seed initial default options if they do not exist.
	 */
	private static function initialize_options() {
		$existing = get_option( Poshtibank_Settings::OPTION_KEY, false );

		if ( false === $existing ) {
			add_option( Poshtibank_Settings::OPTION_KEY, Poshtibank_Settings::get_defaults() );
		}
	}
}
