<?php
/**
 * REST API Webhook Controller: Handles incoming updates from Telegram and Bale.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-telegram.php';
require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-bale.php';
require_once POSHTIBANK_PATH . 'includes/media/class-poshtibank-media.php';
require_once POSHTIBANK_PATH . 'includes/woocommerce/class-poshtibank-bot-commands.php';

/**
 * Class Poshtibank_REST_Webhook
 */
class Poshtibank_REST_Webhook {

	/**
	 * Register Webhook routes for Telegram and Bale.
	 */
	public function register_routes() {
		// Telegram Inbound Webhook
		register_rest_route(
			'poshtibank/v1',
			'/webhook/telegram',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_telegram' ),
				'permission_callback' => '__return_true',
			)
		);

		// Bale Inbound Webhook
		register_rest_route(
			'poshtibank/v1',
			'/webhook/bale',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_bale' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle Telegram Inbound Webhook update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_telegram( WP_REST_Request $request ) {
		$settings = Poshtibank_Settings::get_settings();

		// 1. Verify Secret Token header if configured
		$secret_token = $settings['telegram']['webhook_secret'] ?? '';
		$incoming_token = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );

		if ( ! empty( $secret_token ) && ! empty( $incoming_token ) && ! hash_equals( $secret_token, $incoming_token ) ) {
			return new WP_REST_Response( array( 'error' => 'Invalid webhook secret token.' ), 403 );
		}

		$payload = $request->get_json_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$telegram = new Poshtibank_Bridge_Telegram();

		// 2. Handle Inline Keyboard Callback Queries
		if ( ! empty( $payload['callback_query'] ) ) {
			return $this->process_telegram_callback( $telegram, $payload['callback_query'] );
		}

		// 3. Handle Inbound Admin Message
		$message = $payload['message'] ?? ( $payload['edited_message'] ?? null );
		if ( ! empty( $message ) ) {
			return $this->process_telegram_message( $telegram, $message );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Process Telegram Callback Query (Inline button clicked by admin).
	 *
	 * @param Poshtibank_Bridge_Telegram $telegram Telegram bridge.
	 * @param array                      $callback Callback query data.
	 * @return WP_REST_Response
	 */
	protected function process_telegram_callback( $telegram, array $callback ) {
		$query_id = $callback['id'] ?? '';
		$data     = $callback['data'] ?? '';

		if ( empty( $query_id ) || empty( $data ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$parts      = explode( ':', $data );
		$action     = $parts[0] ?? '';
		$session_id = $parts[1] ?? '';

		$session = Poshtibank_DB::get_session( $session_id );
		if ( ! $session ) {
			$telegram->answer_callback_query( $query_id, 'گفتگوی مورد نظر یافت نشد.', true );
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		switch ( $action ) {
			case 'pb_block':
				Poshtibank_DB::update_session( $session_id, array( 'status' => 'blocked' ) );
				$telegram->answer_callback_query( $query_id, '⛔ این کاربر با موفقیت مسدود شد.', true );
				break;

			case 'pb_close':
				Poshtibank_DB::update_session( $session_id, array( 'status' => 'closed' ) );
				$telegram->answer_callback_query( $query_id, '🔒 گفتگو بسته شد.', false );
				break;

			case 'pb_info':
				$name  = ! empty( $session->user_name ) ? $session->user_name : 'مهمان';
				$phone = ! empty( $session->user_phone ) ? $session->user_phone : 'ثبت نشده';
				$ip    = ! empty( $session->ip_address ) ? $session->ip_address : '—';
				$page  = ! empty( $session->current_page ) ? $session->current_page : '—';
				$time  = $session->created_at;

				$info_text = sprintf(
					"👤 نام: %s\n📱 شماره: %s\n🌐 آی‌پی: %s\n🔗 صفحه: %s\n⏰ زمان شروع: %s",
					$name,
					$phone,
					$ip,
					$page,
					$time
				);

				$telegram->answer_callback_query( $query_id, $info_text, true );
				break;

			default:
				$telegram->answer_callback_query( $query_id, 'عملیات نامعتبر.', false );
				break;
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Process Telegram Inbound Message from Admin.
	 *
	 * @param Poshtibank_Bridge_Telegram $telegram Telegram bridge.
	 * @param array                      $message  Telegram message payload.
	 * @return WP_REST_Response
	 */
	protected function process_telegram_message( $telegram, array $message ) {
		// Ignore bot messages to avoid infinite loops
		if ( ! empty( $message['from']['is_bot'] ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$session = null;

		// 1. Check message_thread_id (Supergroup Forum Topic)
		if ( ! empty( $message['message_thread_id'] ) ) {
			$thread_id = intval( $message['message_thread_id'] );
			$session   = Poshtibank_DB::get_session_by_telegram_topic( $thread_id );
		}

		// 2. Check reply_to_message (Direct reply to user message)
		if ( ! $session && ! empty( $message['reply_to_message']['message_id'] ) ) {
			$reply_msg_id = intval( $message['reply_to_message']['message_id'] );
			$session      = Poshtibank_DB::get_session_by_external_message_id( 'telegram', $reply_msg_id );
		}

		// 3. Fallback: Most recently active open session
		if ( ! $session ) {
			$session = Poshtibank_DB::get_latest_active_session();
		}

		if ( ! $session ) {
			// No matching session found
			return new WP_REST_Response( array( 'ok' => true, 'note' => 'No active session found.' ), 200 );
		}

		// Extract content and type
		$content  = '';
		$type     = 'text';
		$metadata = array(
			'telegram_message_id' => $message['message_id'] ?? null,
			'sender_username'     => $message['from']['username'] ?? '',
		);

		$settings = Poshtibank_Settings::get_settings();
		$tg_token = $settings['telegram']['bot_token'] ?? '';
		$tg_proxy = $settings['telegram']['proxy_url'] ?? '';

		if ( ! empty( $message['text'] ) ) {
			// Check for Bot Commands like /product or /coupon
			if ( Poshtibank_Bot_Commands::is_command( $message['text'] ) ) {
				$feedback = Poshtibank_Bot_Commands::handle( $session, $message['text'] );
				$extra    = array();
				if ( ! empty( $message['message_thread_id'] ) ) {
					$extra['message_thread_id'] = $message['message_thread_id'];
				}
				$chat_target = ! empty( $message['chat']['id'] ) ? (string) $message['chat']['id'] : $telegram->get_chat_id();
				$telegram->send_text( $chat_target, $feedback, $extra );
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}
			$content = $message['text'];
			$type    = 'text';
		} elseif ( ! empty( $message['voice'] ) ) {
			$file_id = $message['voice']['file_id'] ?? '';
			$local_url = Poshtibank_Media::download_telegram_file( $file_id, $tg_token, $tg_proxy );
			$content = $local_url ? $local_url : $file_id;
			$type    = 'voice';
			$metadata['duration'] = $message['voice']['duration'] ?? 0;
		} elseif ( ! empty( $message['photo'] ) ) {
			// Get highest resolution photo
			$photos  = $message['photo'];
			$last    = end( $photos );
			$file_id = $last['file_id'] ?? '';
			$local_url = Poshtibank_Media::download_telegram_file( $file_id, $tg_token, $tg_proxy );
			$content = $local_url ? $local_url : $file_id;
			$type    = 'image';
		} elseif ( ! empty( $message['document'] ) ) {
			$file_id = $message['document']['file_id'] ?? '';
			$local_url = Poshtibank_Media::download_telegram_file( $file_id, $tg_token, $tg_proxy );
			$content = $local_url ? $local_url : $file_id;
			$type    = 'file';
			$metadata['file_name'] = $message['document']['file_name'] ?? 'document';
		}

		if ( empty( $content ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Store admin message in database
		$admin_name = $message['from']['first_name'] ?? __( 'پشتیبان', 'poshtibank' );
		Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'admin',
				'sender_id'   => (string) ( $message['from']['id'] ?? '' ),
				'sender_name' => $admin_name,
				'type'        => $type,
				'content'     => $content,
				'metadata'    => $metadata,
			)
		);

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/**
	 * Handle Bale Inbound Webhook update.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_bale( WP_REST_Request $request ) {
		$settings = Poshtibank_Settings::get_settings();
		$payload  = $request->get_json_params();
		if ( empty( $payload ) || ! is_array( $payload ) ) {
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		$bale = new Poshtibank_Bridge_Bale();

		// Handle Callback Query
		if ( ! empty( $payload['callback_query'] ) ) {
			$callback = $payload['callback_query'];
			$query_id = $callback['id'] ?? '';
			$data     = $callback['data'] ?? '';

			if ( ! empty( $query_id ) && ! empty( $data ) ) {
				$parts      = explode( ':', $data );
				$action     = $parts[0] ?? '';
				$session_id = $parts[1] ?? '';

				$session = Poshtibank_DB::get_session( $session_id );
				if ( $session ) {
					if ( 'pb_block' === $action ) {
						Poshtibank_DB::update_session( $session_id, array( 'status' => 'blocked' ) );
						$bale->answer_callback_query( $query_id, 'کاربر مسدود شد.', true );
					} elseif ( 'pb_close' === $action ) {
						Poshtibank_DB::update_session( $session_id, array( 'status' => 'closed' ) );
						$bale->answer_callback_query( $query_id, 'گفتگو بسته شد.', false );
					} elseif ( 'pb_info' === $action ) {
						$name = $session->user_name ?: 'مهمان';
						$bale->answer_callback_query( $query_id, "کاربر: {$name} | آی‌پی: {$session->ip_address}", true );
					}
				}
			}
			return new WP_REST_Response( array( 'ok' => true ), 200 );
		}

		// Handle Message
		$message = $payload['message'] ?? null;
		if ( ! empty( $message ) ) {
			if ( ! empty( $message['from']['is_bot'] ) ) {
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}

			$session = null;

			// Check reply_to_message
			if ( ! empty( $message['reply_to_message']['message_id'] ) ) {
				$reply_msg_id = intval( $message['reply_to_message']['message_id'] );
				$session      = Poshtibank_DB::get_session_by_external_message_id( 'bale', $reply_msg_id );
			}

			if ( ! $session ) {
				$session = Poshtibank_DB::get_latest_active_session();
			}

			if ( $session ) {
				$admin_name = $message['from']['first_name'] ?? __( 'پشتیبان بله', 'poshtibank' );
				$content    = '';
				$type       = 'text';
				$metadata   = array( 'bale_message_id' => $message['message_id'] ?? null );
				$bale_token = $settings['bale']['bot_token'] ?? '';

				if ( ! empty( $message['text'] ) ) {
					// Check for Bot Commands like /product or /coupon in Bale
					if ( Poshtibank_Bot_Commands::is_command( $message['text'] ) ) {
						$feedback    = Poshtibank_Bot_Commands::handle( $session, $message['text'] );
						$chat_target = ! empty( $message['chat']['id'] ) ? (string) $message['chat']['id'] : $bale->get_chat_id();
						$bale->send_text( $chat_target, $feedback );
						return new WP_REST_Response( array( 'ok' => true ), 200 );
					}
					$content = $message['text'];
					$type    = 'text';
				} elseif ( ! empty( $message['voice'] ) ) {
					$file_id = $message['voice']['file_id'] ?? '';
					$local_url = Poshtibank_Media::download_bale_file( $file_id, $bale_token );
					$content = $local_url ? $local_url : $file_id;
					$type    = 'voice';
					$metadata['duration'] = $message['voice']['duration'] ?? 0;
				} elseif ( ! empty( $message['photo'] ) ) {
					$photos  = $message['photo'];
					$last    = end( $photos );
					$file_id = $last['file_id'] ?? '';
					$local_url = Poshtibank_Media::download_bale_file( $file_id, $bale_token );
					$content = $local_url ? $local_url : $file_id;
					$type    = 'image';
				} elseif ( ! empty( $message['document'] ) ) {
					$file_id = $message['document']['file_id'] ?? '';
					$local_url = Poshtibank_Media::download_bale_file( $file_id, $bale_token );
					$content = $local_url ? $local_url : $file_id;
					$type    = 'file';
				}

				if ( ! empty( $content ) ) {
					Poshtibank_DB::create_message(
						array(
							'session_id'  => $session->session_id,
							'sender'      => 'admin',
							'sender_id'   => (string) ( $message['from']['id'] ?? '' ),
							'sender_name' => $admin_name,
							'type'        => $type,
							'content'     => $content,
							'metadata'    => $metadata,
						)
					);
				}
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}
