<?php
/**
 * Bale Messenger Bridge implementation.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/bridges/interface-poshtibank-bridge.php';

/**
 * Class Poshtibank_Bridge_Bale
 */
class Poshtibank_Bridge_Bale implements Poshtibank_Bridge_Interface {

	/**
	 * Bot token.
	 *
	 * @var string
	 */
	protected $token = '';

	/**
	 * Chat ID in Bale.
	 *
	 * @var string
	 */
	protected $chat_id = '';

	/**
	 * Bale API Base URL.
	 */
	const BASE_URL = 'https://tapi.bale.ai';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$bale = Poshtibank_Settings::get( 'bale', null, array() );

		$this->token   = trim( $bale['bot_token'] ?? '' );
		$this->chat_id = trim( $bale['chat_id'] ?? '' );
	}

	/**
	 * Check if Bale bridge is enabled and configured.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		$enabled = Poshtibank_Settings::get( 'bale', 'enabled' );
		return ! empty( $enabled ) && ! empty( $this->token ) && ! empty( $this->chat_id );
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
	 * Send text message.
	 *
	 * @param string $chat_id Chat ID.
	 * @param string $text    Message content.
	 * @param array  $extra   Additional parameters.
	 * @return array|false
	 */
	public function send_text( $chat_id, $text, array $extra = array() ) {
		$params = array(
			'chat_id' => $chat_id,
			'text'    => $text,
		);

		return $this->request( 'sendMessage', array_merge( $params, $extra ) );
	}

	/**
	 * Send voice message.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $voice_url Public URL of voice.
	 * @param string $caption   Optional caption.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_voice( $chat_id, $voice_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id' => $chat_id,
			'voice'   => $voice_url,
			'caption' => $caption,
		);

		return $this->request( 'sendVoice', array_merge( $params, $extra ) );
	}

	/**
	 * Send photo.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $photo_url Public URL of photo.
	 * @param string $caption   Optional caption.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_photo( $chat_id, $photo_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id' => $chat_id,
			'photo'   => $photo_url,
			'caption' => $caption,
		);

		return $this->request( 'sendPhoto', array_merge( $params, $extra ) );
	}

	/**
	 * Send document.
	 *
	 * @param string $chat_id  Chat ID.
	 * @param string $doc_url  Public URL of document.
	 * @param string $caption  Optional caption.
	 * @param array  $extra    Additional parameters.
	 * @return array|false
	 */
	public function send_document( $chat_id, $doc_url, $caption = '', array $extra = array() ) {
		$params = array(
			'chat_id'  => $chat_id,
			'document' => $doc_url,
			'caption'  => $caption,
		);

		return $this->request( 'sendDocument', array_merge( $params, $extra ) );
	}

	/**
	 * Bale does not currently support forum topics like Telegram supergroups.
	 *
	 * @param string $chat_id Chat ID.
	 * @param string $name    Title.
	 * @param array  $extra   Extra options.
	 * @return false
	 */
	public function create_forum_topic( $chat_id, $name, array $extra = array() ) {
		return false;
	}

	/**
	 * Close forum topic (not supported in Bale).
	 *
	 * @param string $chat_id  Chat ID.
	 * @param int    $topic_id Topic ID.
	 * @return bool
	 */
	public function close_forum_topic( $chat_id, $topic_id ) {
		return false;
	}

	/**
	 * Answer callback query on inline button click.
	 *
	 * @param string $callback_query_id Callback query ID.
	 * @param string $text              Alert message.
	 * @param bool   $show_alert        Whether to show alert.
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
		return $this->request( 'setWebhook', array( 'url' => $url ) );
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
	 * Generate an Inline Keyboard for Bale.
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
	 * Execute HTTP request to Bale Bot API.
	 *
	 * @param string $method      API method name.
	 * @param array  $params      Payload.
	 * @param string $http_method 'POST' or 'GET'.
	 * @return array|false
	 */
	protected function request( $method, array $params = array(), $http_method = 'POST' ) {
		if ( empty( $this->token ) ) {
			return false;
		}

		$url = self::BASE_URL . "/bot{$this->token}/{$method}";

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
			error_log( '[Poshtibank Bale Error] ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( is_array( $body ) && empty( $body['ok'] ) && ! empty( $body['description'] ) ) {
			error_log( '[Poshtibank Bale API Error] ' . $method . ': ' . $body['description'] );
		}
		return is_array( $body ) ? $body : false;
	}
}
