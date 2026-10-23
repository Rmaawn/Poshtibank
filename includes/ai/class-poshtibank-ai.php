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
		$all_settings   = get_option( 'poshtibank_settings', array() );
		$this->settings = $all_settings['ai'] ?? array();
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
		$history = $this->build_conversation_history( $session->session_id );

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
	 * @param object $session Active session.
	 * @return string Handoff notice.
	 */
	protected function handle_human_handoff( $session ) {
		$meta = ! empty( $session->metadata ) ? ( is_array( $session->metadata ) ? $session->metadata : json_decode( $session->metadata, true ) ) : array();
		$meta['ai_paused'] = 1;
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
	 * @param string $session_id
	 * @return array
	 */
	protected function build_conversation_history( $session_id ) {
		$messages = Poshtibank_DB::get_messages( $session_id, 8 );
		$history  = array();

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

		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

		$contents = array();
		foreach ( $history as $h ) {
			$contents[] = array(
				'role'  => ( 'user' === $h['role'] ) ? 'user' : 'model',
				'parts' => array( array( 'text' => $h['content'] ) ),
			);
		}

		// Append latest user message
		$contents[] = array(
			'role'  => 'user',
			'parts' => array( array( 'text' => $user_message ) ),
		);

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

		$url = 'https://api.openai.com/v1/chat/completions';

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

		return false;
	}
}
