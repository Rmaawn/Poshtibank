<?php
/**
 * AI First-Line Responder (BYOK Module).
 *
 * Provides real-time intelligent first-line responses using Google Gemini or OpenAI,
 * with multi-turn conversation context and human operator handoff detection.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_AI
 */
class Poshtibank_AI {

	/**
	 * Settings array.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings = Poshtibank_Settings::get( 'ai', null, array() );
	}

	/**
	 * Check if AI module is active and properly configured.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return ! empty( $this->settings['enabled'] ) && ! empty( $this->settings['api_key'] );
	}

	/**
	 * Check whether the site can offer AI answers at all.
	 *
	 * Used by the widget and the admin screens, which must not instantiate the
	 * module just to decide whether to show the AI option.
	 *
	 * @return bool
	 */
	public static function is_available() {
		$settings = Poshtibank_Settings::get( 'ai', null, array() );

		return ! empty( $settings['enabled'] ) && ! empty( $settings['api_key'] );
	}

	/**
	 * Resolve which responder owns a conversation.
	 *
	 * Sessions created before the chooser existed carry no explicit mode, so
	 * they inherit the historical behaviour: AI answered them whenever the
	 * module was configured.
	 *
	 * @param object $session Session row.
	 * @return string Either 'ai' or 'human'.
	 */
	public static function get_session_mode( $session ) {
		$meta = array();
		if ( ! empty( $session->metadata ) ) {
			$meta = is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true );
			$meta = is_array( $meta ) ? $meta : array();
		}

		if ( isset( $meta['chat_mode'] ) && 'human' === $meta['chat_mode'] ) {
			return 'human';
		}

		// An AI conversation cannot outlive the assistant being switched off.
		return self::is_available() ? 'ai' : 'human';
	}

	/**
	 * Build the request URL for the configured provider.
	 *
	 * The provider setting describes the wire format only, so any compatible
	 * service can be used by pointing the endpoint at it: a bare base URL gets
	 * the provider's path appended, while a full URL is respected as typed.
	 *
	 * @param string $provider Either 'openai' or 'gemini'.
	 * @param string $endpoint Custom base URL, empty for the official service.
	 * @param string $model    Model identifier.
	 * @param string $api_key  API key, needed by Gemini in the query string.
	 * @return string
	 */
	public static function build_endpoint( $provider, $endpoint, $model, $api_key ) {
		$endpoint = trim( (string) $endpoint );
		$model    = trim( (string) $model );

		if ( 'openai' === $provider ) {
			$base = '' !== $endpoint ? $endpoint : 'https://api.openai.com/v1';

			if ( false !== strpos( $base, '/chat/completions' ) ) {
				return $base;
			}

			return untrailingslashit( $base ) . '/chat/completions';
		}

		$base = '' !== $endpoint ? $endpoint : 'https://generativelanguage.googleapis.com/v1beta';

		if ( false !== strpos( $base, ':generateContent' ) ) {
			if ( false !== strpos( $base, 'key=' ) ) {
				return $base;
			}

			return $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . 'key=' . rawurlencode( $api_key );
		}

		return untrailingslashit( $base ) . '/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
	}

	/**
	 * Detect if user message requests a human agent handoff.
	 *
	 * @param string $text User message content.
	 * @return bool
	 */
	public static function is_handoff_requested( $text ) {
		$text = mb_strtolower( trim( $text ) );
		$keywords = array(
			'پشتیبان',
			'اپراتور',
			'انسان',
			'ادمین',
			'کارشناس',
			'وصل کن',
			'اپراتور انسانی',
			'پشتیبان انسانی',
			'پشتیبانی واقعی',
			'human',
			'operator',
			'agent',
			'support',
		);

		foreach ( $keywords as $kw ) {
			if ( mb_strpos( $text, $kw ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Process user message and generate intelligent response.
	 *
	 * @param object $session      Active chat session.
	 * @param string $user_message User message text.
	 * @return string|false Generated response or false.
	 */
	public function process_message( $session, $user_message ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// The customer asked for a human, so this conversation is no longer ours.
		if ( 'ai' !== self::get_session_mode( $session ) ) {
			return false;
		}

		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();

		// If AI is paused on this session (human took over), skip AI
		if ( ! empty( $meta['ai_paused'] ) ) {
			return false;
		}

		// 1. Detect Human Handoff Request
		if ( self::is_handoff_requested( $user_message ) ) {
			return $this->handle_human_handoff( $session );
		}

		// 2. Multi-turn conversation history
		$history = $this->build_conversation_history( $session->session_id, $user_message );

		// 3. System Prompt & Context Enrichment
		$system_prompt = $this->build_system_prompt( $session );

		// 4. Call Provider API
		$provider = $this->settings['provider'] ?? 'gemini';
		$reply    = false;

		if ( 'openai' === $provider ) {
			$reply = $this->call_openai( $system_prompt, $history, $user_message );
		} else {
			$reply = $this->call_gemini( $system_prompt, $history, $user_message );
		}

		if ( empty( $reply ) ) {
			return false;
		}

		// 5. Store AI Response in database
		Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'ai',
				'sender_id'   => 'ai_agent',
				'sender_name' => __( 'دستیار هوشمند', 'poshtibank' ),
				'type'        => 'text',
				'content'     => $reply,
			)
		);

		return $reply;
	}

	/**
	 * Handle transition when user requests human operator.
	 *
	 * Public because the widget can also hand a conversation over on demand,
	 * without waiting for the customer to type a trigger phrase.
	 *
	 * @param object $session Active session.
	 * @return string Handoff notice.
	 */
	public function handle_human_handoff( $session ) {
		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
		$meta['ai_paused'] = 1;
		$meta['chat_mode'] = 'human';
		Poshtibank_DB::update_session( $session->session_id, array( 'metadata' => $meta ) );

		$reply = __( 'درخواست شما برای گفتگو با پشتیبان انسانی ثبت شد. همکاران ما در پیام‌رسان مطلع شدند و به زودی پاسخگوی شما خواهند بود. 🙏', 'poshtibank' );

		// Store AI response
		Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'ai',
				'sender_id'   => 'ai_agent',
				'sender_name' => __( 'دستیار هوشمند', 'poshtibank' ),
				'type'        => 'text',
				'content'     => $reply,
			)
		);

		// Send High-Priority Alert to Telegram / Bale
		require_once POSHTIBANK_PATH . 'includes/bridges/class-poshtibank-bridge-manager.php';
		$bridge_manager = new Poshtibank_Bridge_Manager();
		$alert = "🚨 <b>درخواست فوری اتصال به پشتیبان انسانی!</b>\nکاربر سایت تقاضای ارتباط با اپراتور را دارد.";

		$bridge_manager->dispatch_to_bridges(
			$session,
			array(
				'message_id' => Poshtibank_DB::generate_uuid(),
				'type'       => 'system',
				'content'    => $alert,
			)
		);

		return $reply;
	}

	/**
	 * Build system prompt with site and product context.
	 *
	 * @param object $session
	 * @return string
	 */
	protected function build_system_prompt( $session ) {
		$site_name = get_bloginfo( 'name' );
		$base_prompt = $this->settings['system_prompt'] ?? __( 'شما دستیار هوشمند و مودب پشتیبانی سایت هستید.', 'poshtibank' );

		$context = "نام سایت: {$site_name}\n";
		if ( ! empty( $session->current_page ) ) {
			$context .= "صفحه فعلی کاربر: {$session->current_page}\n";
		}

		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
		if ( ! empty( $meta['wc_product']['title'] ) ) {
			$context .= "محصول مورد نظر کاربر: {$meta['wc_product']['title']} (قیمت: {$meta['wc_product']['price_formatted']})\n";
		}

		return "{$base_prompt}\n\n[اطلاعات بافت مکالمه]:\n{$context}\nدستورالعمل: همیشه به زبان فارسی محترمانه، دقیق و مختصر پاسخ دهید. اگر پاسخ را مطمئن نیستید، به کاربر پیشنهاد دهید گفتگو را به پشتیبان انسانی وصل کنید.";
	}

	/**
	 * Retrieve last 8 messages for multi-turn history.
	 *
	 * @param string $session_id      Session ID.
	 * @param string $exclude_content Content of the message just stored to avoid duplication.
	 * @return array
	 */
	protected function build_conversation_history( $session_id, $exclude_content = '' ) {
		$messages = Poshtibank_DB::get_messages( $session_id, 10 );
		$history  = array();

		// If the very latest message in the DB matches the user message just stored,
		// omit it from the historical context array so it gets appended cleanly as the active turn.
		if ( ! empty( $messages ) ) {
			$last_msg = end( $messages );
			if ( $last_msg && 'user' === $last_msg->sender ) {
				if ( '' === $exclude_content || trim( (string) $last_msg->content ) === trim( (string) $exclude_content ) ) {
					array_pop( $messages );
				}
			}
		}

		foreach ( $messages as $msg ) {
			if ( 'system' === $msg->sender || 'voice' === $msg->type || 'file' === $msg->type ) {
				continue;
			}
			$role = ( 'user' === $msg->sender ) ? 'user' : 'model';
			$history[] = array(
				'role'    => $role,
				'content' => $msg->content,
			);
		}

		return $history;
	}

	/**
	 * Call Google Gemini API (gemini-1.5-flash / gemini-1.5-pro).
	 *
	 * @param string $system_prompt System instruction.
	 * @param array  $history       Conversation history.
	 * @param string $user_message  New user query.
	 * @return string|false
	 */
	protected function call_gemini( $system_prompt, array $history, $user_message ) {
		$api_key = $this->settings['api_key'];
		$model   = ! empty( $this->settings['model'] ) ? $this->settings['model'] : 'gemini-1.5-flash';

		$url = self::build_endpoint( 'gemini', $this->settings['endpoint'] ?? '', $model, $api_key );

		// Build and sanitize multi-turn contents for Gemini API requirements:
		// 1. Alternating turns between 'user' and 'model'
		// 2. First turn MUST have role 'user'
		// 3. Last turn MUST have role 'user'
		$raw_turns = array();
		foreach ( $history as $h ) {
			$raw_turns[] = array(
				'role' => ( 'user' === $h['role'] ) ? 'user' : 'model',
				'text' => $h['content'],
			);
		}
		// Append latest user message
		$raw_turns[] = array(
			'role' => 'user',
			'text' => $user_message,
		);

		// Merge consecutive turns with identical role into one turn
		$merged_turns = array();
		foreach ( $raw_turns as $turn ) {
			$count = count( $merged_turns );
			if ( $count > 0 && $merged_turns[ $count - 1 ]['role'] === $turn['role'] ) {
				$merged_turns[ $count - 1 ]['text'] .= "\n" . $turn['text'];
			} else {
				$merged_turns[] = $turn;
			}
		}

		// Ensure first turn starts with 'user'
		while ( ! empty( $merged_turns ) && 'user' !== $merged_turns[0]['role'] ) {
			array_shift( $merged_turns );
		}

		if ( empty( $merged_turns ) ) {
			$merged_turns[] = array( 'role' => 'user', 'text' => $user_message );
		}

		$contents = array();
		foreach ( $merged_turns as $turn ) {
			$contents[] = array(
				'role'  => $turn['role'],
				'parts' => array( array( 'text' => $turn['text'] ) ),
			);
		}

		$payload = array(
			'system_instruction' => array(
				'parts' => array( array( 'text' => $system_prompt ) ),
			),
			'contents'           => $contents,
			'generationConfig'   => array(
				'temperature'     => 0.4,
				'maxOutputTokens' => 600,
			),
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Poshtibank Gemini Error] ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return trim( $body['candidates'][0]['content']['parts'][0]['text'] );
		}

		if ( ! empty( $body['error'] ) ) {
			error_log( '[Poshtibank Gemini API Error] ' . wp_json_encode( $body['error'] ) );
		}

		return false;
	}

	/**
	 * Call OpenAI API (gpt-4o-mini / gpt-4o).
	 *
	 * @param string $system_prompt System prompt.
	 * @param array  $history       Conversation history.
	 * @param string $user_message  New user query.
	 * @return string|false
	 */
	protected function call_openai( $system_prompt, array $history, $user_message ) {
		$api_key = $this->settings['api_key'];
		$model   = ! empty( $this->settings['model'] ) ? $this->settings['model'] : 'gpt-4o-mini';

		$url = self::build_endpoint( 'openai', $this->settings['endpoint'] ?? '', $model, $api_key );

		$messages = array();
		$messages[] = array(
			'role'    => 'system',
			'content' => $system_prompt,
		);

		foreach ( $history as $h ) {
			$messages[] = array(
				'role'    => ( 'user' === $h['role'] ) ? 'user' : 'assistant',
				'content' => $h['content'],
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $user_message,
		);

		$payload = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => 0.4,
			'max_tokens'  => 600,
		);

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( '[Poshtibank OpenAI Error] ' . $response->get_error_message() );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
			return trim( $body['choices'][0]['message']['content'] );
		}

		if ( ! empty( $body['error'] ) ) {
			error_log( '[Poshtibank OpenAI API Error] ' . wp_json_encode( $body['error'] ) );
		}

		return false;
	}
}
