<?php
/**
 * Admin dashboard and settings manager for Poshtibank.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Admin
 */
class Poshtibank_Admin {

	/**
	 * Settings option key in wp_options.
	 */
	const OPTION_KEY = Poshtibank_Settings::OPTION_KEY;

	/**
	 * Initialize admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu_pages' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX Endpoints
		add_action( 'wp_ajax_poshtibank_save_settings', array( $this, 'ajax_save_settings' ) );
		add_action( 'wp_ajax_poshtibank_test_telegram', array( $this, 'ajax_test_telegram' ) );
		add_action( 'wp_ajax_poshtibank_set_telegram_webhook', array( $this, 'ajax_set_telegram_webhook' ) );
		add_action( 'wp_ajax_poshtibank_delete_telegram_webhook', array( $this, 'ajax_delete_telegram_webhook' ) );
		add_action( 'wp_ajax_poshtibank_test_bale', array( $this, 'ajax_test_bale' ) );
		add_action( 'wp_ajax_poshtibank_set_bale_webhook', array( $this, 'ajax_set_bale_webhook' ) );
		add_action( 'wp_ajax_poshtibank_delete_bale_webhook', array( $this, 'ajax_delete_bale_webhook' ) );
		add_action( 'wp_ajax_poshtibank_test_ai', array( $this, 'ajax_test_ai' ) );
		add_action( 'admin_post_poshtibank_update_session_status', array( $this, 'handle_update_session_status' ) );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
		add_action( 'admin_notices', array( $this, 'render_configuration_notices' ) );
		add_action( 'wp_ajax_poshtibank_send_admin_reply', array( $this, 'ajax_send_admin_reply' ) );
		add_action( 'wp_ajax_poshtibank_poll_conversation', array( $this, 'ajax_poll_conversation' ) );
	}

	/**
	 * Register Admin Menu Pages.
	 */
	public function register_menu_pages() {
		// Main Top-level Menu
		add_menu_page(
			__( 'پشتیبانک', 'poshtibank' ),
			__( 'پشتیبانک', 'poshtibank' ),
			'manage_options',
			'poshtibank',
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			30
		);

		// Submenu: Settings (same slug as parent)
		add_submenu_page(
			'poshtibank',
			__( 'تنظیمات پشتیبانک', 'poshtibank' ),
			__( 'تنظیمات', 'poshtibank' ),
			'manage_options',
			'poshtibank',
			array( $this, 'render_settings_page' )
		);

		// Submenu: Live Chats
		add_submenu_page(
			'poshtibank',
			__( 'گفتگوهای زنده — پشتیبانک', 'poshtibank' ),
			__( 'گفتگوهای زنده', 'poshtibank' ),
			$this->chat_capability(),
			'poshtibank-chats',
			array( $this, 'render_chats_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts only on Poshtibank admin pages.
	 *
	 * @param string $hook The current admin page.
	 */
	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'poshtibank' ) === false ) {
			return;
		}

		// Color picker
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		// Plugin Admin CSS
		wp_enqueue_style(
			'poshtibank-admin-style',
			POSHTIBANK_URL . 'assets/css/poshtibank-admin.css',
			array( 'wp-color-picker' ),
			POSHTIBANK_VERSION
		);

		// Plugin Admin JS
		wp_enqueue_script(
			'poshtibank-admin-script',
			POSHTIBANK_URL . 'assets/js/poshtibank-admin.js',
			array( 'jquery', 'wp-color-picker' ),
			POSHTIBANK_VERSION,
			true
		);

		$settings = self::get_settings();

		// Localize script with AJAX URL, Nonces, and Webhook details
		wp_localize_script(
			'poshtibank-admin-script',
			'poshtibankAdmin',
			array(
				'ajaxUrl'            => admin_url( 'admin-ajax.php' ),
				'nonce'              => wp_create_nonce( 'poshtibank_admin_action' ),
				'telegramWebhookUrl' => rest_url( 'poshtibank/v1/webhook/telegram' ),
				'baleWebhookUrl'     => rest_url( 'poshtibank/v1/webhook/bale' ),
				'isSSL'              => is_ssl(),
				'i18n'               => array(
					'saving'          => __( 'در حال ذخیره‌سازی...', 'poshtibank' ),
					'saved'           => __( 'تنظیمات با موفقیت ذخیره شد.', 'poshtibank' ),
					'error'           => __( 'خطایی رخ داد. لطفاً مجدداً تلاش کنید.', 'poshtibank' ),
					'testing'         => __( 'در حال بررسی اتصال...', 'poshtibank' ),
					'settingWebhook'  => __( 'در حال ثبت وبهوک...', 'poshtibank' ),
					'deletingWebhook' => __( 'در حال حذف وبهوک...', 'poshtibank' ),
					'sslWarning'      => __( 'توجه: ثبت وبهوک تلگرام و بله نیازمند فعال بودن SSL (HTTPS) روی دامنه سایت شماست.', 'poshtibank' ),
					'confirmBlock'    => __( 'آیا از مسدودسازی این کاربر مطمئن هستید؟', 'poshtibank' ),
					'confirmDelete'   => __( 'آیا از حذف مطمئن هستید؟', 'poshtibank' ),
				),
			)
		);
	}

	/**
	 * Retrieve all stored settings with default fallbacks.
	 *
	 * @return array
	 */
	public static function get_settings() {
		return Poshtibank_Settings::get_settings();
	}

	/**
	 * Render the Settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'poshtibank' ) );
		}

		$settings = self::get_settings();
		require_once POSHTIBANK_PATH . 'includes/admin/views/settings-page.php';
	}

	/**
	 * Render the Live Chats page.
	 */
	public function render_chats_page() {
		if ( ! $this->can_manage_chats() ) {
			wp_die( esc_html__( 'شما اجازه دسترسی به این بخش را ندارید.', 'poshtibank' ) );
		}

		$session_id = isset( $_GET['session_id'] ) && is_string( $_GET['session_id'] )
			? sanitize_text_field( wp_unslash( $_GET['session_id'] ) )
			: '';

		if ( '' !== $session_id ) {
			$this->render_conversation( $session_id );
			return;
		}

		global $wpdb;
		$sessions_table = Poshtibank_DB::get_sessions_table();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$sessions = $wpdb->get_results(
			"SELECT * FROM {$sessions_table} ORDER BY last_activity DESC LIMIT 100"
		);

		require_once POSHTIBANK_PATH . 'includes/admin/views/chats-page.php';
	}

	/**
	 * Render a single conversation.
	 *
	 * The history is loaded on the server so the screen never depends on a
	 * second request to display the messages.
	 *
	 * @param string $session_id Session identifier from the URL.
	 */
	protected function render_conversation( $session_id ) {
		$session  = Poshtibank_DB::get_session( $session_id );
		$messages = $session ? Poshtibank_DB::get_messages( $session->session_id, 200 ) : array();

		if ( $session ) {
			// Opening the conversation counts as reading the customer's messages.
			Poshtibank_DB::mark_messages_as_read( $session->session_id, 'admin' );
		}

		require_once POSHTIBANK_PATH . 'includes/admin/views/conversation-page.php';
	}

	/*
	|--------------------------------------------------------------------------
	| AJAX Handlers
	|--------------------------------------------------------------------------
	*/

	/**
	 * AJAX Handler: Save all settings tabs.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$raw = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( ! is_array( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'داده‌های ارسالی نامعتبر است.', 'poshtibank' ) ) );
		}

		$current = self::get_settings();
		$clean   = array();

		// 1. General Tab
		$clean['general'] = array(
			'enabled'         => ! empty( $raw['general']['enabled'] ) ? 1 : 0,
			'widget_title'    => isset( $raw['general']['widget_title'] ) ? sanitize_text_field( $raw['general']['widget_title'] ) : $current['general']['widget_title'],
			'widget_subtitle' => isset( $raw['general']['widget_subtitle'] ) ? sanitize_text_field( $raw['general']['widget_subtitle'] ) : $current['general']['widget_subtitle'],
			'retention_days'  => isset( $raw['general']['retention_days'] ) ? max( 0, absint( $raw['general']['retention_days'] ) ) : 30,
			'primary_color'   => isset( $raw['general']['primary_color'] ) ? sanitize_hex_color( $raw['general']['primary_color'] ) : '#2563eb',
			'widget_position' => in_array( $raw['general']['widget_position'] ?? '', array( 'bottom-right', 'bottom-left' ), true ) ? $raw['general']['widget_position'] : 'bottom-right',
			'require_contact' => ! empty( $raw['general']['require_contact'] ) ? 1 : 0,
		);

		// 2. Telegram Tab
		$webhook_secret = ! empty( $current['telegram']['webhook_secret'] ) ? $current['telegram']['webhook_secret'] : Poshtibank_DB::generate_secret( 32 );
		$clean['telegram'] = array(
			'enabled'        => ! empty( $raw['telegram']['enabled'] ) ? 1 : 0,
			'bot_token'      => isset( $raw['telegram']['bot_token'] ) ? sanitize_text_field( trim( $raw['telegram']['bot_token'] ) ) : '',
			'chat_id'        => isset( $raw['telegram']['chat_id'] ) ? sanitize_text_field( trim( $raw['telegram']['chat_id'] ) ) : '',
			'proxy_url'      => isset( $raw['telegram']['proxy_url'] ) ? esc_url_raw( trim( $raw['telegram']['proxy_url'] ) ) : '',
			'use_topics'     => ! empty( $raw['telegram']['use_topics'] ) ? 1 : 0,
			'webhook_secret' => $webhook_secret,
		);

		// 3. Bale Tab
		$bale_secret = ! empty( $current['bale']['webhook_secret'] ) ? $current['bale']['webhook_secret'] : Poshtibank_DB::generate_secret( 32 );
		$clean['bale'] = array(
			'enabled'        => ! empty( $raw['bale']['enabled'] ) ? 1 : 0,
			'bot_token'      => isset( $raw['bale']['bot_token'] ) ? sanitize_text_field( trim( $raw['bale']['bot_token'] ) ) : '',
			'chat_id'        => isset( $raw['bale']['chat_id'] ) ? sanitize_text_field( trim( $raw['bale']['chat_id'] ) ) : '',
			'webhook_secret' => $bale_secret,
		);

		// 4. Business Hours
		$schedule = array();
		$days     = array( 'sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri' );
		foreach ( $days as $day ) {
			$schedule[ $day ] = array(
				'active' => ! empty( $raw['business_hours']['schedule'][ $day ]['active'] ) ? 1 : 0,
				'start'  => isset( $raw['business_hours']['schedule'][ $day ]['start'] ) ? sanitize_text_field( $raw['business_hours']['schedule'][ $day ]['start'] ) : '09:00',
				'end'    => isset( $raw['business_hours']['schedule'][ $day ]['end'] ) ? sanitize_text_field( $raw['business_hours']['schedule'][ $day ]['end'] ) : '18:00',
			);
		}

		$clean['business_hours'] = array(
			'enabled'         => ! empty( $raw['business_hours']['enabled'] ) ? 1 : 0,
			'offline_message' => isset( $raw['business_hours']['offline_message'] ) ? sanitize_textarea_field( $raw['business_hours']['offline_message'] ) : '',
			'schedule'        => $schedule,
		);

		// 5. Communication Hub Channels
		$clean['channels'] = array(
			'telegram_url'  => isset( $raw['channels']['telegram_url'] ) ? esc_url_raw( trim( $raw['channels']['telegram_url'] ) ) : '',
			'whatsapp_url'  => isset( $raw['channels']['whatsapp_url'] ) ? esc_url_raw( trim( $raw['channels']['whatsapp_url'] ) ) : '',
			'bale_url'      => isset( $raw['channels']['bale_url'] ) ? esc_url_raw( trim( $raw['channels']['bale_url'] ) ) : '',
			'eitaa_url'     => isset( $raw['channels']['eitaa_url'] ) ? esc_url_raw( trim( $raw['channels']['eitaa_url'] ) ) : '',
			'instagram_url' => isset( $raw['channels']['instagram_url'] ) ? esc_url_raw( trim( $raw['channels']['instagram_url'] ) ) : '',
			'phone_number'  => isset( $raw['channels']['phone_number'] ) ? sanitize_text_field( trim( $raw['channels']['phone_number'] ) ) : '',
		);

		// 6. AI BYOK Responder
		$clean['ai'] = array(
			'enabled'       => ! empty( $raw['ai']['enabled'] ) ? 1 : 0,
			'provider'      => in_array( $raw['ai']['provider'] ?? '', array( 'gemini', 'openai' ), true ) ? $raw['ai']['provider'] : 'gemini',
			'api_key'       => isset( $raw['ai']['api_key'] ) ? sanitize_text_field( trim( $raw['ai']['api_key'] ) ) : '',
			'endpoint'      => isset( $raw['ai']['endpoint'] ) ? esc_url_raw( trim( $raw['ai']['endpoint'] ) ) : '',
			'model'         => isset( $raw['ai']['model'] ) ? sanitize_text_field( trim( $raw['ai']['model'] ) ) : 'gemini-1.5-flash',
			'system_prompt' => isset( $raw['ai']['system_prompt'] ) ? sanitize_textarea_field( $raw['ai']['system_prompt'] ) : '',
		);

		// 7. Security & Advanced
		$blocked_raw = isset( $raw['security']['blocked_ips'] ) ? $raw['security']['blocked_ips'] : '';
		$blocked_ips = array();
		if ( is_string( $blocked_raw ) ) {
			$lines = explode( "\n", str_replace( "\r", '', $blocked_raw ) );
			foreach ( $lines as $line ) {
				$ip = trim( $line );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$blocked_ips[] = $ip;
				}
			}
		} elseif ( is_array( $blocked_raw ) ) {
			foreach ( $blocked_raw as $ip ) {
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					$blocked_ips[] = $ip;
				}
			}
		}

		$clean['security'] = array(
			'rate_limit_messages' => isset( $raw['security']['rate_limit_messages'] ) ? max( 1, absint( $raw['security']['rate_limit_messages'] ) ) : 10,
			'rate_limit_window'   => isset( $raw['security']['rate_limit_window'] ) ? max( 10, absint( $raw['security']['rate_limit_window'] ) ) : 60,
			'blocked_ips'         => array_values( array_unique( $blocked_ips ) ),
		);

		$clean['advanced'] = array(
			'delete_data_on_uninstall' => ! empty( $raw['advanced']['delete_data_on_uninstall'] ) ? 1 : 0,
		);

		Poshtibank_Settings::update_settings( $clean );

		wp_send_json_success(
			array(
				'message'  => __( 'تنظیمات با موفقیت ذخیره شد.', 'poshtibank' ),
				'settings' => $clean,
			)
		);
	}

	/**
	 * AJAX: Test Telegram Bot connection via getMe.
	 */
	public function ajax_test_telegram() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$token     = isset( $_POST['token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['token'] ) ) ) : '';
		$proxy_url = isset( $_POST['proxy_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['proxy_url'] ) ) ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً توکن ربات تلگرام را وارد کنید.', 'poshtibank' ) ) );
		}

		$base_endpoint = ! empty( $proxy_url ) ? untrailingslashit( $proxy_url ) : 'https://api.telegram.org';
		$url           = "{$base_endpoint}/bot{$token}/getMe";

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf( __( 'خطا در برقراری ارتباط: %s (اگر هاست شما در ایران است، آدرس پروکسی/ورکر کلودفلر را تنظیم کنید).', 'poshtibank' ), $response->get_error_message() ),
				)
			);
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) && ! empty( $body['result'] ) ) {
			$bot = $body['result'];
			wp_send_json_success(
				array(
					'message'  => sprintf( __( 'اتصال به تلگرام با موفقیت برقرار شد! نام ربات: %s (@%s)', 'poshtibank' ), $bot['first_name'], $bot['username'] ),
					'bot_info' => $bot,
				)
			);
		} else {
			$err = isset( $body['description'] ) ? $body['description'] : __( 'توکن نامعتبر است.', 'poshtibank' );
			wp_send_json_error( array( 'message' => __( 'پاسخ ناموفق از تلگرام: ', 'poshtibank' ) . $err ) );
		}
	}

	/**
	 * AJAX: Set Telegram Webhook to point to the site's REST API endpoint.
	 */
	public function ajax_set_telegram_webhook() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$settings  = self::get_settings();
		$token     = isset( $_POST['token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['token'] ) ) ) : $settings['telegram']['bot_token'];
		$proxy_url = isset( $_POST['proxy_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['proxy_url'] ) ) ) : $settings['telegram']['proxy_url'];

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'توکن ربات تلگرام مشخص نشده است.', 'poshtibank' ) ) );
		}

		$webhook_url = rest_url( 'poshtibank/v1/webhook/telegram' );

		if ( ! is_ssl() && strpos( $webhook_url, 'https://' ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'وبهوک تلگرام نیازمند پروتکل امن HTTPS است. لطفاً ابتدا SSL سایت را فعال کنید.', 'poshtibank' ) ) );
		}

		$base_endpoint = ! empty( $proxy_url ) ? untrailingslashit( $proxy_url ) : 'https://api.telegram.org';
		$api_url       = "{$base_endpoint}/bot{$token}/setWebhook";

		$payload = array(
			'url'             => $webhook_url,
			'secret_token'    => $settings['telegram']['webhook_secret'],
			'allowed_updates' => array( 'message', 'edited_message', 'callback_query' ),
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout'   => 15,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) ) {
			wp_send_json_success(
				array(
					'message'     => __( 'وبهوک تلگرام با موفقیت ثبت شد!', 'poshtibank' ),
					'webhook_url' => $webhook_url,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'خطا در ثبت وبهوک تلگرام: ', 'poshtibank' ) . ( $body['description'] ?? '' ),
				)
			);
		}
	}

	/**
	 * AJAX: Delete Telegram Webhook.
	 */
	public function ajax_delete_telegram_webhook() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$settings      = self::get_settings();
		$token         = $settings['telegram']['bot_token'];
		$proxy_url     = $settings['telegram']['proxy_url'];
		$base_endpoint = ! empty( $proxy_url ) ? untrailingslashit( $proxy_url ) : 'https://api.telegram.org';
		$api_url       = "{$base_endpoint}/bot{$token}/deleteWebhook";

		$response = wp_remote_post( $api_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) ) {
			wp_send_json_success( array( 'message' => __( 'وبهوک تلگرام با موفقیت حذف گردید.', 'poshtibank' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $body['description'] ?? __( 'خطا در حذف وبهوک.', 'poshtibank' ) ) );
		}
	}

	/**
	 * AJAX: Test Bale Messenger Bot connection.
	 */
	public function ajax_test_bale() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$token = isset( $_POST['token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['token'] ) ) ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'لطفاً توکن ربات بله را وارد کنید.', 'poshtibank' ) ) );
		}

		$url = "https://tapi.bale.ai/bot{$token}/getMe";

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'خطا در ارتباط با سرور بله: %s', 'poshtibank' ), $response->get_error_message() ) ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) && ! empty( $body['result'] ) ) {
			$bot = $body['result'];
			wp_send_json_success(
				array(
					'message'  => sprintf( __( 'اتصال به پیام‌رسان بله با موفقیت انجام شد! ربات: %s (@%s)', 'poshtibank' ), $bot['first_name'] ?? '', $bot['username'] ?? '' ),
					'bot_info' => $bot,
				)
			);
		} else {
			$err = isset( $body['description'] ) ? $body['description'] : __( 'توکن بله نامعتبر است.', 'poshtibank' );
			wp_send_json_error( array( 'message' => __( 'پاسخ ناموفق از بله: ', 'poshtibank' ) . $err ) );
		}
	}

	/**
	 * AJAX: Set Bale Webhook.
	 */
	public function ajax_set_bale_webhook() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$settings = self::get_settings();
		$token    = isset( $_POST['token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['token'] ) ) ) : $settings['bale']['bot_token'];

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => __( 'توکن ربات بله مشخص نشده است.', 'poshtibank' ) ) );
		}

		$webhook_url = rest_url( 'poshtibank/v1/webhook/bale' );

		if ( ! is_ssl() && strpos( $webhook_url, 'https://' ) !== 0 ) {
			wp_send_json_error( array( 'message' => __( 'ثبت وبهوک بله نیازمند HTTPS است.', 'poshtibank' ) ) );
		}

		$api_url = "https://tapi.bale.ai/bot{$token}/setWebhook";
		$payload = array(
			'url' => $webhook_url,
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'timeout'   => 15,
				'headers'   => array( 'Content-Type' => 'application/json' ),
				'body'      => wp_json_encode( $payload ),
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) ) {
			wp_send_json_success(
				array(
					'message'     => __( 'وبهوک پیام‌رسان بله با موفقیت ثبت شد!', 'poshtibank' ),
					'webhook_url' => $webhook_url,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'خطا در ثبت وبهوک بله: ', 'poshtibank' ) . ( $body['description'] ?? '' ),
				)
			);
		}
	}

	/**
	 * AJAX: Delete Bale Webhook.
	 */
	public function ajax_delete_bale_webhook() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$settings = self::get_settings();
		$token    = $settings['bale']['bot_token'];
		$api_url  = "https://tapi.bale.ai/bot{$token}/deleteWebhook";

		$response = wp_remote_post( $api_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['ok'] ) ) {
			wp_send_json_success( array( 'message' => __( 'وبهوک بله با موفقیت غیرفعال شد.', 'poshtibank' ) ) );
		} else {
			wp_send_json_error( array( 'message' => $body['description'] ?? __( 'خطا در حذف وبهوک بله.', 'poshtibank' ) ) );
		}
	}

	/**
	 * AJAX: Test AI API key (Gemini or OpenAI).
	 */
	public function ajax_test_ai() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : 'gemini';
		$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['api_key'] ) ) ) : '';
		$model    = isset( $_POST['model'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['model'] ) ) ) : '';
		$endpoint = isset( $_POST['endpoint'] ) ? esc_url_raw( trim( wp_unslash( $_POST['endpoint'] ) ) ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'کلید API وارد نشده است.', 'poshtibank' ) ) );
		}

		$provider = in_array( $provider, array( 'gemini', 'openai' ), true ) ? $provider : 'gemini';
		if ( '' === $model ) {
			$model = 'gemini' === $provider ? 'gemini-1.5-flash' : 'gpt-4o-mini';
		}

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';

		$url     = Poshtibank_AI::build_endpoint( $provider, $endpoint, $model, $api_key );
		$headers = array( 'Content-Type' => 'application/json' );

		if ( 'gemini' === $provider ) {
			$payload = array(
				'contents' => array(
					array(
						'parts' => array(
							array( 'text' => 'Hello. Reply with only one word: OK' ),
						),
					),
				),
			);
		} else {
			$payload = array(
				'model'    => $model,
				'messages' => array(
					array(
						'role'    => 'user',
						'content' => 'Hello. Reply with only one word: OK',
					),
				),
			);

			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 15,
				'headers' => $headers,
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => sprintf( __( 'خطا در ارتباط با سرویس هوش مصنوعی: %s', 'poshtibank' ), $response->get_error_message() ) ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 200 && $code < 300 ) {
			wp_send_json_success( array( 'message' => __( 'کلید API هوش مصنوعی معتبر است و ارتباط با موفقیت برقرار شد!', 'poshtibank' ) ) );
		} else {
			$err = $body['error']['message'] ?? __( 'کلید API نامعتبر است یا سهمیه تمام شده است.', 'poshtibank' );
			wp_send_json_error( array( 'message' => __( 'خطا از سرویس‌دهنده هوش مصنوعی: ', 'poshtibank' ) . $err ) );
		}
	}

	/**
	 * Capability required to read and answer conversations.
	 *
	 * Defaults to site administrators but can be widened (for example to shop
	 * managers) through the `poshtibank_chat_capability` filter.
	 *
	 * @return string
	 */
	protected function chat_capability() {
		return (string) apply_filters( 'poshtibank_chat_capability', 'manage_options' );
	}

	/**
	 * Whether the current user may manage conversations.
	 *
	 * @return bool
	 */
	protected function can_manage_chats() {
		return current_user_can( $this->chat_capability() );
	}

	/**
	 * Handle a conversation status change submitted from the dashboard.
	 *
	 * The session identifier travels inside the submitted form body and is
	 * validated against the database before anything is written, so a missing
	 * or malformed identifier can never reach the update query.
	 */
	public function handle_update_session_status() {
		if ( ! $this->can_manage_chats() ) {
			wp_die(
				esc_html__( 'شما اجازه انجام این عملیات را ندارید.', 'poshtibank' ),
				esc_html__( 'دسترسی غیرمجاز', 'poshtibank' ),
				array( 'response' => 403 )
			);
		}

		check_admin_referer( 'poshtibank_update_session_status' );

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$status     = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$redirect   = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : '';

		if ( empty( $redirect ) ) {
			$redirect = admin_url( 'admin.php?page=poshtibank-chats' );
		}

		if ( ! in_array( $status, array( 'open', 'closed', 'blocked' ), true ) ) {
			$this->redirect_with_notice( $redirect, 'invalid_status' );
		}

		$session = empty( $session_id ) ? null : Poshtibank_DB::get_session( $session_id );

		if ( ! $session ) {
			$this->redirect_with_notice( $redirect, 'session_not_found' );
		}

		Poshtibank_DB::update_session( $session->session_id, array( 'status' => $status ) );

		$this->redirect_with_notice( $redirect, 'status_updated' );
	}

	/**
	 * Redirect back to a dashboard screen with a notice flag attached.
	 *
	 * @param string $url    Destination URL.
	 * @param string $notice Notice key understood by render_admin_notices().
	 */
	protected function redirect_with_notice( $url, $notice ) {
		wp_safe_redirect( add_query_arg( 'poshtibank_notice', $notice, $url ) );
		exit;
	}

	/**
	 * Render status notices for the conversations screens.
	 */
	public function render_admin_notices() {
		if ( empty( $_GET['poshtibank_notice'] ) || ! is_string( $_GET['poshtibank_notice'] ) ) {
			return;
		}

		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'poshtibank-chats' !== $page ) {
			return;
		}

		$notice = sanitize_key( wp_unslash( $_GET['poshtibank_notice'] ) );

		$messages = array(
			'status_updated'    => array( 'success', __( 'وضعیت گفتگو با موفقیت به‌روزرسانی شد.', 'poshtibank' ) ),
			'invalid_status'    => array( 'error', __( 'وضعیت درخواستی نامعتبر است.', 'poshtibank' ) ),
			'session_not_found' => array( 'error', __( 'گفتگوی مورد نظر یافت نشد. ممکن است حذف شده باشد.', 'poshtibank' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		list( $type, $text ) = $messages[ $notice ];

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $text )
		);
	}

	/**
	 * Warn when an integration is switched on but cannot work.
	 *
	 * The connection tests validate what is currently typed into the form, so
	 * a configuration that was never saved still passes them. These notices
	 * read the saved option instead, which is what the site actually runs on.
	 */
	public function render_configuration_notices() {
		$page = isset( $_GET['page'] ) && is_string( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 'poshtibank' !== $page ) {
			return;
		}

		$settings = Poshtibank_Settings::get_settings();
		$warnings = array();

		if ( ! empty( $settings['ai']['enabled'] ) && empty( $settings['ai']['api_key'] ) ) {
			$warnings[] = __( 'پاسخگوی هوش مصنوعی فعال است اما کلید API ذخیره نشده؛ به همین دلیل گزینه «چت سریع با هوش مصنوعی» در سایت نمایش داده نمی‌شود.', 'poshtibank' );
		}

		if ( ! empty( $settings['telegram']['enabled'] ) && ( empty( $settings['telegram']['bot_token'] ) || empty( $settings['telegram']['chat_id'] ) ) ) {
			$warnings[] = __( 'اتصال تلگرام فعال است اما توکن ربات یا شناسه چت ذخیره نشده؛ پیام‌های سایت به تلگرام نمی‌رسند.', 'poshtibank' );
		}

		if ( ! empty( $settings['bale']['enabled'] ) && ( empty( $settings['bale']['bot_token'] ) || empty( $settings['bale']['chat_id'] ) ) ) {
			$warnings[] = __( 'اتصال بله فعال است اما توکن ربات یا شناسه چت ذخیره نشده؛ پیام‌های سایت به بله نمی‌رسند.', 'poshtibank' );
		}

		if ( empty( $warnings ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'تنظیمات ناقص', 'poshtibank' ) . '</strong></p><ul style="margin: 6px 0; list-style: disc; padding-inline-start: 22px;">';
		foreach ( $warnings as $warning ) {
			echo '<li>' . esc_html( $warning ) . '</li>';
		}
		echo '</ul><p>' . esc_html__( 'این هشدارها از تنظیمات ذخیره‌شده خوانده می‌شوند. تست‌های اتصال فقط مقدار فرم را بررسی می‌کنند، پس تا وقتی دکمه «ذخیره تنظیمات» زده نشود، سایت همان تنظیمات قبلی را اجرا می‌کند.', 'poshtibank' ) . '</p></div>';
	}

	/**
	 * AJAX: Send a reply from a dashboard administrator to the customer.
	 */
	public function ajax_send_admin_reply() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! $this->can_manage_chats() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ), 403 );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$content    = isset( $_POST['message'] ) ? trim( wp_kses_post( wp_unslash( $_POST['message'] ) ) ) : '';

		if ( '' === $session_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه گفتگو نامعتبر است.', 'poshtibank' ) ) );
		}

		if ( '' === $content ) {
			wp_send_json_error( array( 'message' => __( 'متن پاسخ نمی‌تواند خالی باشد.', 'poshtibank' ) ) );
		}

		$session = Poshtibank_DB::get_session( $session_id );

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'گفتگوی مورد نظر یافت نشد.', 'poshtibank' ) ) );
		}

		if ( 'blocked' === $session->status ) {
			wp_send_json_error( array( 'message' => __( 'این گفتگو مسدود شده است. ابتدا مسدودی را رفع کنید.', 'poshtibank' ) ) );
		}

		$current_user = wp_get_current_user();

		$message_id = Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'admin',
				'sender_id'   => (string) $current_user->ID,
				'sender_name' => ! empty( $current_user->display_name ) ? $current_user->display_name : __( 'پشتیبان', 'poshtibank' ),
				'type'        => 'text',
				'content'     => $content,
			)
		);

		if ( ! $message_id ) {
			wp_send_json_error( array( 'message' => __( 'خطا در ثبت پاسخ. لطفاً مجدداً تلاش کنید.', 'poshtibank' ) ) );
		}

		// Answering a closed conversation brings it back to life.
		if ( 'closed' === $session->status ) {
			Poshtibank_DB::update_session( $session->session_id, array( 'status' => 'open' ) );
		}

		// The operator has now read everything the customer sent.
		Poshtibank_DB::mark_messages_as_read( $session->session_id, 'admin' );

		$message = Poshtibank_DB::get_message( $message_id );

		wp_send_json_success(
			array(
				'message' => array(
					'id'          => (int) $message->id,
					'message_id'  => $message->message_id,
					'sender'      => $message->sender,
					'sender_name' => $message->sender_name,
					'type'        => $message->type,
					'content'     => $message->content,
					'time'        => mysql2date( 'Y/m/d H:i', $message->created_at ),
				),
				'status'  => 'closed' === $session->status ? 'open' : $session->status,
			)
		);
	}

	/**
	 * AJAX: Return messages that arrived after the last one on screen.
	 */
	public function ajax_poll_conversation() {
		check_ajax_referer( 'poshtibank_admin_action', 'nonce' );

		if ( ! $this->can_manage_chats() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی غیرمجاز.', 'poshtibank' ) ), 403 );
		}

		$session_id = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
		$after_id   = isset( $_POST['after_id'] ) ? absint( $_POST['after_id'] ) : 0;

		$session = empty( $session_id ) ? null : Poshtibank_DB::get_session( $session_id );

		if ( ! $session ) {
			wp_send_json_error( array( 'message' => __( 'گفتگوی مورد نظر یافت نشد.', 'poshtibank' ) ) );
		}

		$messages = Poshtibank_DB::get_new_messages_since( $session->session_id, $after_id, 'admin' );

		if ( ! empty( $messages ) ) {
			Poshtibank_DB::mark_messages_as_read( $session->session_id, 'admin' );
		}

		$payload = array();

		foreach ( $messages as $message ) {
			$payload[] = array(
				'id'          => (int) $message->id,
				'message_id'  => $message->message_id,
				'sender'      => $message->sender,
				'sender_name' => $message->sender_name,
				'type'        => $message->type,
				'content'     => $message->content,
				'time'        => mysql2date( 'Y/m/d H:i', $message->created_at ),
			);
		}

		wp_send_json_success(
			array(
				'messages' => $payload,
				'status'   => $session->status,
			)
		);
	}
}
