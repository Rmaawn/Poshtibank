<?php
/**
 * Database abstraction layer and query helper.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_DB
 *
 * Provides safe, optimized CRUD operations for sessions and messages.
 */
class Poshtibank_DB {

	/**
	 * Get the table name for sessions.
	 *
	 * @return string Full table name with WP prefix.
	 */
	public static function get_sessions_table() {
		global $wpdb;
		$default = $wpdb->prefix . 'poshtibank_sessions';
		return apply_filters( 'poshtibank_table_sessions', $default );
	}

	/**
	 * Get the table name for messages.
	 *
	 * @return string Full table name with WP prefix.
	 */
	public static function get_messages_table() {
		global $wpdb;
		$default = $wpdb->prefix . 'poshtibank_messages';
		return apply_filters( 'poshtibank_table_messages', $default );
	}

	/**
	 * Generate a standard UUID v4 string.
	 *
	 * @return string
	 */
	public static function generate_uuid() {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}

		$data = random_bytes( 16 );
		$data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 ); // set version to 0100
		$data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 ); // set bits 6-7 to 10
		return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $data ), 4 ) );
	}

	/*
	|--------------------------------------------------------------------------
	| Session Management Operations
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create a new session.
	 *
	 * @param array $args Session fields.
	 * @return string|false Generated session_id on success, false on failure.
	 */
	public static function create_session( array $args ) {
		global $wpdb;

		$session_id = ! empty( $args['session_id'] ) ? sanitize_text_field( $args['session_id'] ) : self::generate_uuid();
		$now        = current_time( 'mysql' );

		$defaults = array(
			'session_id'         => $session_id,
			'user_id'            => get_current_user_id(),
			'user_name'          => '',
			'user_phone'         => '',
			'user_email'         => '',
			'ip_address'         => self::get_client_ip(),
			'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 500 ) : '',
			'current_page'       => '',
			'referer'            => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
			'status'             => 'open',
			'telegram_topic_id'  => null,
			'telegram_chat_id'   => null,
			'bale_chat_id'       => null,
			'assigned_to'        => null,
			'unread_admin_count' => 0,
			'unread_user_count'  => 0,
			'metadata'           => null,
			'last_activity'      => $now,
			'created_at'         => $now,
			'updated_at'         => $now,
		);

		$data = wp_parse_args( $args, $defaults );

		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$table = self::get_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'session_id'         => $data['session_id'],
				'user_id'            => absint( $data['user_id'] ),
				'user_name'          => sanitize_text_field( $data['user_name'] ),
				'user_phone'         => sanitize_text_field( $data['user_phone'] ),
				'user_email'         => sanitize_email( $data['user_email'] ),
				'ip_address'         => sanitize_text_field( $data['ip_address'] ),
				'user_agent'         => sanitize_textarea_field( $data['user_agent'] ),
				'current_page'       => esc_url_raw( $data['current_page'] ),
				'referer'            => esc_url_raw( $data['referer'] ),
				'status'             => sanitize_key( $data['status'] ),
				'telegram_topic_id'  => ! empty( $data['telegram_topic_id'] ) ? intval( $data['telegram_topic_id'] ) : null,
				'telegram_chat_id'   => ! empty( $data['telegram_chat_id'] ) ? sanitize_text_field( $data['telegram_chat_id'] ) : null,
				'bale_chat_id'       => ! empty( $data['bale_chat_id'] ) ? sanitize_text_field( $data['bale_chat_id'] ) : null,
				'assigned_to'        => ! empty( $data['assigned_to'] ) ? sanitize_text_field( $data['assigned_to'] ) : null,
				'unread_admin_count' => absint( $data['unread_admin_count'] ),
				'unread_user_count'  => absint( $data['unread_user_count'] ),
				'metadata'           => $data['metadata'],
				'last_activity'      => $data['last_activity'],
				'created_at'         => $data['created_at'],
				'updated_at'         => $data['updated_at'],
			),
			array(
				'%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%d', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s',
			)
		);

		return $inserted ? $data['session_id'] : false;
	}

	/**
	 * Retrieve a session by its UUID.
	 *
	 * @param string $session_id UUID of the session.
	 * @return object|null
	 */
	public static function get_session( $session_id ) {
		global $wpdb;
		$table = self::get_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);
	}

	/**
	 * Retrieve a session by Telegram Topic ID (message_thread_id).
	 *
	 * @param int $topic_id The Telegram Forum topic ID.
	 * @return object|null
	 */
	public static function get_session_by_telegram_topic( $topic_id ) {
		global $wpdb;
		$table = self::get_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE telegram_topic_id = %d LIMIT 1",
				$topic_id
			)
		);
	}

	/**
	 * Update an existing session.
	 *
	 * @param string $session_id UUID of the session.
	 * @param array  $data       Data array to update.
	 * @return bool
	 */
	public static function update_session( $session_id, array $data ) {
		global $wpdb;

		if ( empty( $data ) ) {
			return false;
		}

		$table = self::get_sessions_table();
		$data['updated_at'] = current_time( 'mysql' );

		if ( isset( $data['metadata'] ) && is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$table,
			$data,
			array( 'session_id' => $session_id )
		);

		return false !== $result;
	}

	/**
	 * Update last activity timestamp of a session.
	 *
	 * @param string $session_id UUID of the session.
	 * @return bool
	 */
	public static function update_activity( $session_id ) {
		global $wpdb;
		$table = self::get_sessions_table();
		$now   = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET last_activity = %s, updated_at = %s WHERE session_id = %s",
				$now,
				$now,
				$session_id
			)
		);
	}

	/**
	 * Check whether a session or IP is blocked.
	 *
	 * @param string $session_id UUID of the session.
	 * @param string $ip         Optional client IP.
	 * @return bool
	 */
	public static function is_blocked( $session_id, $ip = '' ) {
		global $wpdb;
		$table = self::get_sessions_table();

		if ( empty( $ip ) ) {
			$ip = self::get_client_ip();
		}

		// Check session status.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$status = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT status FROM {$table} WHERE session_id = %s LIMIT 1",
				$session_id
			)
		);

		if ( 'blocked' === $status ) {
			return true;
		}

		// Check IP against settings blacklist.
		$settings = get_option( 'poshtibank_settings', array() );
		$blocked_ips = isset( $settings['security']['blocked_ips'] ) ? (array) $settings['security']['blocked_ips'] : array();

		return in_array( $ip, $blocked_ips, true );
	}

	/*
	|--------------------------------------------------------------------------
	| Message Management Operations
	|--------------------------------------------------------------------------
	*/

	/**
	 * Create and store a new message.
	 *
	 * @param array $args Message attributes.
	 * @return string|false The message_id on success, false on failure.
	 */
	public static function create_message( array $args ) {
		global $wpdb;

		if ( empty( $args['session_id'] ) || empty( $args['content'] ) ) {
			return false;
		}

		$message_id = ! empty( $args['message_id'] ) ? sanitize_text_field( $args['message_id'] ) : self::generate_uuid();
		$now        = current_time( 'mysql' );

		$defaults = array(
			'message_id'  => $message_id,
			'session_id'  => $args['session_id'],
			'sender'      => 'user', // user, admin, ai, system
			'sender_id'   => '',
			'sender_name' => '',
			'type'        => 'text', // text, voice, image, file, product_card, coupon, system
			'content'     => '',
			'metadata'    => null,
			'is_read'     => 0,
			'read_at'     => null,
			'created_at'  => $now,
		);

		$data = wp_parse_args( $args, $defaults );

		if ( is_array( $data['metadata'] ) ) {
			$data['metadata'] = wp_json_encode( $data['metadata'] );
		}

		$table = self::get_messages_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert(
			$table,
			array(
				'message_id'  => $data['message_id'],
				'session_id'  => sanitize_text_field( $data['session_id'] ),
				'sender'      => sanitize_key( $data['sender'] ),
				'sender_id'   => sanitize_text_field( $data['sender_id'] ),
				'sender_name' => sanitize_text_field( $data['sender_name'] ),
				'type'        => sanitize_key( $data['type'] ),
				'content'     => wp_kses_post( $data['content'] ),
				'metadata'    => $data['metadata'],
				'is_read'     => absint( $data['is_read'] ),
				'read_at'     => $data['read_at'],
				'created_at'  => $data['created_at'],
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s',
			)
		);

		if ( ! $inserted ) {
			return false;
		}

		// Update parent session's last activity & unread counter.
		self::update_activity( $data['session_id'] );
		self::increment_unread_counter( $data['session_id'], $data['sender'] );

		return $data['message_id'];
	}

	/**
	 * Retrieve a single message by UUID.
	 *
	 * @param string $message_id Message UUID.
	 * @return object|null
	 */
	public static function get_message( $message_id ) {
		global $wpdb;
		$table = self::get_messages_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE message_id = %s LIMIT 1",
				$message_id
			)
		);
	}

	/**
	 * Retrieve session by external platform message ID (for Reply tracking).
	 *
	 * @param string $platform    'telegram' or 'bale'.
	 * @param int    $external_id External platform message_id.
	 * @return object|null
	 */
	public static function get_session_by_external_message_id( $platform, $external_id ) {
		global $wpdb;
		$table = self::get_messages_table();
		$key   = ( 'telegram' === $platform ) ? 'telegram_message_id' : 'bale_message_id';
		$search = '%"' . $key . '":' . intval( $external_id ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$session_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT session_id FROM {$table} WHERE metadata LIKE %s ORDER BY id DESC LIMIT 1",
				$search
			)
		);

		return $session_id ? self::get_session( $session_id ) : null;
	}

	/**
	 * Retrieve the most recently active open session.
	 *
	 * @return object|null
	 */
	public static function get_latest_active_session() {
		global $wpdb;
		$table = self::get_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row(
			"SELECT * FROM {$table} WHERE status = 'open' ORDER BY last_activity DESC LIMIT 1"
		);
	}

	/**
	 * Retrieve messages for a given session.
	 *
	 * @param string $session_id UUID of the session.
	 * @param int    $limit      Number of messages to retrieve.
	 * @param int    $before_id  Optional pagination cursor (fetch messages older than this primary ID).
	 * @return array
	 */
	public static function get_messages( $session_id, $limit = 50, $before_id = 0 ) {
		global $wpdb;
		$table = self::get_messages_table();
		$limit = absint( $limit );

		if ( $before_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_id = %s AND id < %d ORDER BY id DESC LIMIT %d",
					$session_id,
					$before_id,
					$limit
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$results = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE session_id = %s ORDER BY id DESC LIMIT %d",
					$session_id,
					$limit
				)
			);
		}

		// Return in chronological order (oldest first).
		return array_reverse( (array) $results );
	}

	/**
	 * Get new messages since a given message ID or timestamp (for Adaptive Polling).
	 *
	 * @param string $session_id    UUID of the session.
	 * @param int    $after_id      ID after which to fetch messages.
	 * @param string $reader_sender Who is reading ('user' or 'admin').
	 * @return array
	 */
	public static function get_new_messages_since( $session_id, $after_id = 0, $reader_sender = 'user' ) {
		global $wpdb;
		$table = self::get_messages_table();
		$after_id = absint( $after_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %s AND id > %d ORDER BY id ASC LIMIT 50",
				$session_id,
				$after_id
			)
		);
	}

	/**
	 * Mark messages in a session as read.
	 *
	 * @param string $session_id UUID of the session.
	 * @param string $reader     Who read the messages ('user' or 'admin').
	 * @return bool
	 */
	public static function mark_messages_as_read( $session_id, $reader = 'user' ) {
		global $wpdb;
		$messages_table = self::get_messages_table();
		$sessions_table = self::get_sessions_table();
		$now            = current_time( 'mysql' );

		// If user read, mark messages from admin/ai as read.
		// If admin read, mark messages from user as read.
		$target_sender = ( 'user' === $reader ) ? 'admin' : 'user';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$messages_table} SET is_read = 1, read_at = %s WHERE session_id = %s AND sender = %s AND is_read = 0",
				$now,
				$session_id,
				$target_sender
			)
		);

		// Reset unread counter on the session.
		$counter_col = ( 'user' === $reader ) ? 'unread_user_count' : 'unread_admin_count';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$sessions_table} SET {$counter_col} = 0, updated_at = %s WHERE session_id = %s",
				$now,
				$session_id
			)
		);
	}

	/**
	 * Increment unread counter on session.
	 *
	 * @param string $session_id UUID of the session.
	 * @param string $sender     Sender role ('user', 'admin', 'ai', 'system').
	 */
	private static function increment_unread_counter( $session_id, $sender ) {
		global $wpdb;
		$sessions_table = self::get_sessions_table();

		if ( 'user' === $sender ) {
			// Admin has 1 more unread message.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sessions_table} SET unread_admin_count = unread_admin_count + 1 WHERE session_id = %s",
					$session_id
				)
			);
		} else {
			// User has 1 more unread message.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$sessions_table} SET unread_user_count = unread_user_count + 1 WHERE session_id = %s",
					$session_id
				)
			);
		}
	}

	/*
	|--------------------------------------------------------------------------
	| Cleanup and Maintenance (WP-Cron support)
	|--------------------------------------------------------------------------
	*/

	/**
	 * Delete sessions and messages older than X days.
	 *
	 * @param int $retention_days Days to keep. 0 to disable.
	 * @return array Number of deleted sessions and messages.
	 */
	public static function cleanup_old_records( $retention_days = 30 ) {
		global $wpdb;

		$retention_days = absint( $retention_days );
		if ( $retention_days <= 0 ) {
			return array(
				'deleted_sessions' => 0,
				'deleted_messages' => 0,
			);
		}

		$sessions_table = self::get_sessions_table();
		$messages_table = self::get_messages_table();

		$cutoff_date = gmdate( 'Y-m-d H:i:s', time() - ( $retention_days * DAY_IN_SECONDS ) );

		// 1. Find sessions older than the cutoff date.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$old_sessions = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT session_id FROM {$sessions_table} WHERE last_activity < %s",
				$cutoff_date
			)
		);

		$deleted_messages = 0;
		$deleted_sessions = 0;

		if ( ! empty( $old_sessions ) ) {
			// Chunk session IDs to avoid query length overflow on large databases.
			$chunks = array_chunk( $old_sessions, 200 );

			foreach ( $chunks as $chunk ) {
				$placeholders = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );

				// Delete messages belonging to these sessions.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$del_msg = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$messages_table} WHERE session_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$chunk
					)
				);

				if ( false !== $del_msg ) {
					$deleted_messages += $del_msg;
				}

				// Delete the sessions themselves.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$del_sess = $wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$sessions_table} WHERE session_id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$chunk
					)
				);

				if ( false !== $del_sess ) {
					$deleted_sessions += $del_sess;
				}
			}
		}

		// Also clean orphaned messages if any exist.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"DELETE m FROM {$messages_table} m LEFT JOIN {$sessions_table} s ON m.session_id = s.session_id WHERE s.session_id IS NULL"
		);

		return array(
			'deleted_sessions' => $deleted_sessions,
			'deleted_messages' => $deleted_messages,
		);
	}

	/**
	 * Extract user IP address taking common proxies and Cloudflare into account safely.
	 *
	 * @return string
	 */
	public static function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',        // Nginx proxy
			'HTTP_X_FORWARDED_FOR',  // Standard proxy
			'REMOTE_ADDR',           // Direct connection
		);

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$raw_ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// If multiple IPs (X-Forwarded-For: client, proxy1, proxy2), extract the first one.
				if ( strpos( $raw_ip, ',' ) !== false ) {
					$parts  = explode( ',', $raw_ip );
					$raw_ip = trim( $parts[0] );
				}
				if ( filter_var( $raw_ip, FILTER_VALIDATE_IP ) ) {
					return $raw_ip;
				}
			}
		}

		return '127.0.0.1';
	}
}
