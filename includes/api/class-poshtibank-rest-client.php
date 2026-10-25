<?php
/**
 * REST API Client Controller: Handles frontend chat widget endpoints.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_REST_Client
 */
class Poshtibank_REST_Client {

	/**
	 * Register client endpoints.
	 */
	public function register_routes() {
		// 1. Session Init: /poshtibank/v1/session/init
		register_rest_route(
			'poshtibank/v1',
			'/session/init',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'session_init' ),
				'permission_callback' => '__return_true',
			)
		);

		// 2. Send Message: /poshtibank/v1/messages/send
		register_rest_route(
			'poshtibank/v1',
			'/messages/send',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'send_message' ),
				'permission_callback' => '__return_true',
			)
		);

		// 3. Adaptive Poll: /poshtibank/v1/messages/poll
		register_rest_route(
			'poshtibank/v1',
			'/messages/poll',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'poll_messages' ),
				'permission_callback' => '__return_true',
			)
		);

		// 4. Session Close: /poshtibank/v1/session/close
		register_rest_route(
			'poshtibank/v1',
			'/session/close',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'session_close' ),
				'permission_callback' => '__return_true',
			)
		);

		// 5. Media Upload: /poshtibank/v1/media/upload
		register_rest_route(
			'poshtibank/v1',
			'/media/upload',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_media' ),
				'permission_callback' => '__return_true',
			)
		);

		// 6. Conversation Mode: /poshtibank/v1/session/mode
		register_rest_route(
			'poshtibank/v1',
			'/session/mode',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'session_mode' ),
				'permission_callback' => '__return_true',
			)
		);

		// 7. Session Feedback (CSAT): /poshtibank/v1/session/feedback
		register_rest_route(
			'poshtibank/v1',
			'/session/feedback',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'session_feedback' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Endpoint: Initialize or resume a chat session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function session_init( WP_REST_Request $request ) {
		$params     = $request->get_json_params();
		$session_id = ! empty( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';
		$client_ip  = Poshtibank_DB::get_client_ip();

		// Check if IP is blacklisted
		if ( Poshtibank_DB::is_blocked( $session_id, $client_ip ) ) {
			return new WP_REST_Response(
				array(
					'status'  => 'blocked',
					'message' => __( 'امکان دسترسی به چت آنلاین برای شما مسدود شده است.', 'poshtibank' ),
				),
				403
			);
		}

		$session = null;
		if ( ! empty( $session_id ) ) {
			$session = Poshtibank_DB::get_session( $session_id );
		}

		// Create new session if does not exist
		if ( ! $session ) {
			$new_session_id = Poshtibank_DB::create_session(
				array(
					'session_id'   => $session_id,
					'user_name'    => ! empty( $params['user_name'] ) ? sanitize_text_field( $params['user_name'] ) : '',
					'user_phone'   => ! empty( $params['user_phone'] ) ? sanitize_text_field( $params['user_phone'] ) : '',
					'user_email'   => ! empty( $params['user_email'] ) ? sanitize_email( $params['user_email'] ) : '',
					'current_page' => ! empty( $params['current_page'] ) ? esc_url_raw( $params['current_page'] ) : '',
					'referer'      => ! empty( $params['referer'] ) ? esc_url_raw( $params['referer'] ) : '',
					'ip_address'   => $client_ip,
				)
			);

			if ( ! $new_session_id ) {
				return new WP_REST_Response( array( 'error' => __( 'خطا در ایجاد نشست گفتگو.', 'poshtibank' ) ), 500 );
			}

			$session = Poshtibank_DB::get_session( $new_session_id );
		} else {
			// Update context on resume
			$update_data = array();
			if ( ! empty( $params['current_page'] ) ) {
				$update_data['current_page'] = esc_url_raw( $params['current_page'] );
			}
			if ( ! empty( $params['user_name'] ) && empty( $session->user_name ) ) {
				$update_data['user_name'] = sanitize_text_field( $params['user_name'] );
			}
			if ( ! empty( $params['user_phone'] ) && empty( $session->user_phone ) ) {
				$update_data['user_phone'] = sanitize_text_field( $params['user_phone'] );
			}

			if ( ! empty( $update_data ) ) {
				Poshtibank_DB::update_session( $session->session_id, $update_data );
			}
		}

		// Store WooCommerce Context if provided
		if ( ! empty( $params['wc'] ) && is_array( $params['wc'] ) ) {
			$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
			if ( ! empty( $params['wc']['product'] ) ) {
				$meta['wc_product'] = $params['wc']['product'];
			}
			if ( ! empty( $params['wc']['cart'] ) ) {
				$meta['wc_cart'] = $params['wc']['cart'];
			}
			if ( get_current_user_id() > 0 ) {
				require_once POSHTIBANK_PATH . 'includes/woocommerce/class-poshtibank-woocommerce.php';
				$meta['wc_customer'] = Poshtibank_WooCommerce::get_customer_summary( get_current_user_id() );
			}
			Poshtibank_DB::update_session( $session->session_id, array( 'metadata' => $meta ) );

			// Keep the in-memory row in step: later steps merge into this value.
			$session->metadata = wp_json_encode( $meta );
		}

		// Persist the responder the customer picked, if any.
		$requested_mode = ! empty( $params['mode'] ) ? sanitize_key( $params['mode'] ) : '';
		if ( ! in_array( $requested_mode, array( 'ai', 'human' ), true ) ) {
			$requested_mode = '';
		}

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';

		if ( 'ai' === $requested_mode && ! Poshtibank_AI::is_available() ) {
			$requested_mode = 'human';
		}

		if ( '' !== $requested_mode ) {
			$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
			$meta = is_array( $meta ) ? $meta : array();

			if ( ( $meta['chat_mode'] ?? '' ) !== $requested_mode ) {
				$meta['chat_mode'] = $requested_mode;
				if ( 'human' === $requested_mode ) {
					$meta['ai_paused'] = 1;
				} else {
					unset( $meta['ai_paused'] );
				}

				Poshtibank_DB::update_session( $session->session_id, array( 'metadata' => $meta ) );
				$session->metadata = wp_json_encode( $meta );
			}
		}

		// Retrieve recent messages
		$messages = Poshtibank_DB::get_messages( $session->session_id, 50 );

		$settings = Poshtibank_Settings::get_settings();

		// Check business hours
		$is_online = $this->is_business_hours( $settings );

		return new WP_REST_Response(
			array(
				'session_id'       => $session->session_id,
				'status'           => $session->status,
				'mode'             => Poshtibank_AI::get_session_mode( $session ),
				'ai_available'     => Poshtibank_AI::is_available(),
				'is_online'        => $is_online,
				'offline_message'  => $settings['business_hours']['offline_message'] ?? '',
				'require_contact'  => ! empty( $settings['general']['require_contact'] ),
				'primary_color'    => $settings['general']['primary_color'] ?? '#2563eb',
				'widget_title'     => $settings['general']['widget_title'] ?? 'پشتیبانی آنلاین',
				'widget_subtitle'  => $settings['general']['widget_subtitle'] ?? '',
				'channels'         => $settings['channels'] ?? array(),
				'messages'         => $messages,
			),
			200
		);
	}

	/**
	 * Endpoint: Switch a live conversation between AI and human support.
	 *
	 * Handing over to a human is recorded as a message and announced to the
	 * bridges, so an operator sees the request without the customer having to
	 * type a trigger phrase first.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function session_mode( WP_REST_Request $request ) {
		$params     = $request->get_json_params();
		$session_id = ! empty( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';
		$mode       = ! empty( $params['mode'] ) ? sanitize_key( $params['mode'] ) : '';

		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست الزامی است.', 'poshtibank' ) ), 400 );
		}

		if ( ! in_array( $mode, array( 'ai', 'human' ), true ) ) {
			return new WP_REST_Response( array( 'error' => __( 'حالت گفتگو نامعتبر است.', 'poshtibank' ) ), 400 );
		}

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => __( 'نشست گفتگو یافت نشد.', 'poshtibank' ) ), 404 );
		}

		if ( 'blocked' === $session->status ) {
			return new WP_REST_Response( array( 'error' => __( 'دسترسی شما به این گفتگو مسدود است.', 'poshtibank' ) ), 403 );
		}

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';

		if ( 'ai' === $mode && ! Poshtibank_AI::is_available() ) {
			return new WP_REST_Response( array( 'error' => __( 'دستیار هوشمند در حال حاضر فعال نیست.', 'poshtibank' ) ), 409 );
		}

		if ( 'human' === $mode ) {
			$ai     = new Poshtibank_AI();
			$notice = $ai->handle_human_handoff( $session );

			return new WP_REST_Response(
				array(
					'mode'   => 'human',
					'notice' => $notice,
					'status' => $session->status,
				),
				200
			);
		}

		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
		$meta = is_array( $meta ) ? $meta : array();

		$meta['chat_mode'] = 'ai';
		unset( $meta['ai_paused'] );
		Poshtibank_DB::update_session( $session->session_id, array( 'metadata' => $meta ) );

		return new WP_REST_Response(
			array(
				'mode'   => 'ai',
				'status' => $session->status,
			),
			200
		);
	}

	/**
	 * Endpoint: Send message from client widget.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function send_message( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		// 1. Honeypot check (Anti-Spam)
		if ( ! empty( $params['hp_check'] ) ) {
			return new WP_REST_Response( array( 'error' => 'Spam detected.' ), 400 );
		}

		// 2. Validate session
		$session_id = ! empty( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';
		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست الزامی است.', 'poshtibank' ) ), 400 );
		}

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => __( 'نشست گفتگو یافت نشد.', 'poshtibank' ) ), 404 );
		}

		$client_ip = Poshtibank_DB::get_client_ip();

		// 3. Blocked check
		if ( 'blocked' === $session->status || Poshtibank_DB::is_blocked( $session_id, $client_ip ) ) {
			return new WP_REST_Response( array( 'error' => __( 'دسترسی شما به این گفتگو مسدود است.', 'poshtibank' ) ), 403 );
		}

		// 4. Rate Limiting check
		if ( ! $this->check_rate_limit( $session_id, $client_ip ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'تعداد پیام‌های ارسالی شما بیش از حد مجاز است. لطفاً یک دقیقه صبور باشید.', 'poshtibank' ) ),
				429
			);
		}

		// 5. Validate content
		$content = ! empty( $params['content'] ) ? trim( $params['content'] ) : '';
		$type    = ! empty( $params['type'] ) ? sanitize_key( $params['type'] ) : 'text';

		if ( empty( $content ) ) {
			return new WP_REST_Response( array( 'error' => __( 'متن پیام نمی‌تواند خالی باشد.', 'poshtibank' ) ), 400 );
		}

		// If session was closed, reopen it
		if ( 'closed' === $session->status ) {
			Poshtibank_DB::update_session( $session_id, array( 'status' => 'open' ) );
			$session->status = 'open';
		}

		// 6. Create message record in database
		$message_id = Poshtibank_DB::create_message(
			array(
				'session_id'  => $session_id,
				'sender'      => 'user',
				'sender_id'   => (string) get_current_user_id(),
				'sender_name' => ! empty( $session->user_name ) ? $session->user_name : __( 'کاربر', 'poshtibank' ),
				'type'        => $type,
				'content'     => $content,
				'metadata'    => ! empty( $params['metadata'] ) ? (array) $params['metadata'] : null,
			)
		);

		if ( ! $message_id ) {
			return new WP_REST_Response( array( 'error' => __( 'خطا در ثبت پیام.', 'poshtibank' ) ), 500 );
		}

		$created_message = Poshtibank_DB::get_message( $message_id );

		// 7. Dispatch message to Telegram & Bale bridges
		require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-manager.php';
		$bridge_manager = new Poshtibank_Bridge_Manager();
		$bridge_manager->dispatch_to_bridges(
			$session,
			array(
				'message_id' => $message_id,
				'type'       => $type,
				'content'    => $content,
				'metadata'   => ! empty( $params['metadata'] ) ? (array) $params['metadata'] : array(),
			)
		);

		// 8. Process AI First-Line Responder (Phase 7)
		// Only conversations the customer handed to the assistant get an answer.
		$ai_reply = false;
		if ( 'text' === $type ) {
			require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';
			if ( 'ai' === Poshtibank_AI::get_session_mode( $session ) ) {
				$ai = new Poshtibank_AI();
				if ( $ai->is_enabled() ) {
					$ai_reply = $ai->process_message( $session, $content );
				}
			}
		}

		// Hand the stored reply back with its real id so the widget does not
		// paint a second copy when the poll returns the same message.
		$ai_message = null;
		if ( $ai_reply ) {
			$latest     = Poshtibank_DB::get_messages( $session->session_id, 1 );
			$ai_message = ! empty( $latest ) && 'ai' === $latest[0]->sender ? $latest[0] : null;
		}

		return new WP_REST_Response(
			array(
				'success'    => true,
				'message'    => $created_message,
				'ai_reply'   => $ai_reply,
				'ai_message' => $ai_message,
			),
			201
		);
	}

	/**
	 * Endpoint: Adaptive Short Polling for new messages.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function poll_messages( WP_REST_Request $request ) {
		$session_id = sanitize_text_field( $request->get_param( 'session_id' ) );
		$after_id   = absint( $request->get_param( 'after_id' ) );

		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست نامعتبر است.', 'poshtibank' ) ), 400 );
		}

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => __( 'نشست یافت نشد.', 'poshtibank' ) ), 404 );
		}

		// Fetch new messages sent by admin, ai, or system
		$new_messages = Poshtibank_DB::get_new_messages_since( $session_id, $after_id, 'user' );

		// If new messages found, mark admin messages as read
		if ( ! empty( $new_messages ) ) {
			Poshtibank_DB::mark_messages_as_read( $session_id, 'user' );
		}

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';

		return new WP_REST_Response(
			array(
				'status'       => $session->status,
				// The assistant may have handed the thread over on its own.
				'mode'         => Poshtibank_AI::get_session_mode( $session ),
				'new_messages' => $new_messages,
			),
			200
		);
	}

	/**
	 * Endpoint: Close active session by user.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function session_close( WP_REST_Request $request ) {
		$params     = $request->get_json_params();
		$session_id = ! empty( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';

		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست نامعتبر است.', 'poshtibank' ) ), 400 );
		}

		Poshtibank_DB::update_session( $session_id, array( 'status' => 'closed' ) );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Check Rate Limit for Anti-Spam protection.
	 *
	 * @param string $session_id Session UUID.
	 * @param string $ip         Client IP.
	 * @return bool True if allowed, false if limit exceeded.
	 */
	protected function check_rate_limit( $session_id, $ip ) {
		global $wpdb;

		$settings = Poshtibank_Settings::get_settings();
		$max_msgs = $settings['security']['rate_limit_messages'] ?? 10;
		$window   = $settings['security']['rate_limit_window'] ?? 60;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $window );
		$table  = Poshtibank_DB::get_messages_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$table} WHERE session_id = %s AND sender = 'user' AND created_at > %s",
				$session_id,
				$cutoff
			)
		);

		// If aggressive spamming detected (2x limit), automatically blacklist IP
		if ( intval( $count ) >= ( intval( $max_msgs ) * 2 ) && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			$raw_settings = get_option( Poshtibank_Settings::OPTION_KEY, array() );
			$current_blocked = (array) ( $raw_settings['security']['blocked_ips'] ?? array() );
			if ( ! in_array( $ip, $current_blocked, true ) ) {
				$current_blocked[] = $ip;
				$raw_settings['security']['blocked_ips'] = $current_blocked;
				Poshtibank_Settings::update_settings( $raw_settings );
			}
			return false;
		}

		return intval( $count ) < intval( $max_msgs );
	}

	/**
	 * Endpoint: Upload media (voice, image, screenshot, file) from client widget.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function upload_media( WP_REST_Request $request ) {
		// 1. Validate session
		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست الزامی است.', 'poshtibank' ) ), 400 );
		}

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => __( 'نشست گفتگو یافت نشد.', 'poshtibank' ) ), 404 );
		}

		$client_ip = Poshtibank_DB::get_client_ip();

		// 2. Blocked check
		if ( 'blocked' === $session->status || Poshtibank_DB::is_blocked( $session_id, $client_ip ) ) {
			return new WP_REST_Response( array( 'error' => __( 'دسترسی شما به این گفتگو مسدود است.', 'poshtibank' ) ), 403 );
		}

		// 3. Rate Limit check
		if ( ! $this->check_rate_limit( $session_id, $client_ip ) ) {
			return new WP_REST_Response(
				array( 'error' => __( 'تعداد درخواست‌های شما بیش از حد مجاز است. لطفاً کمی صبر کنید.', 'poshtibank' ) ),
				429
			);
		}

		// 4. File existence check
		if ( empty( $_FILES['file'] ) ) {
			return new WP_REST_Response( array( 'error' => __( 'هیچ فایلی ارسال نشده است.', 'poshtibank' ) ), 400 );
		}

		$category = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : 'file';
		require_once POSHTIBANK_PATH . 'includes/media/class-poshtibank-media.php';

		// 5. Handle file upload safely
		$upload_result = Poshtibank_Media::handle_upload( $_FILES['file'], $category );

		if ( is_wp_error( $upload_result ) ) {
			return new WP_REST_Response( array( 'error' => $upload_result->get_error_message() ), 400 );
		}

		// 6. Map category to message type
		$msg_type = 'file';
		if ( 'voice' === $category ) {
			$msg_type = 'voice';
		} elseif ( in_array( $category, array( 'image', 'screenshot' ), true ) ) {
			$msg_type = 'image';
		}

		$duration = isset( $_POST['duration'] ) ? absint( $_POST['duration'] ) : 0;
		$metadata = array(
			'file_name' => $upload_result['file_name'],
			'file_size' => $upload_result['file_size'],
			'mime_type' => $upload_result['mime_type'],
			'category'  => $category,
		);
		if ( $duration > 0 ) {
			$metadata['duration'] = $duration;
		}

		// 7. Store message in database
		$message_id = Poshtibank_DB::create_message(
			array(
				'session_id'  => $session_id,
				'sender'      => 'user',
				'sender_id'   => (string) get_current_user_id(),
				'sender_name' => ! empty( $session->user_name ) ? $session->user_name : __( 'کاربر', 'poshtibank' ),
				'type'        => $msg_type,
				'content'     => $upload_result['url'],
				'metadata'    => $metadata,
			)
		);

		if ( ! $message_id ) {
			return new WP_REST_Response( array( 'error' => __( 'خطا در ثبت پیام رسانه.', 'poshtibank' ) ), 500 );
		}

		$created_message = Poshtibank_DB::get_message( $message_id );

		// 8. Dispatch to Telegram and Bale bridges
		require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-manager.php';
		$bridge_manager = new Poshtibank_Bridge_Manager();
		$bridge_manager->dispatch_to_bridges(
			$session,
			array(
				'message_id' => $message_id,
				'type'       => $msg_type,
				'content'    => $upload_result['url'],
				'metadata'   => $metadata,
			)
		);

		return new WP_REST_Response(
			array(
				'success' => true,
				'message' => $created_message,
				'upload'  => $upload_result,
			),
			201
		);
	}

	/**
	 * Endpoint: Submit CSAT satisfaction rating and feedback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function session_feedback( WP_REST_Request $request ) {
		$params     = $request->get_json_params();
		$session_id = ! empty( $params['session_id'] ) ? sanitize_text_field( $params['session_id'] ) : '';
		$rating     = ! empty( $params['rating'] ) ? min( 5, max( 1, absint( $params['rating'] ) ) ) : 5;
		$comment    = ! empty( $params['comment'] ) ? sanitize_textarea_field( $params['comment'] ) : '';

		if ( empty( $session_id ) ) {
			return new WP_REST_Response( array( 'error' => __( 'شناسه نشست الزامی است.', 'poshtibank' ) ), 400 );
		}

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			return new WP_REST_Response( array( 'error' => __( 'نشست یافت نشد.', 'poshtibank' ) ), 404 );
		}

		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
		$meta['csat'] = array(
			'rating'     => $rating,
			'comment'    => $comment,
			'created_at' => current_time( 'mysql' ),
		);

		Poshtibank_DB::update_session( $session_id, array( 'metadata' => $meta ) );

		// Send feedback notice to Telegram / Bale
		require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-manager.php';
		$bridge_manager = new Poshtibank_Bridge_Manager();

		$stars = str_repeat( '⭐️', $rating );
		$notice = sprintf(
			"📊 <b>ثبت رضایت مشتری (CSAT):</b>\nامتیاز: %s (%d از ۵)\nکاربر: %s\nنظر: %s",
			$stars,
			$rating,
			esc_html( $session->user_name ?: 'مهمان' ),
			esc_html( $comment ?: 'بدون توضیح' )
		);

		$msg_data = array(
			'message_id' => Poshtibank_DB::generate_uuid(),
			'type'       => 'system',
			'content'    => $notice,
		);
		$bridge_manager->dispatch_to_bridges( $session, $msg_data );

		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Check whether the current time falls within configured business hours.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	protected function is_business_hours( array $settings ) {
		if ( empty( $settings['business_hours']['enabled'] ) ) {
			return true; // Business hours not restricted -> Always online
		}

		// Current day of week in Tehran / WP timezone
		$wp_timestamp = current_time( 'timestamp' );
		$day_num      = intval( gmdate( 'w', $wp_timestamp ) ); // 0 (Sun) to 6 (Sat)

		// Map PHP date('w') to Poshtibank days:
		// 6 => sat, 0 => sun, 1 => mon, 2 => tue, 3 => wed, 4 => thu, 5 => fri
		$map = array(
			6 => 'sat',
			0 => 'sun',
			1 => 'mon',
			2 => 'tue',
			3 => 'wed',
			4 => 'thu',
			5 => 'fri',
		);

		$current_day_key = $map[ $day_num ] ?? 'sat';
		$day_cfg         = $settings['business_hours']['schedule'][ $current_day_key ] ?? null;

		if ( ! $day_cfg || empty( $day_cfg['active'] ) ) {
			return false; // Closed today
		}

		$current_time = gmdate( 'H:i', $wp_timestamp );
		$start_time   = $day_cfg['start'] ?? '09:00';
		$end_time     = $day_cfg['end'] ?? '18:00';

		return ( $current_time >= $start_time && $current_time <= $end_time );
	}
}
