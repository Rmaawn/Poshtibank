<?php
/**
 * Bot Commands Handler for Admin: Parses /product and /coupon in Telegram and Bale.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/woocommerce/class-poshtibank-woocommerce.php';

/**
 * Class Poshtibank_Bot_Commands
 */
class Poshtibank_Bot_Commands {

	/**
	 * Check if message is an admin command.
	 *
	 * @param string $text
	 * @return bool
	 */
	public static function is_command( $text ) {
		$text = trim( $text );
		return ( strpos( $text, '/' ) === 0 );
	}

	/**
	 * Handle admin bot command.
	 *
	 * @param object $session      Active chat session.
	 * @param string $command_text Command string.
	 * @return string Feedback text for admin.
	 */
	public static function handle( $session, $command_text ) {
		$command_text = trim( $command_text );
		$parts        = explode( ' ', $command_text, 2 );
		$command      = strtolower( $parts[0] );
		$argument     = isset( $parts[1] ) ? trim( $parts[1] ) : '';

		switch ( $command ) {
			case '/product':
				return self::handle_product_command( $session, $argument );

			case '/coupon':
				return self::handle_coupon_command( $session, $argument );

			case '/close':
				Poshtibank_DB::update_session( $session->session_id, array( 'status' => 'closed' ) );
				// Also add system notice
				Poshtibank_DB::create_message(
					array(
						'session_id'  => $session->session_id,
						'sender'      => 'system',
						'type'        => 'system',
						'content'     => __( 'این گفتگو توسط پشتیبان پایان یافت. با تشکر از تماس شما.', 'poshtibank' ),
					)
				);
				return '🔒 گفتگو با کاربر با موفقیت بسته شد.';

			case '/block':
				Poshtibank_DB::update_session( $session->session_id, array( 'status' => 'blocked' ) );
				return '⛔ کاربر و نشست مربوطه مسدود گردید.';

			case '/help':
				return "📌 راهنمای دستورات ربات پشتیبانک:\n\n" .
					"🔹 /product <شناسه یا نام> : ارسال کارت محصول ووکامرس در چت کاربر\n" .
					"🔹 /coupon <کد تخفیف> : ارسال کارت کوپن تخفیف در چت کاربر\n" .
					"🔹 /close : بستن گفتگو و ارسال فرم نظرسنجی رضایت\n" .
					"🔹 /block : مسدودسازی کاربر";

			default:
				return 'دستور ناشناخته است. برای مشاهده دستورات /help را ارسال کنید.';
		}
	}

	/**
	 * Handle /product command.
	 *
	 * @param object $session
	 * @param string $argument
	 * @return string
	 */
	protected static function handle_product_command( $session, $argument ) {
		if ( empty( $argument ) ) {
			return '❌ لطفاً شناسه (ID) یا نام محصول را مشخص کنید. مثال: /product 102';
		}

		$product = Poshtibank_WooCommerce::find_product( $argument );
		if ( ! $product ) {
			return sprintf( '❌ محصولی با مشخصات «%s» یافت نشد.', $argument );
		}

		// Insert product_card message into chat
		Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'admin',
				'sender_name' => __( 'پیشنهاد پشتیبان', 'poshtibank' ),
				'type'        => 'product_card',
				'content'     => $product['title'],
				'metadata'    => $product,
			)
		);

		return sprintf( "✅ کارت محصول «%s» با قیمت %s در چت کاربر قرار گرفت.", $product['title'], $product['price_formatted'] );
	}

	/**
	 * Handle /coupon command.
	 *
	 * @param object $session
	 * @param string $argument
	 * @return string
	 */
	protected static function handle_coupon_command( $session, $argument ) {
		if ( empty( $argument ) ) {
			return '❌ لطفاً کد کوپن را وارد کنید. مثال: /coupon OFF30';
		}

		$coupon = Poshtibank_WooCommerce::find_coupon( $argument );
		if ( ! $coupon ) {
			return sprintf( '❌ کوپن تخفیف با کد «%s» در ووکامرس پیدا نشد.', $argument );
		}

		// Insert coupon message into chat
		Poshtibank_DB::create_message(
			array(
				'session_id'  => $session->session_id,
				'sender'      => 'admin',
				'sender_name' => __( 'کد تخفیف اختصاصی', 'poshtibank' ),
				'type'        => 'coupon',
				'content'     => $coupon['code'],
				'metadata'    => $coupon,
			)
		);

		return sprintf( "🎁 کد تخفیف «%s» (%s) در چت کاربر ارسال شد.", $coupon['code'], $coupon['label'] );
	}
}
