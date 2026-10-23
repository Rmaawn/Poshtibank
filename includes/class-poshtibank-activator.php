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
		$existing = get_option( 'poshtibank_settings', false );

		if ( false === $existing ) {
			$defaults = array(
				'general' => array(
					'enabled'         => 1,
					'widget_title'    => 'پشتیبانی آنلاین پشتیبانک',
					'widget_subtitle' => 'معمولاً در کمتر از چند دقیقه پاسخگوییم',
					'retention_days'  => 30,
					'primary_color'   => '#2563eb',
					'widget_position' => 'bottom-right',
					'require_contact' => 0, // 0: guest can chat immediately, 1: pre-chat form
				),
				'telegram' => array(
					'enabled'        => 0,
					'bot_token'      => '',
					'chat_id'        => '',
					'proxy_url'      => '', // Cloudflare worker reverse proxy
					'use_topics'     => 1,  // Multi-operator forum topics
					'webhook_secret' => Poshtibank_DB::generate_secret( 32 ),
				),
				'bale' => array(
					'enabled'        => 0,
					'bot_token'      => '',
					'chat_id'        => '',
					'webhook_secret' => Poshtibank_DB::generate_secret( 32 ),
				),
				'security' => array(
					'rate_limit_messages' => 10, // Max 10 messages per minute
					'rate_limit_window'   => 60, // Window in seconds
					'blocked_ips'         => array(),
				),
				'business_hours' => array(
					'enabled'         => 0,
					'offline_message' => 'در حال حاضر همکاران ما آفلاین هستند. لطفاً پیام و شماره تماس خود را ارسال کنید.',
					'schedule'        => array(),
				),
				'ai' => array(
					'enabled'       => 0,
					'provider'      => 'gemini',
					'api_key'       => '',
					'system_prompt' => 'شما دستیار هوشمند و مودب پشتیبانی سایت هستید. به سوالات کاربران با دقت و صبوری پاسخ دهید.',
				),
				'channels' => array(
					'telegram_url'  => '',
					'whatsapp_url'  => '',
					'bale_url'      => '',
					'eitaa_url'     => '',
					'instagram_url' => '',
					'phone_number'  => '',
				),
				'advanced' => array(
					'delete_data_on_uninstall' => 0,
				),
			);

			add_option( 'poshtibank_settings', $defaults );
		}
	}
}
