<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package Poshtibank
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load DB helper to know table names.
require_once plugin_dir_path( __FILE__ ) . 'includes/db/class-poshtibank-db.php';

$settings = get_option( 'poshtibank_settings', array() );
$delete_data = ! empty( $settings['advanced']['delete_data_on_uninstall'] );

if ( $delete_data ) {
	global $wpdb;

	$sessions_table = Poshtibank_DB::get_sessions_table();
	$messages_table = Poshtibank_DB::get_messages_table();

	// Drop tables.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$messages_table}" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( "DROP TABLE IF EXISTS {$sessions_table}" );

	// Delete options.
	delete_option( 'poshtibank_settings' );
	delete_option( 'poshtibank_db_version' );
	delete_option( 'poshtibank_version' );

	// Clear scheduled cron.
	wp_clear_scheduled_hook( 'poshtibank_cleanup_old_chats' );
}
