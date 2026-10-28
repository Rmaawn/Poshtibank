<?php
/**
 * Bridge Manager: Orchestrates outgoing messages across all active messenger bridges.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-telegram.php';
require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-bale.php';

/**
 * Class Poshtibank_Bridge_Manager
 */
class Poshtibank_Bridge_Manager {

	/**
	 * Telegram Bridge instance.
	 *
	 * @var Poshtibank_Bridge_Telegram
	 */
	protected $telegram;

	/**
	 * Bale Bridge instance.
	 *
	 * @var Poshtibank_Bridge_Bale
	 */
	protected $bale;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->telegram = new Poshtibank_Bridge_Telegram();
		$this->bale     = new Poshtibank_Bridge_Bale();
	}

	/**
	 * Get Telegram bridge instance.
	 *
	 * @return Poshtibank_Bridge_Telegram
	 */
	public function get_telegram() {
		return $this->telegram;
	}

	/**
	 * Get Bale bridge instance.
	 *
	 * @return Poshtibank_Bridge_Bale
	 */
	public function get_bale() {
		return $this->bale;
	}

	/**
	 * Dispatch user message to all active messenger bridges.
	 *
	 * @param object $session      Session database object.
	 * @param array  $message_data Message record array.
	 * @return array Bridge response logs.
	 */
	public function dispatch_to_bridges( $session, array $message_data ) {
		$responses = array(
			'telegram' => null,
			'bale'     => null,
		);

		// 1. Dispatch to Telegram
		if ( $this->telegram->is_enabled() ) {
			$responses['telegram'] = $this->dispatch_telegram( $session, $message_data );
		}

		// 2. Dispatch to Bale
		if ( $this->bale->is_enabled() ) {
			$responses['bale'] = $this->dispatch_bale( $session, $message_data );
		}

		return $responses;
	}

	/**
	 * Handle Telegram dispatch including Topic creation and reply tracking.
	 *
	 * @param object $session      Session object.
	 * @param array  $message_data Message record.
	 * @return array|false
	 */
	protected function dispatch_telegram( $session, array $message_data ) {
		$chat_id = $this->telegram->get_chat_id();
		$extra   = array();

		// Handle Forum Topics in Supergroups
		if ( $this->telegram->uses_topics() ) {
			$topic_id = ! empty( $session->telegram_topic_id ) ? intval( $session->telegram_topic_id ) : 0;

			// If no topic created yet, create one now
			if ( $topic_id <= 0 ) {
				$user_label = ! empty( $session->user_name ) ? $session->user_name : ( ! empty( $session->user_phone ) ? $session->user_phone : 'کاربر سایت' );
				$topic_name = sprintf( '💬 %s (%s)', $user_label, substr( $session->session_id, 0, 6 ) );

				$new_topic_id = $this->telegram->create_forum_topic( $chat_id, $topic_name );
				if ( $new_topic_id ) {
					$topic_id = $new_topic_id;
					Poshtibank_DB::update_session( $session->session_id, array( 'telegram_topic_id' => $topic_id ) );
					$session->telegram_topic_id = $topic_id;
				}
			}

			if ( $topic_id > 0 ) {
				$extra['message_thread_id'] = $topic_id;
			}
		}

		// Attach Inline Action Keyboard
		$extra['reply_markup'] = Poshtibank_Bridge_Telegram::make_session_inline_keyboard( $session->session_id );

		// Format message content with context header
		$formatted_text = $this->format_header( $session, 'telegram' ) . "\n\n" . esc_html( $message_data['content'] );

		$type = $message_data['type'] ?? 'text';
		$res  = false;

		switch ( $type ) {
			case 'voice':
				$res = $this->telegram->send_voice( $chat_id, $message_data['content'], $this->format_header( $session, 'telegram' ), $extra );
				break;

			case 'image':
				$res = $this->telegram->send_photo( $chat_id, $message_data['content'], $this->format_header( $session, 'telegram' ), $extra );
				break;

			case 'file':
				$res = $this->telegram->send_document( $chat_id, $message_data['content'], $this->format_header( $session, 'telegram' ), $extra );
				break;

			default:
				$res = $this->telegram->send_text( $chat_id, $formatted_text, $extra );
				break;
		}

		// Fallback: If sending into a topic failed (e.g. topic deleted, or chat is not a forum), retry in main chat
		if ( empty( $res['ok'] ) && ! empty( $extra['message_thread_id'] ) ) {
			unset( $extra['message_thread_id'] );
			if ( 'text' === $type ) {
				$res = $this->telegram->send_text( $chat_id, $formatted_text, $extra );
			}
		}

		// Store Telegram Message ID into metadata for reply tracking
		if ( ! empty( $res['ok'] ) && ! empty( $res['result']['message_id'] ) ) {
			$tg_msg_id = $res['result']['message_id'];
			$meta      = ! empty( $message_data['metadata'] ) ? ( is_array( $message_data['metadata'] ) ? $message_data['metadata'] : json_decode( $message_data['metadata'], true ) ) : array();
			$meta['telegram_message_id'] = $tg_msg_id;

			global $wpdb;
			$table = Poshtibank_DB::get_messages_table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'metadata' => wp_json_encode( $meta ) ),
				array( 'message_id' => $message_data['message_id'] )
			);
		}

		return $res;
	}

	/**
	 * Handle Bale dispatch.
	 *
	 * @param object $session      Session object.
	 * @param array  $message_data Message record.
	 * @return array|false
	 */
	protected function dispatch_bale( $session, array $message_data ) {
		$chat_id = $this->bale->get_chat_id();
		$extra   = array();

		// Attach Inline Action Keyboard for Bale
		$extra['reply_markup'] = Poshtibank_Bridge_Bale::make_session_inline_keyboard( $session->session_id );

		$formatted_text = $this->format_header( $session, 'bale' ) . "\n\n" . $message_data['content'];

		$type = $message_data['type'] ?? 'text';
		$res  = false;

		switch ( $type ) {
			case 'voice':
				$res = $this->bale->send_voice( $chat_id, $message_data['content'], $this->format_header( $session, 'bale' ), $extra );
				break;

			case 'image':
				$res = $this->bale->send_photo( $chat_id, $message_data['content'], $this->format_header( $session, 'bale' ), $extra );
				break;

			case 'file':
				$res = $this->bale->send_document( $chat_id, $message_data['content'], $this->format_header( $session, 'bale' ), $extra );
				break;

			default:
				$res = $this->bale->send_text( $chat_id, $formatted_text, $extra );
				break;
		}

		// Store Bale Message ID into metadata
		if ( ! empty( $res['ok'] ) && ! empty( $res['result']['message_id'] ) ) {
			$bale_msg_id = $res['result']['message_id'];
			$meta        = ! empty( $message_data['metadata'] ) ? ( is_array( $message_data['metadata'] ) ? $message_data['metadata'] : json_decode( $message_data['metadata'], true ) ) : array();
			$meta['bale_message_id'] = $bale_msg_id;

			global $wpdb;
			$table = Poshtibank_DB::get_messages_table();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				array( 'metadata' => wp_json_encode( $meta ) ),
				array( 'message_id' => $message_data['message_id'] )
			);
		}

		return $res;
	}

	/**
	 * Build context header for messenger notifications (including WooCommerce context).
	 *
	 * @param object $session  Session object.
	 * @param string $platform 'telegram' or 'bale'.
	 * @return string
	 */
	protected function format_header( $session, $platform = 'telegram' ) {
		$name  = ! empty( $session->user_name ) ? ( 'telegram' === $platform ? esc_html( $session->user_name ) : $session->user_name ) : 'کاربر مهمان';
		$phone = ! empty( $session->user_phone ) ? ' | 📱 ' . ( 'telegram' === $platform ? esc_html( $session->user_phone ) : $session->user_phone ) : '';

		// For Telegram HTML mode, escape '&', '<', '>' in URLs so entity parser does not reject the message.
		if ( ! empty( $session->current_page ) ) {
			$raw_url = esc_url_raw( $session->current_page );
			$page    = "\n🔗 " . ( 'telegram' === $platform ? htmlspecialchars( $raw_url, ENT_QUOTES, 'UTF-8' ) : $raw_url );
		} else {
			$page = '';
		}

		// WooCommerce context enrichment
		$extra_info = '';
		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();

		if ( ! empty( $meta['wc_product'] ) && is_array( $meta['wc_product'] ) ) {
			$prod = $meta['wc_product'];
			$prod_title = 'telegram' === $platform ? esc_html( $prod['title'] ?? '' ) : ( $prod['title'] ?? '' );
			$prod_price = 'telegram' === $platform ? esc_html( $prod['price_formatted'] ?? '' ) : ( $prod['price_formatted'] ?? '' );
			$extra_info .= sprintf( "\n📦 محصول: %s (%s)", $prod_title, $prod_price );
		}

		if ( ! empty( $meta['wc_cart'] ) && is_array( $meta['wc_cart'] ) ) {
			$cart = $meta['wc_cart'];
			$cart_total = 'telegram' === $platform ? esc_html( $cart['total'] ?? '' ) : ( $cart['total'] ?? '' );
			$extra_info .= sprintf( "\n🛒 سبد خرید: %d کالا (مجموع: %s)", absint( $cart['count'] ?? 0 ), $cart_total );
		}

		if ( ! empty( $meta['wc_customer'] ) && is_array( $meta['wc_customer'] ) ) {
			$cust = $meta['wc_customer'];
			$extra_info .= sprintf( "\n🛍 سابقه خرید: %d سفارش ثبت‌شده", absint( $cust['order_count'] ?? 0 ) );
		}

		if ( 'telegram' === $platform ) {
			return sprintf( '👤 <b>%s</b>%s%s%s', $name, $phone, $page, $extra_info );
		}

		return sprintf( "👤 %s%s%s%s", $name, $phone, $page, $extra_info );
	}
}
