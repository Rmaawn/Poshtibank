<?php
/**
 * Frontend Widget Controller for Poshtibank.
 *
 * Enqueues lightweight Vanilla JS & modern CSS widget and renders root container.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Frontend
 */
class Poshtibank_Frontend {

	/**
	 * Initialize frontend hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget_markup' ) );
	}

	/**
	 * Enqueue CSS and Vanilla JS for the chat widget.
	 */
	public function enqueue_assets() {
		$settings = Poshtibank_Settings::get_settings();

		// Check if chat is globally enabled
		if ( empty( $settings['general']['enabled'] ) ) {
			return;
		}

		// Enqueue Widget CSS
		wp_enqueue_style(
			'poshtibank-widget-style',
			POSHTIBANK_URL . 'assets/css/poshtibank-widget.css',
			array(),
			POSHTIBANK_VERSION
		);

		// Enqueue Modular Vanilla JS (no jQuery dependency!)
		wp_enqueue_script(
			'poshtibank-widget-script',
			POSHTIBANK_URL . 'assets/js/poshtibank-widget.js',
			array(),
			POSHTIBANK_VERSION,
			true // in footer
		);

		// Check user session / login
		$current_user = wp_get_current_user();
		$user_data    = array(
			'is_logged_in' => is_user_logged_in(),
			'user_id'      => get_current_user_id(),
			'name'         => is_user_logged_in() ? $current_user->display_name : '',
			'email'        => is_user_logged_in() ? $current_user->user_email : '',
		);

		// FAQ items (default pre-chat questions)
		$default_faqs = array(
			array(
				'question' => __( 'چگونه می‌توانم سفارشم را پیگیری کنم؟', 'poshtibank' ),
				'answer'   => __( 'برای پیگیری سفارش، به بخش حساب کاربری > سفارش‌های من مراجعه فرمایید یا شماره سفارش خود را در همین گفتگو ارسال نمایید.', 'poshtibank' ),
			),
			array(
				'question' => __( 'زمان تحویل سفارش‌ها چقدر است؟', 'poshtibank' ),
				'answer'   => __( 'سفارش‌های تهران معمولاً در همان روز یا روز بعد و سفارش‌های شهرستان بین ۲ تا ۴ روز کاری با پست پیشتاز تحویل داده می‌شوند.', 'poshtibank' ),
			),
			array(
				'question' => __( 'ساعات کاری پشتیبانی به چه صورت است؟', 'poshtibank' ),
				'answer'   => __( 'تیم پشتیبانی ما در روزهای کاری از ساعت ۹ صبح تا ۱۸ پاسخگوی شما عزیزان هستند.', 'poshtibank' ),
			),
		);
		$faqs = apply_filters( 'poshtibank_prechat_faqs', $default_faqs );

		require_once POSHTIBANK_PATH . 'includes/woocommerce/class-poshtibank-woocommerce.php';
		$wc_context = Poshtibank_WooCommerce::get_page_context();

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';
		$ai_available = Poshtibank_AI::is_available();

		// Localize script with REST endpoint, nonce, settings & translations
		wp_localize_script(
			'poshtibank-widget-script',
			'poshtibankConfig',
			array(
				'restUrl'     => rest_url( 'poshtibank/v1/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'settings'    => array(
					'widgetTitle'    => $settings['general']['widget_title'] ?? __( 'پشتیبانی آنلاین', 'poshtibank' ),
					'widgetSubtitle' => $settings['general']['widget_subtitle'] ?? '',
					'primaryColor'   => $settings['general']['primary_color'] ?? '#2563eb',
					'position'       => $settings['general']['widget_position'] ?? 'bottom-right',
					'requireContact' => ! empty( $settings['general']['require_contact'] ),
					'channels'       => $settings['channels'] ?? array(),
					'offlineMessage' => $settings['business_hours']['offline_message'] ?? '',
				),
				'handoff'     => array(
					'telegram' => ! empty( $settings['channels']['telegram_url'] ) ? $settings['channels']['telegram_url'] : '',
					'bale'     => ! empty( $settings['channels']['bale_url'] ) ? $settings['channels']['bale_url'] : '',
				),
				'ai'          => array(
					'available' => $ai_available,
				),
				'faqs'        => $faqs,
				'wc'          => $wc_context,
				'user'        => $user_data,
				'page'        => array(
					'url'   => esc_url_raw( ( is_ssl() ? 'https://' : 'http://' ) . ( $_SERVER['HTTP_HOST'] ?? '' ) . ( $_SERVER['REQUEST_URI'] ?? '' ) ),
					'title' => wp_get_document_title(),
				),
				'i18n'        => array(
					'startChat'        => __( 'شروع گفتگوی آنلاین', 'poshtibank' ),
					'modeAiTitle'      => __( 'چت سریع با هوش مصنوعی', 'poshtibank' ),
					'modeAiDesc'       => __( 'پاسخ فوری، شبانه‌روزی و بدون انتظار', 'poshtibank' ),
					'modeHumanTitle'   => __( 'گفتگو با پشتیبان انسانی', 'poshtibank' ),
					'modeHumanDesc'    => __( 'ارتباط با کارشناس تیم پشتیبانی', 'poshtibank' ),
					'modeAiNotice'     => __( 'پاسخ‌ها توسط دستیار هوشمند داده می‌شود.', 'poshtibank' ),
					'modeHumanNotice'  => __( 'گفتگو به پشتیبان انسانی متصل شد.', 'poshtibank' ),
					'askHuman'         => __( 'اتصال به پشتیبان انسانی', 'poshtibank' ),
					'modeSwitchError'  => __( 'تغییر حالت گفتگو ممکن نشد. لطفاً دوباره تلاش کنید.', 'poshtibank' ),
					'contactHub'       => __( 'دیگر راه‌های ارتباط با ما', 'poshtibank' ),
					'faqTitle'         => __( 'سوالات پرتکرار', 'poshtibank' ),
					'inputPlaceholder' => __( 'پیام خود را بنویسید...', 'poshtibank' ),
					'send'             => __( 'ارسال', 'poshtibank' ),
					'nameLabel'        => __( 'نام شما', 'poshtibank' ),
					'phoneLabel'       => __( 'شماره موبایل', 'poshtibank' ),
					'startConversation'=> __( 'ورود به گفتگو', 'poshtibank' ),
					'namePlaceholder'  => __( 'مثال: علی رضایی', 'poshtibank' ),
					'phonePlaceholder' => __( 'مثال: ۰۹۱۲۳۴۵۶۷۸۹', 'poshtibank' ),
					'online'           => __( 'آنلاین و آماده پاسخگویی', 'poshtibank' ),
					'offline'          => __( 'در حال حاضر آفلاین', 'poshtibank' ),
					'newMsgTitle'      => __( '(پیام جدید!)', 'poshtibank' ),
					'requiredFields'   => __( 'لطفاً نام و شماره همراه خود را وارد کنید.', 'poshtibank' ),
					'phoneInvalid'     => __( 'شماره موبایل وارد شده صحیح نیست.', 'poshtibank' ),
					'chatClosed'       => __( 'این گفتگو بسته شده است.', 'poshtibank' ),
					'connectionError'  => __( 'ارتباط با سرور برقرار نشد. لطفاً چند لحظه بعد دوباره تلاش کنید.', 'poshtibank' ),
					'sendFailed'       => __( 'پیام شما ارسال نشد.', 'poshtibank' ),
					'handoffText'      => __( 'ادامه گفتگو در تلگرام / بله', 'poshtibank' ),
					'ratingTitle'      => __( 'میزان رضایت شما از پاسخگویی چطور بود؟', 'poshtibank' ),
					'ratingSubmit'     => __( 'ثبت نظر', 'poshtibank' ),
					'ratingThanks'     => __( 'با تشکر از ثبت نظر شما!', 'poshtibank' ),
					'exitPrompt'       => __( 'قبل از رفتن، سوالی درباره محصولات یا نحوه خرید دارید؟ خوشحال می‌شویم کمکتان کنیم!', 'poshtibank' ),
					'checkoutPrompt'   => __( 'آیا در ثبت سفارش یا پرداخت مشکلی پیش آمده است؟ پشتیبان ما آنلاین است و می‌تواند راهنماییتان کند.', 'poshtibank' ),
					'viewProduct'      => __( 'مشاهده و خرید محصول', 'poshtibank' ),
					'copyCoupon'       => __( 'کپی کد تخفیف', 'poshtibank' ),
					'couponCopied'     => __( 'کد کپی شد!', 'poshtibank' ),
				),
			)
		);
	}

	/**
	 * Output widget container markup in footer.
	 */
	public function render_widget_markup() {
		$settings = Poshtibank_Settings::get_settings();
		if ( empty( $settings['general']['enabled'] ) ) {
			return;
		}

		require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';
		$ai_available = Poshtibank_AI::is_available();

		$position = $settings['general']['widget_position'] ?? 'bottom-right';
		$pos_class = ( 'bottom-left' === $position ) ? 'pos-bottom-left' : 'pos-bottom-right';
		$primary_color = esc_attr( $settings['general']['primary_color'] ?? '#2563eb' );
		?>
		<div id="poshtibank-widget-root" class="<?php echo esc_attr( $pos_class ); ?>" dir="rtl" style="--pb-primary: <?php echo $primary_color; ?>;">
			<!-- Floating Action Button (Launcher) -->
			<button type="button" id="poshtibank-launcher" class="poshtibank-launcher" aria-label="<?php esc_attr_e( 'پشتیبانی آنلاین', 'poshtibank' ); ?>">
				<span class="poshtibank-badge" id="poshtibank-unread-badge" style="display:none;">0</span>
				<div class="poshtibank-launcher-icon icon-chat">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
					</svg>
				</div>
				<div class="poshtibank-launcher-icon icon-close" style="display:none;">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<line x1="18" y1="6" x2="6" y2="18"></line>
						<line x1="6" y1="6" x2="18" y2="18"></line>
					</svg>
				</div>
			</button>

			<!-- Chat Window Container -->
			<div id="poshtibank-window" class="poshtibank-window" style="display:none;">
				<!-- Header -->
				<div class="poshtibank-win-header">
					<div class="poshtibank-header-user">
						<button type="button" id="poshtibank-btn-back" class="poshtibank-btn-icon" style="display:none;" title="<?php esc_attr_e( 'بازگشت به منو', 'poshtibank' ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="9 18 15 12 9 6"></polyline>
							</svg>
						</button>
						<div class="poshtibank-avatar">
							<svg viewBox="0 0 24 24" fill="currentColor">
								<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
							</svg>
							<span class="poshtibank-status-indicator" id="poshtibank-agent-status"></span>
						</div>
						<div class="poshtibank-win-titles">
							<h4 id="poshtibank-header-title"><?php echo esc_html( $settings['general']['widget_title'] ?? 'پشتیبانی آنلاین' ); ?></h4>
							<span id="poshtibank-header-subtitle" class="poshtibank-win-status"><?php echo esc_html( $settings['general']['widget_subtitle'] ?? '' ); ?></span>
						</div>
					</div>
					<div class="poshtibank-header-actions">
						<button type="button" id="poshtibank-btn-minimize" class="poshtibank-btn-icon" title="<?php esc_attr_e( 'بستن پنجره', 'poshtibank' ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<line x1="18" y1="6" x2="6" y2="18"></line>
								<line x1="6" y1="6" x2="18" y2="18"></line>
							</svg>
						</button>
					</div>
				</div>

				<!-- View 1: Hub & Channels & FAQs -->
				<div id="poshtibank-view-hub" class="poshtibank-view active">
					<div class="poshtibank-hub-hero">
						<h3><?php esc_html_e( 'سلام! 👋 چطور می‌تونیم کمکتون کنیم؟', 'poshtibank' ); ?></h3>
						<p>
							<?php
							if ( $ai_available ) {
								esc_html_e( 'انتخاب کنید پاسخ سریع هوش مصنوعی را می‌خواهید یا گفتگو با کارشناس تیم پشتیبانی.', 'poshtibank' );
							} else {
								esc_html_e( 'یک پیام بفرستید، همکاران ما فوراً پاسخگوی شما خواهند بود.', 'poshtibank' );
							}
							?>
						</p>
						<div class="poshtibank-mode-choices" role="group" aria-label="<?php esc_attr_e( 'انتخاب نوع گفتگو', 'poshtibank' ); ?>">
							<?php if ( $ai_available ) : ?>
								<button type="button" class="poshtibank-mode-card" id="poshtibank-mode-ai" data-mode="ai">
									<span class="poshtibank-mode-icon">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
											<path d="M12 3l1.9 4.6L18.5 9.5l-4.6 1.9L12 16l-1.9-4.6L5.5 9.5l4.6-1.9z"></path>
											<path d="M18 15l.8 1.9 1.9.8-1.9.8-.8 1.9-.8-1.9-1.9-.8 1.9-.8z"></path>
										</svg>
									</span>
									<span class="poshtibank-mode-text">
										<span class="poshtibank-mode-title"><?php esc_html_e( 'چت سریع با هوش مصنوعی', 'poshtibank' ); ?></span>
										<span class="poshtibank-mode-desc"><?php esc_html_e( 'پاسخ فوری، شبانه‌روزی و بدون انتظار', 'poshtibank' ); ?></span>
									</span>
									<span class="poshtibank-mode-chevron">
										<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
											<polyline points="15 18 9 12 15 6"></polyline>
										</svg>
									</span>
								</button>
							<?php endif; ?>

							<button type="button" class="poshtibank-mode-card poshtibank-mode-card-primary" id="poshtibank-mode-human" data-mode="human">
								<span class="poshtibank-mode-icon">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
										<path d="M4 14v-2a8 8 0 0 1 16 0v2"></path>
										<path d="M4 14h3v5H5a1 1 0 0 1-1-1z"></path>
										<path d="M20 14h-3v5h2a1 1 0 0 0 1-1z"></path>
									</svg>
								</span>
								<span class="poshtibank-mode-text">
									<span class="poshtibank-mode-title"><?php esc_html_e( 'گفتگو با پشتیبان انسانی', 'poshtibank' ); ?></span>
									<span class="poshtibank-mode-desc"><?php esc_html_e( 'ارتباط با کارشناس تیم پشتیبانی', 'poshtibank' ); ?></span>
								</span>
								<span class="poshtibank-mode-chevron">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
										<polyline points="15 18 9 12 15 6"></polyline>
									</svg>
								</span>
							</button>
						</div>
					</div>

					<!-- FAQs Accordion -->
					<div class="poshtibank-hub-section" id="poshtibank-faqs-section">
						<h5><?php esc_html_e( 'سوالات پرتکرار', 'poshtibank' ); ?></h5>
						<div class="poshtibank-faq-list" id="poshtibank-faq-list"></div>
					</div>

					<!-- Social Channels List -->
					<div class="poshtibank-hub-section" id="poshtibank-channels-section">
						<h5><?php esc_html_e( 'دیگر راه‌های ارتباط با ما', 'poshtibank' ); ?></h5>
						<div class="poshtibank-channels-grid" id="poshtibank-channels-grid"></div>
					</div>
				</div>

				<!-- View 2: Pre-chat Lead Form (Optional) -->
				<div id="poshtibank-view-prechat" class="poshtibank-view">
					<div class="poshtibank-prechat-card">
						<h4><?php esc_html_e( 'اطلاعات تماس شما', 'poshtibank' ); ?></h4>
						<p><?php esc_html_e( 'لطفاً نام و شماره همراه خود را وارد فرمایید تا گفتگو آغاز شود.', 'poshtibank' ); ?></p>
						<form id="poshtibank-prechat-form">
							<div class="poshtibank-input-group">
								<label for="pb-pre-name"><?php esc_html_e( 'نام و نام‌خانوادگی', 'poshtibank' ); ?> <span class="required">*</span></label>
								<input type="text" id="pb-pre-name" placeholder="<?php esc_attr_e( 'مثال: علی رضایی', 'poshtibank' ); ?>" required>
							</div>
							<div class="poshtibank-input-group">
								<label for="pb-pre-phone"><?php esc_html_e( 'شماره موبایل', 'poshtibank' ); ?> <span class="required">*</span></label>
								<input type="tel" id="pb-pre-phone" placeholder="<?php esc_attr_e( 'مثال: ۰۹۱۲۳۴۵۶۷۸۹', 'poshtibank' ); ?>" dir="ltr" required>
							</div>
							<div id="poshtibank-prechat-error" class="poshtibank-form-error" style="display:none;"></div>
							<button type="submit" class="poshtibank-btn-submit"><?php esc_html_e( 'ورود به گفتگو', 'poshtibank' ); ?></button>
						</form>
					</div>
				</div>

				<!-- View 3: Chat Messages Stream -->
				<div id="poshtibank-view-chat" class="poshtibank-view">
					<div id="poshtibank-mode-strip" class="poshtibank-mode-strip" style="display:none;">
						<span class="poshtibank-mode-strip-dot" aria-hidden="true"></span>
						<span class="poshtibank-mode-strip-text" id="poshtibank-mode-strip-text"></span>
						<button type="button" id="poshtibank-btn-ask-human" class="poshtibank-mode-strip-action">
							<?php esc_html_e( 'اتصال به پشتیبان انسانی', 'poshtibank' ); ?>
						</button>
					</div>

					<div id="poshtibank-messages-stream" class="poshtibank-messages-stream">
						<div class="poshtibank-chat-date-separator">
							<span><?php esc_html_e( 'امروز', 'poshtibank' ); ?></span>
						</div>
					</div>

					<!-- Typing indicator -->
					<div id="poshtibank-typing" class="poshtibank-typing" style="display:none;">
						<span></span><span></span><span></span>
						<small><?php esc_html_e( 'پشتیبان در حال پاسخگویی...', 'poshtibank' ); ?></small>
					</div>

					<!-- Input Area -->
					<div class="poshtibank-input-bar">
						<!-- Anti-spam Honeypot -->
						<input type="text" id="pb-hp-check" name="poshtibank_hp" style="display:none !important;" tabindex="-1" autocomplete="off">
						<input type="file" id="poshtibank-file-input" style="display:none !important;" accept="image/*,.pdf,.zip,.doc,.docx,.txt">

						<div class="poshtibank-input-tools">
							<button type="button" id="poshtibank-btn-attach" class="poshtibank-btn-tool" title="<?php esc_attr_e( 'ارسال فایل یا عکس', 'poshtibank' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
								</svg>
							</button>
							<button type="button" id="poshtibank-btn-screenshot" class="poshtibank-btn-tool" title="<?php esc_attr_e( 'ارسال اسکرین‌شات صفحه', 'poshtibank' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
									<circle cx="12" cy="13" r="4"></circle>
								</svg>
							</button>
							<button type="button" id="poshtibank-btn-voice" class="poshtibank-btn-tool" title="<?php esc_attr_e( 'ضبط صدا (ویس)', 'poshtibank' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
									<path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
									<line x1="12" y1="19" x2="12" y2="23"></line>
									<line x1="8" y1="23" x2="16" y2="23"></line>
								</svg>
							</button>
						</div>

						<textarea id="poshtibank-input-text" rows="1" placeholder="<?php esc_attr_e( 'پیام خود را بنویسید...', 'poshtibank' ); ?>"></textarea>

						<div class="poshtibank-input-actions">
							<button type="button" id="poshtibank-btn-send" class="poshtibank-btn-send" aria-label="<?php esc_attr_e( 'ارسال پیام', 'poshtibank' ); ?>">
								<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<line x1="22" y1="2" x2="11" y2="13"></line>
									<polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
								</svg>
							</button>
						</div>

						<!-- Voice Recording Bar Overlay -->
						<div id="poshtibank-voice-bar" class="poshtibank-voice-bar" style="display:none;">
							<div class="poshtibank-voice-rec-dot"></div>
							<span id="poshtibank-voice-timer" class="poshtibank-voice-timer">00:00</span>
							<div class="poshtibank-voice-actions">
								<button type="button" id="poshtibank-btn-cancel-voice" class="poshtibank-btn-vaction btn-cancel" title="<?php esc_attr_e( 'لغو ضبط', 'poshtibank' ); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
								</button>
								<button type="button" id="poshtibank-btn-send-voice" class="poshtibank-btn-vaction btn-confirm" title="<?php esc_attr_e( 'ارسال ویس', 'poshtibank' ); ?>">
									<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
								</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
