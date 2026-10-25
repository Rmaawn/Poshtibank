<?php
/**
 * Admin settings page template for Poshtibank.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$is_ssl = is_ssl();
?>
<div class="wrap poshtibank-wrap" dir="rtl">
	<!-- Header -->
	<header class="poshtibank-header">
		<div class="poshtibank-brand">
			<div class="poshtibank-logo">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div>
				<h1><?php esc_html_e( 'پشتیبانک — Poshtibank', 'poshtibank' ); ?></h1>
				<p class="poshtibank-slogan"><?php esc_html_e( 'همه پشتیبانی سایت، در یک افزونه', 'poshtibank' ); ?> <span class="poshtibank-badge">نسخه <?php echo esc_html( POSHTIBANK_VERSION ); ?></span></p>
			</div>
		</div>
		<div class="poshtibank-header-actions">
			<button type="button" class="poshtibank-btn poshtibank-btn-primary" id="poshtibank-save-top">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'ذخیره تنظیمات', 'poshtibank' ); ?>
			</button>
		</div>
	</header>

	<?php if ( ! $is_ssl ) : ?>
		<div class="poshtibank-alert poshtibank-alert-warning">
			<span class="dashicons dashicons-warning"></span>
			<div>
				<strong><?php esc_html_e( 'هشدار SSL:', 'poshtibank' ); ?></strong>
				<?php esc_html_e( 'سایت شما در حال حاضر از HTTPS استفاده نمی‌کند. وب‌هوک رسمی تلگرام و بله برای ارسال پاسخ‌های ادمین، الزماً نیازمند گواهینامه SSL معتبر است.', 'poshtibank' ); ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Tab Navigation -->
	<nav class="poshtibank-tabs-nav" id="poshtibank-tabs-nav">
		<a href="#tab-general" class="poshtibank-tab-link active">
			<span class="dashicons dashicons-admin-generic"></span>
			<?php esc_html_e( 'عمومی و ظاهر', 'poshtibank' ); ?>
		</a>
		<a href="#tab-telegram" class="poshtibank-tab-link">
			<span class="dashicons dashicons-share-alt"></span>
			<?php esc_html_e( 'پل تلگرام', 'poshtibank' ); ?>
		</a>
		<a href="#tab-bale" class="poshtibank-tab-link">
			<span class="dashicons dashicons-format-chat"></span>
			<?php esc_html_e( 'پیام‌رسان بله', 'poshtibank' ); ?>
		</a>
		<a href="#tab-channels" class="poshtibank-tab-link">
			<span class="dashicons dashicons-networking"></span>
			<?php esc_html_e( 'هاب ارتباطی', 'poshtibank' ); ?>
		</a>
		<a href="#tab-hours" class="poshtibank-tab-link">
			<span class="dashicons dashicons-clock"></span>
			<?php esc_html_e( 'ساعات کاری', 'poshtibank' ); ?>
		</a>
		<a href="#tab-ai" class="poshtibank-tab-link">
			<span class="dashicons dashicons-superhero"></span>
			<?php esc_html_e( 'هوش مصنوعی (AI)', 'poshtibank' ); ?>
		</a>
		<a href="#tab-security" class="poshtibank-tab-link">
			<span class="dashicons dashicons-shield"></span>
			<?php esc_html_e( 'امنیت و پیشرفته', 'poshtibank' ); ?>
		</a>
	</nav>

	<!-- Main Form -->
	<form id="poshtibank-settings-form" class="poshtibank-form">
		<!-- 1. GENERAL TAB -->
		<section class="poshtibank-tab-panel active" id="tab-general">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'تنظیمات عمومی ویجت چت', 'poshtibank' ); ?></h3>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[general][enabled]" value="1" <?php checked( $settings['general']['enabled'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'فعال‌سازی چت آنلاین در سایت', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'با خاموش کردن این گزینه، ویجت چت به طور کامل از روی سایت برداشته می‌شود.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-widget-title"><?php esc_html_e( 'عنوان پنجره چت', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-widget-title" name="settings[general][widget_title]" value="<?php echo esc_attr( $settings['general']['widget_title'] ); ?>" class="regular-text">
						<span class="description"><?php esc_html_e( 'عنوانی که در بالای پنجره گفتگو به مشتری نمایش داده می‌شود.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-widget-subtitle"><?php esc_html_e( 'زیرعنوان وضعیت', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-widget-subtitle" name="settings[general][widget_subtitle]" value="<?php echo esc_attr( $settings['general']['widget_subtitle'] ); ?>" class="regular-text">
						<span class="description"><?php esc_html_e( 'مثال: معمولاً در کمتر از چند دقیقه پاسخگوییم', 'poshtibank' ); ?></span>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-primary-color"><?php esc_html_e( 'رنگ سازمانی ویجت', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-primary-color" name="settings[general][primary_color]" value="<?php echo esc_attr( $settings['general']['primary_color'] ); ?>" class="poshtibank-color-picker">
						<span class="description"><?php esc_html_e( 'رنگ دکمه شناور و سربرگ پنجره چت.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-widget-position"><?php esc_html_e( 'موقعیت قرارگیری ویجت', 'poshtibank' ); ?></label>
						<select id="poshtibank-widget-position" name="settings[general][widget_position]">
							<option value="bottom-right" <?php selected( $settings['general']['widget_position'], 'bottom-right' ); ?>><?php esc_html_e( 'پایین راست صفحه (پیش‌فرض)', 'poshtibank' ); ?></option>
							<option value="bottom-left" <?php selected( $settings['general']['widget_position'], 'bottom-left' ); ?>><?php esc_html_e( 'پایین چپ صفحه', 'poshtibank' ); ?></option>
						</select>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-retention-days"><?php esc_html_e( 'مدت زمان نگهداری تاریخچه چت‌ها (روز)', 'poshtibank' ); ?></label>
						<input type="number" min="0" max="365" id="poshtibank-retention-days" name="settings[general][retention_days]" value="<?php echo esc_attr( $settings['general']['retention_days'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'چت‌های قدیمی‌تر از این تعداد روز به صورت خودکار توسط WP-Cron پاکسازی می‌شوند. برای عدم پاکسازی ۰ وارد کنید.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label class="poshtibank-switch-inline">
							<input type="checkbox" name="settings[general][require_contact]" value="1" <?php checked( $settings['general']['require_contact'], 1 ); ?>>
							<span><?php esc_html_e( 'الزام کاربر به وارد کردن نام و شماره تماس قبل از شروع گفتگو', 'poshtibank' ); ?></span>
						</label>
						<span class="description"><?php esc_html_e( 'در صورت فعال بودن، ابتدا فرم دریافت لید نمایش داده شده و سپس چت آغاز می‌شود.', 'poshtibank' ); ?></span>
					</div>
				</div>
			</div>
		</section>

		<!-- 2. TELEGRAM TAB -->
		<section class="poshtibank-tab-panel" id="tab-telegram">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'پل ارتباطی تلگرام (Telegram Bridge)', 'poshtibank' ); ?></h3>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[telegram][enabled]" value="1" <?php checked( $settings['telegram']['enabled'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'فعال‌سازی ارسال و دریافت پیام در تلگرام', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'پیام‌های کاربران سایت به ربات تلگرام شما ارسال شده و پاسخ با Reply برمی‌گردد.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-tg-token"><?php esc_html_e( 'توکن ربات تلگرام (Bot Token)', 'poshtibank' ); ?> <span class="required">*</span></label>
						<input type="text" id="poshtibank-tg-token" name="settings[telegram][bot_token]" value="<?php echo esc_attr( $settings['telegram']['bot_token'] ); ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" class="large-text" dir="ltr">
						<span class="description"><?php esc_html_e( 'توکن دریافتی از BotFather@ در تلگرام.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-tg-chat-id"><?php esc_html_e( 'شناسه چت یا سوپرگروه ادمین (Chat ID)', 'poshtibank' ); ?> <span class="required">*</span></label>
						<input type="text" id="poshtibank-tg-chat-id" name="settings[telegram][chat_id]" value="<?php echo esc_attr( $settings['telegram']['chat_id'] ); ?>" placeholder="-100123456789 یا 12345678" class="regular-text" dir="ltr">
						<span class="description"><?php esc_html_e( 'آیدی عددی اکانت ادمین یا آیدی گروه/کانال پشتیبانی (مثلاً 100123456789-).', 'poshtibank' ); ?></span>
					</div>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-tg-proxy"><?php esc_html_e( 'پروکسی معکوس / ورکر کلودفلر (Custom Cloudflare Worker Proxy)', 'poshtibank' ); ?></label>
					<input type="url" id="poshtibank-tg-proxy" name="settings[telegram][proxy_url]" value="<?php echo esc_attr( $settings['telegram']['proxy_url'] ); ?>" placeholder="https://my-telegram-proxy.workers.dev" class="large-text" dir="ltr">
					<span class="description"><?php esc_html_e( 'اگر هاست سایت شما در ایران است و api.telegram.org مسدود است، آدرس ورکر کلودفلر اختصاصی خود را در این فیلد قرار دهید تا درخواست‌ها بدون فیلتر عبور کنند.', 'poshtibank' ); ?></span>
				</div>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[telegram][use_topics]" value="1" <?php checked( $settings['telegram']['use_topics'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'حالت چند اپراتوره با تاپیک‌های تلگرام (Forum Topics)', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'در صورت فعال بودن و ارسال به یک سوپرگروه با قابلیت Forum، برای هر کاربر سایت یک تاپیک مجزا ساخته می‌شود تا گفتگوها هرگز با هم قاطی نشوند.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<!-- Webhook Box -->
				<div class="poshtibank-webhook-box">
					<h4><?php esc_html_e( 'تنظیم و مدیریت Webhook تلگرام', 'poshtibank' ); ?></h4>
					<p><?php esc_html_e( 'آدرس وبهوک REST API این سایت برای دریافت آنی ریپلای‌های ادمین در تلگرام:', 'poshtibank' ); ?></p>
					<code dir="ltr" class="poshtibank-code-block"><?php echo esc_url( rest_url( 'poshtibank/v1/webhook/telegram' ) ); ?></code>

					<div class="poshtibank-webhook-actions">
						<button type="button" class="button button-primary" id="btn-tg-set-webhook">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'ثبت خودکار وبهوک (Set Webhook)', 'poshtibank' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="btn-tg-test">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'تست اتصال ربات (Test Bot)', 'poshtibank' ); ?>
						</button>
						<button type="button" class="button button-link-delete" id="btn-tg-delete-webhook">
							<?php esc_html_e( 'حذف وبهوک (Delete Webhook)', 'poshtibank' ); ?>
						</button>
					</div>

					<div id="tg-webhook-status" class="poshtibank-status-message" style="display:none;"></div>
				</div>
			</div>
		</section>

		<!-- 3. BALE TAB -->
		<section class="poshtibank-tab-panel" id="tab-bale">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'پل پیام‌رسان بله (Bale Messenger Bridge)', 'poshtibank' ); ?></h3>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[bale][enabled]" value="1" <?php checked( $settings['bale']['enabled'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'فعال‌سازی ارسال و دریافت پیام در پیام‌رسان ایرانی بله', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'بله کاملاً بدون فیلتر بوده و روی تمامی سرورها و هاست‌های ایران بدون نیاز به پروکسی کار می‌کند.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-bale-token"><?php esc_html_e( 'توکن ربات بله (Bot Token)', 'poshtibank' ); ?> <span class="required">*</span></label>
						<input type="text" id="poshtibank-bale-token" name="settings[bale][bot_token]" value="<?php echo esc_attr( $settings['bale']['bot_token'] ); ?>" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" class="large-text" dir="ltr">
						<span class="description"><?php esc_html_e( 'توکن دریافتی از بازوی BotFather@ در اپلیکیشن بله.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-bale-chat-id"><?php esc_html_e( 'شناسه چت یا کانال ادمین در بله (Chat ID)', 'poshtibank' ); ?> <span class="required">*</span></label>
						<input type="text" id="poshtibank-bale-chat-id" name="settings[bale][chat_id]" value="<?php echo esc_attr( $settings['bale']['chat_id'] ); ?>" placeholder="12345678" class="regular-text" dir="ltr">
						<span class="description"><?php esc_html_e( 'شناسه عددی اکانت یا کانال پشتیبان در بله.', 'poshtibank' ); ?></span>
					</div>
				</div>

				<!-- Bale Webhook Box -->
				<div class="poshtibank-webhook-box">
					<h4><?php esc_html_e( 'تنظیم و مدیریت Webhook بله', 'poshtibank' ); ?></h4>
					<p><?php esc_html_e( 'آدرس وبهوک اختصاصی برای دریافت پاسخ‌های ارسالی از پیام‌رسان بله:', 'poshtibank' ); ?></p>
					<code dir="ltr" class="poshtibank-code-block"><?php echo esc_url( rest_url( 'poshtibank/v1/webhook/bale' ) ); ?></code>

					<div class="poshtibank-webhook-actions">
						<button type="button" class="button button-primary" id="btn-bale-set-webhook">
							<span class="dashicons dashicons-admin-links"></span>
							<?php esc_html_e( 'ثبت خودکار وبهوک بله', 'poshtibank' ); ?>
						</button>
						<button type="button" class="button button-secondary" id="btn-bale-test">
							<span class="dashicons dashicons-update"></span>
							<?php esc_html_e( 'تست اتصال ربات بله', 'poshtibank' ); ?>
						</button>
						<button type="button" class="button button-link-delete" id="btn-bale-delete-webhook">
							<?php esc_html_e( 'حذف وبهوک بله', 'poshtibank' ); ?>
						</button>
					</div>

					<div id="bale-webhook-status" class="poshtibank-status-message" style="display:none;"></div>
				</div>
			</div>
		</section>

		<!-- 4. CHANNELS TAB -->
		<section class="poshtibank-tab-panel" id="tab-channels">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'هاب ارتباطی چندمنظوره (Social & Quick Contact Hub)', 'poshtibank' ); ?></h3>
				<p class="description"><?php esc_html_e( 'این راه‌های ارتباطی در صفحه اول ویجت و در کنار چت آنلاین برای دسترسی سریع به کاربران نمایش داده می‌شوند.', 'poshtibank' ); ?></p>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-telegram"><?php esc_html_e( 'لینک تلگرام (Telegram)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-telegram" name="settings[channels][telegram_url]" value="<?php echo esc_attr( $settings['channels']['telegram_url'] ); ?>" placeholder="https://t.me/my_support" class="large-text" dir="ltr">
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-whatsapp"><?php esc_html_e( 'شماره یا لینک واتساپ (WhatsApp)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-whatsapp" name="settings[channels][whatsapp_url]" value="<?php echo esc_attr( $settings['channels']['whatsapp_url'] ); ?>" placeholder="https://wa.me/989123456789" class="large-text" dir="ltr">
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-bale"><?php esc_html_e( 'لینک پیام‌رسان بله (Bale)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-bale" name="settings[channels][bale_url]" value="<?php echo esc_attr( $settings['channels']['bale_url'] ); ?>" placeholder="https://ble.ir/my_channel" class="large-text" dir="ltr">
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-eitaa"><?php esc_html_e( 'لینک پیام‌رسان ایتا (Eitaa)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-eitaa" name="settings[channels][eitaa_url]" value="<?php echo esc_attr( $settings['channels']['eitaa_url'] ); ?>" placeholder="https://eitaa.com/my_support" class="large-text" dir="ltr">
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-instagram"><?php esc_html_e( 'پیج اینستاگرام (Instagram)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-instagram" name="settings[channels][instagram_url]" value="<?php echo esc_attr( $settings['channels']['instagram_url'] ); ?>" placeholder="https://instagram.com/my_brand" class="large-text" dir="ltr">
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ch-phone"><?php esc_html_e( 'شماره تماس مستقیم پشتیبانی', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ch-phone" name="settings[channels][phone_number]" value="<?php echo esc_attr( $settings['channels']['phone_number'] ); ?>" placeholder="02112345678" class="regular-text" dir="ltr">
					</div>
				</div>
			</div>
		</section>

		<!-- 5. BUSINESS HOURS TAB -->
		<section class="poshtibank-tab-panel" id="tab-hours">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'ساعات کاری و پاسخگویی پشتیبانان', 'poshtibank' ); ?></h3>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[business_hours][enabled]" value="1" <?php checked( $settings['business_hours']['enabled'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'فعال‌سازی محدودیت ساعات کاری', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'در خارج از ساعات کاری، پیام حالت آفلاین به کاربر نمایش داده می‌شود و اطلاعات تماس وی دریافت خواهد شد.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-offline-msg"><?php esc_html_e( 'پیام وضعیت آفلاین', 'poshtibank' ); ?></label>
					<textarea id="poshtibank-offline-msg" name="settings[business_hours][offline_message]" rows="3" class="large-text"><?php echo esc_textarea( $settings['business_hours']['offline_message'] ); ?></textarea>
				</div>

				<h4 style="margin-top:20px;"><?php esc_html_e( 'برنامه هفتگی ساعات کاری:', 'poshtibank' ); ?></h4>
				<table class="poshtibank-table-schedule">
					<thead>
						<tr>
							<th><?php esc_html_e( 'روز هفته', 'poshtibank' ); ?></th>
							<th><?php esc_html_e( 'وضعیت', 'poshtibank' ); ?></th>
							<th><?php esc_html_e( 'ساعت شروع', 'poshtibank' ); ?></th>
							<th><?php esc_html_e( 'ساعت پایان', 'poshtibank' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$day_labels = array(
							'sat' => __( 'شنبه', 'poshtibank' ),
							'sun' => __( 'یکشنبه', 'poshtibank' ),
							'mon' => __( 'دوشنبه', 'poshtibank' ),
							'tue' => __( 'سه‌شنبه', 'poshtibank' ),
							'wed' => __( 'چهارشنبه', 'poshtibank' ),
							'thu' => __( 'پنجشنبه', 'poshtibank' ),
							'fri' => __( 'جمعه', 'poshtibank' ),
						);

						foreach ( $day_labels as $day_key => $day_name ) :
							$day_cfg = $settings['business_hours']['schedule'][ $day_key ] ?? array( 'active' => 1, 'start' => '09:00', 'end' => '18:00' );
							?>
							<tr>
								<td><strong><?php echo esc_html( $day_name ); ?></strong></td>
								<td>
									<label class="poshtibank-switch-sm">
										<input type="checkbox" name="settings[business_hours][schedule][<?php echo esc_attr( $day_key ); ?>][active]" value="1" <?php checked( ! empty( $day_cfg['active'] ) ); ?>>
										<span class="poshtibank-slider"></span>
									</label>
								</td>
								<td>
									<input type="time" name="settings[business_hours][schedule][<?php echo esc_attr( $day_key ); ?>][start]" value="<?php echo esc_attr( $day_cfg['start'] ?? '09:00' ); ?>" dir="ltr">
								</td>
								<td>
									<input type="time" name="settings[business_hours][schedule][<?php echo esc_attr( $day_key ); ?>][end]" value="<?php echo esc_attr( $day_cfg['end'] ?? '18:00' ); ?>" dir="ltr">
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>

		<!-- 6. AI BYOK TAB -->
		<section class="poshtibank-tab-panel" id="tab-ai">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'ایجنت هوش مصنوعی خط اول (BYOK AI Responder)', 'poshtibank' ); ?></h3>

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[ai][enabled]" value="1" <?php checked( $settings['ai']['enabled'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong><?php esc_html_e( 'فعال‌سازی پاسخگوی هوشمند خط اول (BYOK)', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'هوش مصنوعی می‌تواند سوالات اولیه و متداول کاربران را بلافاصله پاسخ دهد و در صورت لزوم گفتگو را به تلگرام اپراتور انسانی بسپارد.', 'poshtibank' ); ?></p>
					</div>
				</div>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-ai-provider"><?php esc_html_e( 'نوع API', 'poshtibank' ); ?></label>
						<select id="poshtibank-ai-provider" name="settings[ai][provider]">
							<option value="gemini" <?php selected( $settings['ai']['provider'], 'gemini' ); ?>><?php esc_html_e( 'سازگار با Gemini', 'poshtibank' ); ?></option>
							<option value="openai" <?php selected( $settings['ai']['provider'], 'openai' ); ?>><?php esc_html_e( 'سازگار با OpenAI', 'poshtibank' ); ?></option>
						</select>
						<span class="description"><?php esc_html_e( 'قالب درخواست را انتخاب کنید؛ هر سرویس سازگار (OpenRouter، Groq، Ollama و…) با همین دو گزینه کار می‌کند.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-ai-model"><?php esc_html_e( 'مدل انتخابی (Model)', 'poshtibank' ); ?></label>
						<input type="text" id="poshtibank-ai-model" name="settings[ai][model]" value="<?php echo esc_attr( $settings['ai']['model'] ); ?>" placeholder="gemini-1.5-flash یا gpt-4o-mini" class="regular-text" dir="ltr">
					</div>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-ai-endpoint"><?php esc_html_e( 'آدرس اندپوینت (اختیاری)', 'poshtibank' ); ?></label>
					<input type="url" id="poshtibank-ai-endpoint" name="settings[ai][endpoint]" value="<?php echo esc_attr( $settings['ai']['endpoint'] ?? '' ); ?>" class="large-text" dir="ltr" placeholder="https://api.openai.com/v1">
					<span class="description"><?php esc_html_e( 'آدرس پایه سرویس را وارد کنید؛ مسیر اختصاصی هر نوع API خودکار اضافه می‌شود. اگر خالی بماند، سرویس رسمی همان گزینه استفاده می‌شود.', 'poshtibank' ); ?></span>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-ai-key"><?php esc_html_e( 'کلید API اختصاصی شما (API Key)', 'poshtibank' ); ?> <span class="required">*</span></label>
					<div class="poshtibank-input-with-btn">
						<input type="password" id="poshtibank-ai-key" name="settings[ai][api_key]" value="<?php echo esc_attr( $settings['ai']['api_key'] ); ?>" class="large-text" dir="ltr" placeholder="AIzaSy... یا sk-...">
						<button type="button" class="button" id="btn-test-ai">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'تست اعتبار کلید API', 'poshtibank' ); ?>
						</button>
					</div>
					<span class="description"><?php esc_html_e( 'کلید شخصی شما در دیتابیس لوکال سایت محفوظ می‌ماند و به هیچ سرور واسطی ارسال نخواهد شد.', 'poshtibank' ); ?></span>
					<div id="ai-test-status" class="poshtibank-status-message" style="display:none; margin-top:8px;"></div>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-ai-prompt"><?php esc_html_e( 'دستورالعمل سیستم (System Prompt & Knowledge)', 'poshtibank' ); ?></label>
					<textarea id="poshtibank-ai-prompt" name="settings[ai][system_prompt]" rows="6" class="large-text"><?php echo esc_textarea( $settings['ai']['system_prompt'] ); ?></textarea>
					<span class="description"><?php esc_html_e( 'توضیحات سایت، قوانین ارسال، شرایط بازگشت کالا و نحوه لحن پاسخگویی را برای ایجنت هوش مصنوعی اینجا بنویسید.', 'poshtibank' ); ?></span>
				</div>
			</div>
		</section>

		<!-- 7. SECURITY TAB -->
		<section class="poshtibank-tab-panel" id="tab-security">
			<div class="poshtibank-card">
				<h3 class="poshtibank-card-title"><?php esc_html_e( 'امنیت، ضد اسپم و تنظیمات پیشرفته', 'poshtibank' ); ?></h3>

				<div class="poshtibank-grid poshtibank-grid-2">
					<div class="poshtibank-form-field">
						<label for="poshtibank-rate-limit"><?php esc_html_e( 'حداکثر تعداد پیام مجاز در دقیقه (Rate Limit)', 'poshtibank' ); ?></label>
						<input type="number" min="1" max="100" id="poshtibank-rate-limit" name="settings[security][rate_limit_messages]" value="<?php echo esc_attr( $settings['security']['rate_limit_messages'] ); ?>" class="small-text">
						<span class="description"><?php esc_html_e( 'جلوگیری از ارسال رگباری پیام توسط ربات‌های اسپمر به تلگرام یا بله ادمین.', 'poshtibank' ); ?></span>
					</div>

					<div class="poshtibank-form-field">
						<label for="poshtibank-rate-window"><?php esc_html_e( 'بازه زمانی بررسی محدودیت (ثانیه)', 'poshtibank' ); ?></label>
						<input type="number" min="10" max="600" id="poshtibank-rate-window" name="settings[security][rate_limit_window]" value="<?php echo esc_attr( $settings['security']['rate_limit_window'] ); ?>" class="small-text">
					</div>
				</div>

				<div class="poshtibank-form-field">
					<label for="poshtibank-blocked-ips"><?php esc_html_e( 'لیست سیاه آی‌پی‌های مسدودشده (Blacklisted IPs)', 'poshtibank' ); ?></label>
					<textarea id="poshtibank-blocked-ips" name="settings[security][blocked_ips]" rows="5" class="large-text" dir="ltr"><?php echo esc_textarea( implode( "\n", (array) $settings['security']['blocked_ips'] ) ); ?></textarea>
					<span class="description"><?php esc_html_e( 'هر آی‌پی را در یک سطر جداگانه وارد کنید. کاربران با این IPها امکان شروع چت یا ارسال پیام را نخواهند داشت.', 'poshtibank' ); ?></span>
				</div>

				<hr class="poshtibank-divider">

				<div class="poshtibank-form-group">
					<label class="poshtibank-switch">
						<input type="checkbox" name="settings[advanced][delete_data_on_uninstall]" value="1" <?php checked( $settings['advanced']['delete_data_on_uninstall'], 1 ); ?>>
						<span class="poshtibank-slider"></span>
					</label>
					<div class="poshtibank-label-desc">
						<strong style="color: #dc2626;"><?php esc_html_e( 'حذف کامل داده‌ها هنگام حذف افزونه (Delete All Data on Uninstall)', 'poshtibank' ); ?></strong>
						<p><?php esc_html_e( 'در صورت فعال بودن، در زمان حذف کامل افزونه از بخش پلاگین‌های وردپرس، جداول چت و تمام پیام‌ها از دیتابیس پاک خواهند شد.', 'poshtibank' ); ?></p>
					</div>
				</div>
			</div>
		</section>

		<!-- Bottom Sticky Action Bar -->
		<div class="poshtibank-sticky-bar">
			<button type="submit" class="poshtibank-btn poshtibank-btn-primary" id="poshtibank-save-bottom">
				<span class="dashicons dashicons-saved"></span>
				<?php esc_html_e( 'ذخیره تمام تغییرات', 'poshtibank' ); ?>
			</button>
			<span id="poshtibank-save-status" class="poshtibank-save-status"></span>
		</div>
	</form>
</div>
