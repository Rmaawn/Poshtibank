<?php
/**
 * Global Settings and Configuration Manager for Poshtibank.
 *
 * Provides a unified, single source of truth for plugin settings across Admin,
 * Frontend, REST API, Webhooks, and Activator.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Settings
 */
class Poshtibank_Settings {

	/**
	 * WordPress option key for plugin settings.
	 */
	const OPTION_KEY = 'poshtibank_settings';

	/**
	 * Cached settings in memory for current request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Retrieve default options structure.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'general' => array(
				'enabled'         => 1,
				'widget_title'    => 'پشتیبانی آنلاین پشتیبانک',
				'widget_subtitle' => 'معمولاً در کمتر از چند دقیقه پاسخگوییم',
				'retention_days'  => 30,
				'primary_color'   => '#2563eb',
				'widget_position' => 'bottom-right',
				'require_contact' => 0,
			),
			'telegram' => array(
				'enabled'        => 0,
				'bot_token'      => '',
				'chat_id'        => '',
				'proxy_url'      => '',
				'use_topics'     => 1,
				'webhook_secret' => Poshtibank_DB::generate_secret( 32 ),
			),
			'bale' => array(
				'enabled'        => 0,
				'bot_token'      => '',
				'chat_id'        => '',
				'webhook_secret' => Poshtibank_DB::generate_secret( 32 ),
			),
			'business_hours' => array(
				'enabled'         => 0,
				'offline_message' => 'در حال حاضر همکاران ما آفلاین هستند. لطفاً پیام و شماره تماس خود را ارسال کنید.',
				'schedule'        => array(
					'sat' => array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' ),
					'sun' => array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' ),
					'mon' => array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' ),
					'tue' => array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' ),
					'wed' => array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' ),
					'thu' => array( 'active' => 1, 'start' => '09:00', 'end' => '14:00' ),
					'fri' => array( 'active' => 0, 'start' => '00:00', 'end' => '00:00' ),
				),
			),
			'channels' => array(
				'telegram_url'  => '',
				'whatsapp_url'  => '',
				'bale_url'      => '',
				'eitaa_url'     => '',
				'instagram_url' => '',
				'phone_number'  => '',
			),
			'ai' => array(
				'enabled'       => 0,
				'provider'      => 'gemini',
				'api_key'       => '',
				'model'         => 'gemini-1.5-flash',
				'system_prompt' => 'شما دستیار هوشمند و مودب پشتیبانی سایت هستید. به سوالات کاربران با دقت و صبوری پاسخ دهید.',
			),
			'security' => array(
				'rate_limit_messages' => 10,
				'rate_limit_window'   => 60,
				'blocked_ips'         => array(),
			),
			'advanced' => array(
				'delete_data_on_uninstall' => 0,
			),
		);
	}

	/**
	 * Retrieve all stored settings with default fallbacks.
	 *
	 * @param bool $refresh Force cache refresh from database.
	 * @return array
	 */
	public static function get_settings( $refresh = false ) {
		if ( null !== self::$cache && ! $refresh ) {
			return self::$cache;
		}

		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$defaults = self::get_defaults();

		// Deep merge sections to preserve nested defaults
		$merged = array();
		foreach ( $defaults as $section => $fields ) {
			if ( isset( $saved[ $section ] ) && is_array( $saved[ $section ] ) ) {
				$merged[ $section ] = wp_parse_args( $saved[ $section ], $fields );
			} else {
				$merged[ $section ] = $fields;
			}
		}

		// Also retain any extra keys that might exist in saved
		foreach ( $saved as $section => $fields ) {
			if ( ! isset( $merged[ $section ] ) ) {
				$merged[ $section ] = $fields;
			}
		}

		self::$cache = $merged;
		return self::$cache;
	}

	/**
	 * Retrieve a specific section or key from settings.
	 *
	 * @param string      $section Top-level settings section.
	 * @param string|null $key     Specific key inside section (optional).
	 * @param mixed       $default Default fallback value.
	 * @return mixed
	 */
	public static function get( $section, $key = null, $default = null ) {
		$settings = self::get_settings();

		if ( ! isset( $settings[ $section ] ) ) {
			return $default;
		}

		if ( null === $key ) {
			return $settings[ $section ];
		}

		if ( is_array( $settings[ $section ] ) && array_key_exists( $key, $settings[ $section ] ) ) {
			return $settings[ $section ][ $key ];
		}

		return $default;
	}

	/**
	 * Update settings and refresh internal cache.
	 *
	 * @param array $new_settings Complete or partial settings array.
	 * @return bool
	 */
	public static function update_settings( array $new_settings ) {
		$result      = update_option( self::OPTION_KEY, $new_settings );
		self::$cache = null;
		return $result;
	}

	/**
	 * Reset internal memory cache.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}
}
