<?php
/**
 * Database schema management and dbDelta runner.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Schema
 *
 * Defines and runs schema migrations using WordPress dbDelta.
 */
class Poshtibank_Schema {

	/**
	 * Create or update custom database tables.
	 *
	 * Uses dbDelta to safely create tables or apply column modifications
	 * across plugin updates.
	 *
	 * @return array dbDelta log output.
	 */
	public static function create_tables() {
		global $wpdb;

		if ( file_exists( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->get_charset_collate();

		$sessions_table = Poshtibank_DB::get_sessions_table();
		$messages_table = Poshtibank_DB::get_messages_table();

		/*
		 * Rules for dbDelta:
		 * 1. Each field on its own line.
		 * 2. PRIMARY KEY must be followed by TWO spaces, then (field).
		 * 3. Use KEY keyword instead of INDEX.
		 * 4. Do not use quotes/backticks around table names or column names.
		 */
		$sql_sessions = "CREATE TABLE {$sessions_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_name varchar(191) NOT NULL DEFAULT '',
			user_phone varchar(32) NOT NULL DEFAULT '',
			user_email varchar(191) NOT NULL DEFAULT '',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text DEFAULT NULL,
			current_page text DEFAULT NULL,
			referer text DEFAULT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			telegram_topic_id bigint(20) DEFAULT NULL,
			telegram_chat_id varchar(64) DEFAULT NULL,
			bale_chat_id varchar(64) DEFAULT NULL,
			assigned_to varchar(64) DEFAULT NULL,
			unread_admin_count int(11) NOT NULL DEFAULT 0,
			unread_user_count int(11) NOT NULL DEFAULT 0,
			metadata longtext DEFAULT NULL,
			last_activity datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY status (status),
			KEY telegram_topic_id (telegram_topic_id),
			KEY last_activity (last_activity),
			KEY ip_address (ip_address)
		) {$charset_collate};";

		$sql_messages = "CREATE TABLE {$messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id varchar(64) NOT NULL,
			session_id varchar(64) NOT NULL,
			sender varchar(20) NOT NULL DEFAULT 'user',
			sender_id varchar(64) NOT NULL DEFAULT '',
			sender_name varchar(191) NOT NULL DEFAULT '',
			type varchar(30) NOT NULL DEFAULT 'text',
			content longtext NOT NULL,
			metadata longtext DEFAULT NULL,
			is_read tinyint(1) NOT NULL DEFAULT 0,
			read_at datetime DEFAULT NULL,
			created_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY message_id (message_id),
			KEY session_id (session_id),
			KEY sender (sender),
			KEY type (type),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) {$charset_collate};";

		$result = array();
		if ( function_exists( 'dbDelta' ) ) {
			$result['sessions'] = dbDelta( $sql_sessions );
			$result['messages'] = dbDelta( $sql_messages );
		}

		// Fallback: If dbDelta failed to create any table, execute direct SQL queries.
		if ( ! self::verify_tables_exist() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( str_replace( 'CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql_sessions ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query( str_replace( 'CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $sql_messages ) );
		}

		// Only claim the schema is current once it actually is:
		if ( self::verify_tables_exist() ) {
			update_option( 'poshtibank_db_version', POSHTIBANK_DB_VERSION );
			delete_transient( 'poshtibank_schema_retry' );
		} else {
			error_log( '[Poshtibank] Table creation failed: ' . $wpdb->last_error . ' | dbDelta log: ' . wp_json_encode( $result ) );
		}

		return $result;
	}

	/**
	 * Repair the tables when the installed schema is behind or tables are missing.
	 */
	public static function maybe_upgrade() {
		if ( self::verify_tables_exist() && POSHTIBANK_DB_VERSION === (string) get_option( 'poshtibank_db_version', '' ) ) {
			return;
		}

		self::create_tables();
	}

	/**
	 * Verify that all required tables exist in the database.
	 *
	 * @return bool True if all tables exist, false otherwise.
	 */
	public static function verify_tables_exist() {
		global $wpdb;

		$sessions_table = Poshtibank_DB::get_sessions_table();
		$messages_table = Poshtibank_DB::get_messages_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_sessions = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $sessions_table ) ) === $sessions_table;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$has_messages = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $messages_table ) ) === $messages_table;

		return $has_sessions && $has_messages;
	}
}
