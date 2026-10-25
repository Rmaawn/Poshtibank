<?php
/**
 * Admin single conversation view.
 *
 * Rendered by Poshtibank_Admin::render_chats_page() when a session id is
 * present in the URL, so the whole history arrives with the page instead of
 * depending on a follow-up AJAX call.
 *
 * @package Poshtibank
 *
 * @var object|null $session  Session row.
 * @var array       $messages Messages ordered oldest first.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_url    = admin_url( 'admin.php?page=poshtibank-chats' );
$current_url = $list_url;

if ( empty( $session ) ) :
	?>
	<div class="wrap poshtibank-wrap" dir="rtl">
		<div class="notice notice-error">
			<p><?php esc_html_e( 'گفتگوی مورد نظر یافت نشد. ممکن است حذف شده باشد.', 'poshtibank' ); ?></p>
		</div>
		<a class="poshtibank-back-link" href="<?php echo esc_url( $list_url ); ?>">
			<span class="dashicons dashicons-arrow-right-alt2"></span>
			<?php esc_html_e( 'بازگشت به لیست گفتگوها', 'poshtibank' ); ?>
		</a>
	</div>
	<?php
	return;
endif;

$current_url = add_query_arg( 'session_id', $session->session_id, $list_url );

$status        = $session->status;
$display_name  = ! empty( $session->user_name ) ? $session->user_name : __( 'کاربر مهمان', 'poshtibank' );
$status_labels = array(
	'open'    => __( 'باز', 'poshtibank' ),
	'closed'  => __( 'بسته‌شده', 'poshtibank' ),
	'blocked' => __( 'مسدود', 'poshtibank' ),
);
$status_classes = array(
	'open'    => 'badge-open',
	'closed'  => 'badge-closed',
	'blocked' => 'badge-blocked',
);
$status_label = $status_labels[ $status ] ?? $status_labels['open'];
$status_class = $status_classes[ $status ] ?? $status_classes['open'];

$metadata = ! empty( $session->metadata ) && is_string( $session->metadata )
	? json_decode( $session->metadata, true )
	: array();
$csat = is_array( $metadata ) && ! empty( $metadata['csat'] ) ? $metadata['csat'] : null;

require_once POSHTIBANK_PATH . 'includes/ai/class-poshtibank-ai.php';
$chat_mode  = Poshtibank_AI::get_session_mode( $session );
$mode_label = 'ai' === $chat_mode
	? __( 'دستیار هوشمند', 'poshtibank' )
	: __( 'پشتیبان انسانی', 'poshtibank' );

$last_message_id = 0;
if ( ! empty( $messages ) ) {
	$last_message    = end( $messages );
	$last_message_id = (int) $last_message->id;
	reset( $messages );
}
?>
<div class="wrap poshtibank-wrap" dir="rtl">
	<a class="poshtibank-back-link" href="<?php echo esc_url( $list_url ); ?>">
		<span class="dashicons dashicons-arrow-right-alt2"></span>
		<?php esc_html_e( 'بازگشت به لیست گفتگوها', 'poshtibank' ); ?>
	</a>

	<header class="poshtibank-header">
		<div class="poshtibank-brand">
			<div class="poshtibank-logo">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div>
				<h1><?php echo esc_html( $display_name ); ?></h1>
				<p class="poshtibank-slogan">
					<span class="poshtibank-status-badge <?php echo esc_attr( $status_class ); ?>">
						<?php echo esc_html( $status_label ); ?>
					</span>
					<span class="poshtibank-mode-badge mode-<?php echo esc_attr( $chat_mode ); ?>">
						<?php echo esc_html( $mode_label ); ?>
					</span>
					<?php if ( ! empty( $session->user_phone ) ) : ?>
						<span class="poshtibank-header-meta" dir="ltr"><?php echo esc_html( $session->user_phone ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $session->ip_address ) ) : ?>
						<span class="poshtibank-header-meta" dir="ltr"><?php echo esc_html( $session->ip_address ); ?></span>
					<?php endif; ?>
				</p>
			</div>
		</div>

		<div class="poshtibank-header-actions">
			<?php if ( 'open' === $status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="poshtibank_update_session_status">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session->session_id ); ?>">
					<input type="hidden" name="status" value="closed">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'poshtibank_update_session_status' ); ?>
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e( 'بستن گفتگو', 'poshtibank' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( 'closed' === $status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="poshtibank_update_session_status">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session->session_id ); ?>">
					<input type="hidden" name="status" value="open">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'poshtibank_update_session_status' ); ?>
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'بازگشایی گفتگو', 'poshtibank' ); ?>
					</button>
				</form>
			<?php endif; ?>

			<?php if ( 'blocked' === $status ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="poshtibank_update_session_status">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session->session_id ); ?>">
					<input type="hidden" name="status" value="open">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'poshtibank_update_session_status' ); ?>
					<button type="submit" class="button button-secondary">
						<span class="dashicons dashicons-unlock"></span>
						<?php esc_html_e( 'رفع مسدودی', 'poshtibank' ); ?>
					</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="poshtibank_update_session_status">
					<input type="hidden" name="session_id" value="<?php echo esc_attr( $session->session_id ); ?>">
					<input type="hidden" name="status" value="blocked">
					<input type="hidden" name="redirect_to" value="<?php echo esc_url( $current_url ); ?>">
					<?php wp_nonce_field( 'poshtibank_update_session_status' ); ?>
					<button type="submit" class="button button-secondary poshtibank-danger-button">
						<span class="dashicons dashicons-dismiss"></span>
						<?php esc_html_e( 'مسدودسازی کاربر', 'poshtibank' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</header>

	<div class="poshtibank-conversation">
		<section class="poshtibank-card poshtibank-conversation-main">
			<div class="poshtibank-conversation-toolbar">
				<h2><?php esc_html_e( 'تاریخچه گفتگو', 'poshtibank' ); ?></h2>
				<span class="poshtibank-conversation-count">
					<?php
					printf(
						/* translators: %s: number of messages in the conversation. */
						esc_html__( '%s پیام', 'poshtibank' ),
						esc_html( number_format_i18n( count( $messages ) ) )
					);
					?>
				</span>
			</div>

			<div class="poshtibank-chat-stream" id="poshtibank-admin-stream"
				data-session-id="<?php echo esc_attr( $session->session_id ); ?>"
				data-last-id="<?php echo esc_attr( $last_message_id ); ?>">
				<?php if ( empty( $messages ) ) : ?>
					<p class="poshtibank-chat-empty"><?php esc_html_e( 'هنوز پیامی در این گفتگو رد و بدل نشده است.', 'poshtibank' ); ?></p>
				<?php else : ?>
					<?php
					foreach ( $messages as $message ) :
						$sender = $message->sender;
						$class  = 'bubble-user';
						$label  = __( 'کاربر', 'poshtibank' );

						if ( 'admin' === $sender ) {
							$class = 'bubble-admin';
							$label = ! empty( $message->sender_name ) ? $message->sender_name : __( 'پشتیبان', 'poshtibank' );
						} elseif ( 'ai' === $sender ) {
							$class = 'bubble-ai';
							$label = __( 'دستیار هوشمند', 'poshtibank' );
						} elseif ( 'system' === $sender ) {
							$class = 'bubble-system';
							$label = __( 'سیستم', 'poshtibank' );
						}

						$type    = $message->type;
						$content = '';
						$meta    = ! empty( $message->metadata ) && is_string( $message->metadata ) ? json_decode( $message->metadata, true ) : array();

						if ( 'image' === $type ) {
							$content = '<a href="' . esc_url( $message->content ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'مشاهده تصویر', 'poshtibank' ) . '</a>';
						} elseif ( 'voice' === $type ) {
							$content = '<audio controls preload="none" src="' . esc_url( $message->content ) . '"></audio>';
						} elseif ( 'file' === $type ) {
							$file_name = is_array( $meta ) && ! empty( $meta['file_name'] ) ? $meta['file_name'] : basename( $message->content );
							$content   = '<a href="' . esc_url( $message->content ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $file_name ) . '</a>';
						} else {
							$content = nl2br( esc_html( $message->content ) );
						}
						?>
						<div class="poshtibank-chat-bubble <?php echo esc_attr( $class ); ?>"
							data-message-id="<?php echo esc_attr( $message->message_id ); ?>">
							<span class="bubble-sender"><?php echo esc_html( $label ); ?></span>
							<div class="bubble-content"><?php echo wp_kses_post( $content ); ?></div>
							<span class="bubble-time" dir="ltr"><?php echo esc_html( mysql2date( 'Y/m/d H:i', $message->created_at ) ); ?></span>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<?php if ( 'blocked' === $status ) : ?>
				<div class="poshtibank-conversation-blocked">
					<?php esc_html_e( 'این گفتگو مسدود شده است. برای پاسخ‌دهی ابتدا مسدودی کاربر را رفع کنید.', 'poshtibank' ); ?>
				</div>
			<?php else : ?>
				<div class="poshtibank-conversation-composer">
					<label class="screen-reader-text" for="poshtibank-admin-reply"><?php esc_html_e( 'متن پاسخ', 'poshtibank' ); ?></label>
					<textarea id="poshtibank-admin-reply" rows="2" placeholder="<?php esc_attr_e( 'پاسخ خود را بنویسید...', 'poshtibank' ); ?>"></textarea>
					<p class="poshtibank-composer-error" id="poshtibank-admin-reply-error" style="display:none;"></p>
					<div class="poshtibank-conversation-composer-actions">
						<span class="poshtibank-composer-hint"><?php esc_html_e( 'Enter برای ارسال · Shift + Enter برای خط جدید', 'poshtibank' ); ?></span>
						<button type="button" class="button button-primary" id="poshtibank-admin-send">
							<?php esc_html_e( 'ارسال پاسخ', 'poshtibank' ); ?>
						</button>
					</div>
				</div>
			<?php endif; ?>
		</section>

		<aside class="poshtibank-card poshtibank-conversation-side">
			<h2 class="poshtibank-card-title"><?php esc_html_e( 'مشخصات گفتگو', 'poshtibank' ); ?></h2>
			<dl class="poshtibank-meta-list">
				<div>
					<dt><?php esc_html_e( 'شناسه نشست', 'poshtibank' ); ?></dt>
					<dd><code dir="ltr"><?php echo esc_html( $session->session_id ); ?></code></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'شماره تماس', 'poshtibank' ); ?></dt>
					<dd dir="ltr"><?php echo esc_html( ! empty( $session->user_phone ) ? $session->user_phone : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'ایمیل', 'poshtibank' ); ?></dt>
					<dd dir="ltr"><?php echo esc_html( ! empty( $session->user_email ) ? $session->user_email : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'آدرس IP', 'poshtibank' ); ?></dt>
					<dd dir="ltr"><?php echo esc_html( ! empty( $session->ip_address ) ? $session->ip_address : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'صفحه مبدا', 'poshtibank' ); ?></dt>
					<dd>
						<?php if ( ! empty( $session->current_page ) ) : ?>
							<a href="<?php echo esc_url( $session->current_page ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( wp_trim_words( $session->current_page, 6, '...' ) ); ?>
							</a>
						<?php else : ?>
							—
						<?php endif; ?>
					</dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'تاپیک تلگرام', 'poshtibank' ); ?></dt>
					<dd dir="ltr"><?php echo esc_html( ! empty( $session->telegram_topic_id ) ? '#' . $session->telegram_topic_id : '—' ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'شروع گفتگو', 'poshtibank' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( 'Y/m/d H:i', $session->created_at ) ); ?></dd>
				</div>
				<div>
					<dt><?php esc_html_e( 'آخرین فعالیت', 'poshtibank' ); ?></dt>
					<dd><?php echo esc_html( mysql2date( 'Y/m/d H:i', $session->last_activity ) ); ?></dd>
				</div>
				<?php if ( $csat ) : ?>
					<div>
						<dt><?php esc_html_e( 'رضایت مشتری', 'poshtibank' ); ?></dt>
						<dd dir="ltr"><?php echo esc_html( str_repeat( '★', (int) $csat['rating'] ) ); ?></dd>
					</div>
				<?php endif; ?>
			</dl>
		</aside>
	</div>
</div>
