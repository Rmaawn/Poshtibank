<?php
/**
 * Telegram Messenger Bridge implementation.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/bridges/interface-poshtibank-bridge.php';

/**
 * Class Poshtibank_Bridge_Telegram
 */
class Poshtibank_Bridge_Telegram implements Poshtibank_Bridge_Interface {

	/**
	 * Bot token.
	 *
	 * @var string
	 */
	protected $token = '';

	/**
	 * Custom reverse proxy or Cloudflare worker endpoint.
	 *
	 * @var string
	 */
	protected $proxy_url = '';

	/**
	 * Default chat or supergroup ID.
	 *
	 * @var string
	 */
	protected $chat_id = '';

	/**
	 * Whether forum topics are enabled.
	 *
	 * @var bool
	 */
	protected $use_topics = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings = get_option( 'poshtibank_settings', array() );
		$tg       = $settings['telegram'] ?? array();

		$this->token      = trim( $tg['bot_token'] ?? '' );
		$this->proxy_url  = trim( $tg['proxy_url'] ?? '' );
		$this->chat_id    = trim( $tg['chat_id'] ?? '' );
		$this->use_topics = ! empty( $tg['use_topics'] );
	}

	/**
	 * Check if Telegram bridge is enabled and has minimal required configuration.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$settings = get_option( 'poshtibank_settings', array() );
		$enabled  = ! empty( $settings['telegram']['enabled'] );
		return $enabled && ! empty( $this->token ) && ! empty( $this->chat_id );
	}

	/**
	 * Get default Chat ID.
	 *
	 * @return string
	 */
	public function get_chat_id() {
		return $this->chat_id;
	}

	/**
	 * Whether topics are enabled.
	 *
	 * @return bool
	 */
	public function uses_topics() {
		return $this->use_topics;
	}

	/**
	 * Send text message.
	 *
	 * @param string $chat_id Chat ID or supergroup ID.
	 * @param string $text    Message content.
	 * @param array  $extra   Additional parameters.
	 * @return array|false
	 */
	public function send_text( $chat_id, $text, array $extra = array() ) {
		$params = array(
			'chat_id'    => $chat_id,
			'text'       => $text,
			'parse_mode' => 'HTML',
		);

		return $this->request( 'sendMessage', array_merge( $params, $extra ) );
	}

	/**
	 * Send voice audio.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $voice_url Public URL of voice.
	 * @param string $caption   Optional caption.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_voice( $chat_id, $voice_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id'    => $chat_id,
			'voice'      => $voice_url,
			'caption'    => $caption,
			'parse_mode' => 'HTML',
		);

		return $this->request( 'sendVoice', array_merge( $params, $extra ) );
	}

	/**
	 * Send photo / screenshot.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $photo_url Public URL of photo.
	 * @param string $caption   Optional caption.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_photo( $chat_id, $photo_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id'    => $chat_id,
			'photo'      => $photo_url,
			'caption'    => $caption,
			'parse_mode' => 'HTML',
		);

		return $this->request( 'sendPhoto', array_merge( $params, $extra ) );
	}

	/**
	 * Send document / file.
	 *
	 * @param string $chat_id  Chat ID.
	 * @param string $doc_url  Public URL of document.
	 * @param string $caption  Optional caption.
	 * @param array  $extra    Additional parameters.
	 * @return array|false
	 */
	public function send_document( $chat_id, $doc_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id'    => $chat_id,
			'document'   => $doc_url,
			'caption'    => $caption,
			'parse_mode' => 'HTML',
		);

		return $this->request( 'sendDocument', array_merge( $params, $extra ) );
	}

	/**
	 * Create a forum topic in a supergroup.
	 *
	 * @param string $chat_id Supergroup Chat ID.
	 * @param string $name    Topic title.
	 * @param array  $extra   Additional parameters.
	 * @return int|false
	 */
	public function create_forum_topic( $chat_id, $name, array $extra = array() ) {
		$params = array(
			'chat_id' => $chat_id,
			'name'    => mb_substr( $name, 0, 128 ),
		);

		$res = $this->request( 'createForumTopic', array_merge( $params, $extra ) );

		if ( ! empty( $res['ok'] ) && ! empty( $res['result']['message_thread_id'] ) ) {
			return intval( $res['result']['message_thread_id'] );
		}

		return false;
	}

	/**
	 * Close a forum topic.
	 *
	 * @param string $chat_id  Supergroup Chat ID.
	 * @param int    $topic_id Topic ID.
	 * @return bool
	 */
	public function close_forum_topic( $chat_id, $topic_id ) {
		$res = $this->request(
			'closeForumTopic',
			array(
				'chat_id'           => $chat_id,
				'message_thread_id' => intval( $topic_id ),
			)
		);

		return ! empty( $res['ok'] );
	}

	/**
	 * Answer an inline callback query.
	 *
	 * @param string $callback_query_id Callback query ID.
	 * @param string $text              Alert message.
	 * @param bool   $show_alert        Whether to show alert modal.
	 * @return bool
	 */
	public function answer_callback_query( $callback_query_id, $text = '', $show_alert = false ) {
		$res = $this->request(
			'answerCallbackQuery',
			array(
				'callback_query_id' => $callback_query_id,
				'text'              => $text,
				'show_alert'        => (bool) $show_alert,
			)
		);

		return ! empty( $res['ok'] );
	}

	/**
	 * Register Webhook.
	 *
	 * @param string $url          Webhook URL.
	 * @param string $secret_token Secret verification token.
	 * @return array|false
	 */
	public function set_webhook( $url, $secret_token = '' ) {
		$params = array(
			'url'             => $url,
			'allowed_updates' => array( 'message', 'edited_message', 'callback_query' ),
		);

		if ( ! empty( $secret_token ) ) {
			$params['secret_token'] = $secret_token;
		}

		return $this->request( 'setWebhook', $params );
	}

	/**
	 * Delete Webhook.
	 *
	 * @return bool
	 */
	public function delete_webhook() {
		$res = $this->request( 'deleteWebhook' );
		return ! empty( $res['ok'] );
	}

	/**
	 * Get bot info.
	 *
	 * @return array|false
	 */
	public function get_me() {
		return $this->request( 'getMe', array(), 'GET' );
	}

	/**
	 * Generate an Inline Keyboard for a specific chat session.
	 *
	 * @param string $session_id UUID of the session.
	 * @return array
	 */
	public static function make_session_inline_keyboard( $session_id ) {
		return array(
			'inline_keyboard' => array(
				array(
					array(
						'text'          => '⛔ مسدودسازی کاربر',
						'callback_data' => 'pb_block:' . $session_id,
					),
					array(
						'text'          => 'ℹ️ اطلاعات کاربر',
						'callback_data' => 'pb_info:' . $session_id,
					),
				),
				array(
					array(
						'text'          => '🔒 بستن گفتگو',
						'callback_data' => 'pb_close:' . $session_id,
					),
				),
			),
		);
	}

	/**
	 * Execute HTTP request to Telegram API.
	 *
	 * @param string $method      Telegram API method name.
	 * @param array  $params      Parameters payload.
	 * @param string $http_method 'POST' or 'GET'.
	 * @return array|false
	 */
	protected function request( $method, array $params = array(), $http_method = 'POST' ) {
		if ( empty( $this->token ) ) {
			return false;
		}

		$base = ! empty( $this->proxy_url ) ? untrailingslashit( $this->proxy_url ) : 'https://api.telegram.org';
		$url  = "{$base}/bot{$this->token}/{$method}";

		$args = array(
			'timeout'   => 20,
			'sslverify' => true,
		);

		if ( 'GET' === strtoupper( $http_method ) ) {
			if ( ! empty( $params ) ) {
				$url = add_query_arg( $params, $url );
			}
			$response = wp_remote_get( $url, $args );
		} else {
			$args['headers'] = array( 'Content-Type' => 'application/json' );
			$args['body']    = wp_json_encode( $params );
			$response        = wp_remote_post( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			error_log( '[Poshtibank Telegram Error] ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : false;
	}
}
