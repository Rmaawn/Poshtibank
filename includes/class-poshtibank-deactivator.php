<?php
/**
 * Fired during plugin deactivation.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Deactivator
 *
 * Handles tasks that need to run when the plugin is deactivated:
 * - Un-scheduling cron hooks
 * - Flushing temporary transients
 */
class Poshtibank_Deactivator {

	/**
	 * Run deactivation logic.
	 */
	public static function deactivate() {
		require_once POSHTIBANK_PATH . 'includes/cron/class-poshtibank-cron.php';

		// Clear scheduled cron tasks.
		Poshtibank_Cron::clear_events();

		// Clean transients if any.
		delete_transient( 'poshtibank_active_sessions_count' );
	}
}
