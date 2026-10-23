<?php
/**
 * Bridge Interface for Poshtibank Messenger Integrations.
 *
 * Defines the contract for all messenger bridges (Telegram, Bale, etc.)
 * using the Strategy Pattern.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Poshtibank_Bridge_Interface {

	/**
	 * Check if this bridge is currently active and configured.
	 *
	 * @return bool
	 */
	public function is_enabled();

	/**
	 * Send a text message to a chat/channel.
	 *
	 * @param string $chat_id Chat ID or supergroup ID.
	 * @param string $text    Message content (HTML or plain text).
	 * @param array  $extra   Additional parameters (reply_markup, message_thread_id, etc.).
	 * @return array|false Response array on success, false on failure.
	 */
	public function send_text( $chat_id, $text, array $extra = array() );

	/**
	 * Send an audio/voice message.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $voice_url Public URL of the audio/voice file.
	 * @param string $caption   Optional caption text.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_voice( $chat_id, $voice_url, $caption = '', array $extra = array() );

	/**
	 * Send a photo/screenshot.
	 *
	 * @param string $chat_id   Chat ID.
	 * @param string $photo_url Public URL of the image.
	 * @param string $caption   Optional caption text.
	 * @param array  $extra     Additional parameters.
	 * @return array|false
	 */
	public function send_photo( $chat_id, $photo_url, $caption = '', array $extra = array() );

	/**
	 * Send a generic document/file.
	 *
	 * @param string $chat_id  Chat ID.
	 * @param string $doc_url  Public URL of the document.
	 * @param string $caption  Optional caption text.
	 * @param array  $extra    Additional parameters.
	 * @return array|false
	 */
	public function send_document( $chat_id, $doc_url, $caption = '', array $extra = array() );

	/**
	 * Create a forum topic in a supergroup (for multi-operator management).
	 *
	 * @param string $chat_id Chat ID of the supergroup.
	 * @param string $name    Name of the topic (e.g. user name or session ID).
	 * @param array  $extra   Additional parameters (icon_color, etc.).
	 * @return int|false The created message_thread_id or false.
	 */
	public function create_forum_topic( $chat_id, $name, array $extra = array() );

	/**
	 * Close a forum topic.
	 *
	 * @param string $chat_id  Chat ID of the supergroup.
	 * @param int    $topic_id The message_thread_id to close.
	 * @return bool
	 */
	public function close_forum_topic( $chat_id, $topic_id );

	/**
	 * Answer an inline callback query.
	 *
	 * @param string $callback_query_id Unique ID of the callback query.
	 * @param string $text              Text notification for user.
	 * @param bool   $show_alert        Whether to show an alert modal or top toast.
	 * @return bool
	 */
	public function answer_callback_query( $callback_query_id, $text = '', $show_alert = false );

	/**
	 * Register a webhook URL.
	 *
	 * @param string $url          Webhook endpoint URL.
	 * @param string $secret_token Optional secret token for verification.
	 * @return array|false
	 */
	public function set_webhook( $url, $secret_token = '' );

	/**
	 * Delete current webhook.
	 *
	 * @return bool
	 */
	public function delete_webhook();

	/**
	 * Verify bot credentials via getMe.
	 *
	 * @return array|false
	 */
	public function get_me();
}
