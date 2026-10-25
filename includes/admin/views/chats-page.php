<?php
/**
 * Admin live chats history view for Poshtibank.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap poshtibank-wrap" dir="rtl">
	<!-- Header -->
	<header class="poshtibank-header">
		<div class="poshtibank-brand">
			<div class="poshtibank-logo">
				<span class="dashicons dashicons-format-chat"></span>
			</div>
			<div>
				<h1><?php esc_html_e( 'گفتگوهای زنده — پشتیبانک', 'poshtibank' ); ?></h1>
				<p class="poshtibank-slogan"><?php esc_html_e( 'مشاهده، مدیریت و سابقه گفتگوهای آنلاین کاربران سایت', 'poshtibank' ); ?></p>
			</div>
		</div>
		<div class="poshtibank-header-actions">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=poshtibank' ) ); ?>" class="button button-secondary">
				<span class="dashicons dashicons-admin-generic"></span>
				<?php esc_html_e( 'تنظیمات افزونه', 'poshtibank' ); ?>
			</a>
		</div>
	</header>

	<div class="poshtibank-card">
		<div class="poshtibank-table-header">
			<h3><?php esc_html_e( 'لیست ۱۰۰ گفتگوی اخیر', 'poshtibank' ); ?></h3>
			<button type="button" class="button" onclick="location.reload();">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'تازه‌سازی لیست', 'poshtibank' ); ?>
			</button>
		</div>

		<?php if ( empty( $sessions ) ) : ?>
			<div class="poshtibank-empty-state">
				<span class="dashicons dashicons-format-chat" style="font-size: 48px; width: 48px; height: 48px; color: #94a3b8;"></span>
				<h4><?php esc_html_e( 'هنوز گفتگویی ثبت نشده است.', 'poshtibank' ); ?></h4>
				<p><?php esc_html_e( 'به محض اینکه کاربری در ویجت چت سایت پیامی ارسال کند، در این جدول نمایش داده خواهد شد.', 'poshtibank' ); ?></p>
			</div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped poshtibank-chats-table">
				<thead>
					<tr>
						<th style="width: 130px;"><?php esc_html_e( 'شناسه نشست', 'poshtibank' ); ?></th>
						<th><?php esc_html_e( 'کاربر / مشخصات', 'poshtibank' ); ?></th>
						<th><?php esc_html_e( 'آدرس IP', 'poshtibank' ); ?></th>
						<th><?php esc_html_e( 'صفحه چت', 'poshtibank' ); ?></th>
						<th style="width: 100px;"><?php esc_html_e( 'وضعیت', 'poshtibank' ); ?></th>
						<th style="width: 90px;"><?php esc_html_e( 'تاپیک تلگرام', 'poshtibank' ); ?></th>
						<th><?php esc_html_e( 'آخرین فعالیت', 'poshtibank' ); ?></th>
						<th style="width: 140px;"><?php esc_html_e( 'عملیات', 'poshtibank' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sessions as $sess ) : ?>
						<tr id="session-row-<?php echo esc_attr( $sess->session_id ); ?>">
							<td>
								<code dir="ltr" title="<?php echo esc_attr( $sess->session_id ); ?>">
									<?php echo esc_html( substr( $sess->session_id, 0, 8 ) . '...' ); ?>
								</code>
							</td>
							<td>
								<strong><?php echo esc_html( ! empty( $sess->user_name ) ? $sess->user_name : __( 'کاربر مهمان', 'poshtibank' ) ); ?></strong>
								<?php if ( ! empty( $sess->user_phone ) ) : ?>
									<br><small dir="ltr"><?php echo esc_html( $sess->user_phone ); ?></small>
								<?php endif; ?>
							</td>
							<td>
								<span dir="ltr"><?php echo esc_html( $sess->ip_address ); ?></span>
							</td>
							<td>
								<?php if ( ! empty( $sess->current_page ) ) : ?>
									<a href="<?php echo esc_url( $sess->current_page ); ?>" target="_blank" rel="noopener noreferrer" title="<?php echo esc_attr( $sess->current_page ); ?>">
										<?php echo esc_html( wp_trim_words( $sess->current_page, 5, '...' ) ); ?>
									</a>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<?php
								$status_class = 'badge-open';
								$status_label = __( 'باز', 'poshtibank' );
								if ( 'closed' === $sess->status ) {
									$status_class = 'badge-closed';
									$status_label = __( 'بسته‌شده', 'poshtibank' );
								} elseif ( 'blocked' === $sess->status ) {
									$status_class = 'badge-blocked';
									$status_label = __( 'بلاک', 'poshtibank' );
								}
								?>
								<span class="poshtibank-status-badge <?php echo esc_attr( $status_class ); ?>">
									<?php echo esc_html( $status_label ); ?>
								</span>
							</td>
							<td>
								<?php if ( ! empty( $sess->telegram_topic_id ) ) : ?>
									<span class="poshtibank-topic-tag" dir="ltr">#<?php echo esc_html( $sess->telegram_topic_id ); ?></span>
								<?php else : ?>
									—
								<?php endif; ?>
							</td>
							<td>
								<?php echo esc_html( $sess->last_activity ); ?>
							</td>
							<td>
								<a class="button button-small" href="<?php echo esc_url( add_query_arg( array( 'page' => 'poshtibank-chats', 'session_id' => $sess->session_id ), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'مشاهده', 'poshtibank' ); ?>
								</a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="poshtibank-inline-form">
									<input type="hidden" name="action" value="poshtibank_update_session_status">
									<input type="hidden" name="session_id" value="<?php echo esc_attr( $sess->session_id ); ?>">
									<input type="hidden" name="redirect_to" value="<?php echo esc_url( admin_url( 'admin.php?page=poshtibank-chats' ) ); ?>">
									<?php wp_nonce_field( 'poshtibank_update_session_status' ); ?>
									<?php if ( 'blocked' === $sess->status ) : ?>
										<input type="hidden" name="status" value="open">
										<button type="submit" class="button button-small">
											<?php esc_html_e( 'رفع مسدودی', 'poshtibank' ); ?>
										</button>
									<?php else : ?>
										<input type="hidden" name="status" value="blocked">
										<button type="submit" class="button button-small button-link-delete" title="<?php esc_attr_e( 'مسدودسازی', 'poshtibank' ); ?>">
											<?php esc_html_e( 'بلاک', 'poshtibank' ); ?>
										</button>
									<?php endif; ?>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
