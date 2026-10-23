<?php
/**
 * Cron schedule and automated background tasks.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Cron
 *
 * Handles automated maintenance tasks such as purging expired chats.
 */
class Poshtibank_Cron {

	/**
	 * Hook name for the daily cleanup task.
	 */
	const CLEANUP_HOOK = 'poshtibank_cleanup_old_chats';

	/**
	 * Initialize the cron hooks.
	 */
	public function init() {
		add_action( self::CLEANUP_HOOK, array( $this, 'execute_cleanup' ) );
	}

	/**
	 * Run the cleanup task to purge old chat sessions and messages.
	 *
	 * @return array
	 */
	public function execute_cleanup() {
		$settings       = Poshtibank_Settings::get_settings();
		$retention_days = isset( $settings['general']['retention_days'] ) ? absint( $settings['general']['retention_days'] ) : 30;

		if ( $retention_days <= 0 ) {
			return array(
				'deleted_sessions' => 0,
				'deleted_messages' => 0,
			);
		}

		return Poshtibank_DB::cleanup_old_records( $retention_days );
	}

	/**
	 * Schedule the daily cleanup event if not already scheduled.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule the cleanup event on plugin deactivation.
	 */
	public static function clear_events() {
		$timestamp = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
		}
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}
}
