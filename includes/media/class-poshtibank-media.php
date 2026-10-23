<?php
/**
 * Media handler: Manages secure file uploads, voice recordings, screenshots and media downloads.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank_Media
 */
class Poshtibank_Media {

	/**
	 * Upload sub-directory inside wp-content/uploads/.
	 */
	const UPLOAD_SUBDIR = 'poshtibank';

	/**
	 * Whitelisted MIME types and extensions.
	 *
	 * @var array
	 */
	protected static $allowed_mimes = array(
		// Images & Screenshots
		'jpg|jpeg|jpe' => 'image/jpeg',
		'png'          => 'image/png',
		'webp'         => 'image/webp',
		'gif'          => 'image/gif',

		// Audio & Voice Notes
		'ogg|oga'      => 'audio/ogg',
		'webm'         => 'audio/webm',
		'mp3'          => 'audio/mpeg',
		'wav'          => 'audio/wav',
		'm4a'          => 'audio/mp4',

		// Documents
		'pdf'          => 'application/pdf',
		'zip'          => 'application/zip',
		'txt'          => 'text/plain',
		'doc'          => 'application/msword',
		'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xls'          => 'application/vnd.ms-excel',
		'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
	);

	/**
	 * Ensure the upload directory exists and is secured against script execution.
	 *
	 * @return string Absolute path to upload directory.
	 */
	public static function get_upload_dir() {
		$wp_upload = wp_upload_dir();
		$dir       = trailingslashit( $wp_upload['basedir'] ) . self::UPLOAD_SUBDIR . '/';

		if ( ! file_exists( $dir ) ) {
			wp_mkdir_p( $dir );

			// Security: Add index.php
			file_put_contents( $dir . 'index.php', "<?php\n// Silence is golden.\n" );

			// Security: Add .htaccess to prevent PHP/script execution
			$htaccess = "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|phar|pl|py|cgi|asp|sh|bash)\$\">\nDeny from all\n</FilesMatch>\nOptions -Indexes\n";
			file_put_contents( $dir . '.htaccess', $htaccess );
		}

		return $dir;
	}

	/**
	 * Get the public base URL for the upload directory.
	 *
	 * @return string
	 */
	public static function get_upload_url() {
		$wp_upload = wp_upload_dir();
		return trailingslashit( $wp_upload['baseurl'] ) . self::UPLOAD_SUBDIR . '/';
	}

	/**
	 * Process and securely store an uploaded file.
	 *
	 * @param array  $file     Entry from $_FILES.
	 * @param string $category Expected category ('voice', 'image', 'screenshot', 'file').
	 * @return array|WP_Error Array with url, path, name, size, type or WP_Error.
	 */
	public static function handle_upload( array $file, $category = 'file' ) {
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'poshtibank_invalid_upload', __( 'فایل نامعتبر است یا به درستی آپلود نشده است.', 'poshtibank' ) );
		}

		// 1. Check file size (Default max 8MB)
		$max_size = apply_filters( 'poshtibank_max_upload_size', 8 * 1024 * 1024 ); // 8 MB
		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'poshtibank_file_too_large', sprintf( __( 'حجم فایل بیشتر از حد مجاز (%s مگابایت) است.', 'poshtibank' ), round( $max_size / ( 1024 * 1024 ) ) ) );
		}

		// 2. Validate MIME type & extension
		$wp_filetype = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], self::$allowed_mimes );
		$ext         = $wp_filetype['ext'];
		$type        = $wp_filetype['type'];

		// Fallback for audio/webm voice recording if browser sends generic blob
		if ( ( ! $ext || ! $type ) && 'voice' === $category ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			$mime  = finfo_file( $finfo, $file['tmp_name'] );
			finfo_close( $finfo );

			if ( in_array( $mime, array( 'audio/webm', 'video/webm', 'audio/ogg', 'video/ogg' ), true ) ) {
				$ext  = ( strpos( $mime, 'ogg' ) !== false ) ? 'ogg' : 'webm';
				$type = $mime;
			}
		}

		if ( ! $ext || ! $type ) {
			return new WP_Error( 'poshtibank_forbidden_file_type', __( 'فرمت این فایل مجاز نیست.', 'poshtibank' ) );
		}

		// 3. Generate unique safe filename
		$unique_filename = wp_unique_filename( self::get_upload_dir(), sanitize_file_name( $file['name'] ) );
		$destination     = self::get_upload_dir() . $unique_filename;

		// 4. Move uploaded file
		if ( ! move_uploaded_file( $file['tmp_name'], $destination ) ) {
			return new WP_Error( 'poshtibank_upload_failed', __( 'خطا در ذخیره‌سازی فایل بر روی سرور.', 'poshtibank' ) );
		}

		// Set proper file permissions
		chmod( $destination, 0644 );

		$public_url = self::get_upload_url() . $unique_filename;

		return array(
			'url'       => $public_url,
			'path'      => $destination,
			'file_name' => $unique_filename,
			'file_size' => $file['size'],
			'mime_type' => $type,
			'extension' => $ext,
			'category'  => $category,
		);
	}

	/**
	 * Download media file sent by Telegram Bot API to local server.
	 *
	 * Prevents direct client requests to api.telegram.org (which are blocked in Iran).
	 *
	 * @param string $file_id   Telegram file_id.
	 * @param string $bot_token Telegram Bot Token.
	 * @param string $proxy_url Custom proxy URL if configured.
	 * @return string|false Local public URL or false.
	 */
	public static function download_telegram_file( $file_id, $bot_token, $proxy_url = '' ) {
		if ( empty( $file_id ) || empty( $bot_token ) ) {
			return false;
		}

		$base = ! empty( $proxy_url ) ? untrailingslashit( $proxy_url ) : 'https://api.telegram.org';

		// Step 1: Call getFile
		$get_file_url = "{$base}/bot{$bot_token}/getFile?file_id=" . urlencode( $file_id );
		$response     = wp_remote_get( $get_file_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['ok'] ) || empty( $body['result']['file_path'] ) ) {
			return false;
		}

		$file_path = $body['result']['file_path'];

		// Step 2: Download the binary file
		$download_url = "{$base}/file/bot{$bot_token}/{$file_path}";
		$file_resp    = wp_remote_get( $download_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $file_resp ) ) {
			return false;
		}

		$binary = wp_remote_retrieve_body( $file_resp );
		if ( empty( $binary ) ) {
			return false;
		}

		// Step 3: Save locally
		$filename    = 'tg_' . basename( $file_path );
		$unique_name = wp_unique_filename( self::get_upload_dir(), $filename );
		$destination = self::get_upload_dir() . $unique_name;

		if ( false === file_put_contents( $destination, $binary ) ) {
			return false;
		}

		chmod( $destination, 0644 );

		return self::get_upload_url() . $unique_name;
	}

	/**
	 * Download media file sent by Bale Messenger to local server.
	 *
	 * @param string $file_id   Bale file_id.
	 * @param string $bot_token Bale Bot Token.
	 * @return string|false
	 */
	public static function download_bale_file( $file_id, $bot_token ) {
		if ( empty( $file_id ) || empty( $bot_token ) ) {
			return false;
		}

		$get_file_url = "https://tapi.bale.ai/bot{$bot_token}/getFile?file_id=" . urlencode( $file_id );
		$response     = wp_remote_get( $get_file_url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['ok'] ) || empty( $body['result']['file_path'] ) ) {
			return false;
		}

		$file_path    = $body['result']['file_path'];
		$download_url = "https://tapi.bale.ai/file/bot{$bot_token}/{$file_path}";
		$file_resp    = wp_remote_get( $download_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $file_resp ) ) {
			return false;
		}

		$binary = wp_remote_retrieve_body( $file_resp );
		if ( empty( $binary ) ) {
			return false;
		}

		$filename    = 'bale_' . basename( $file_path );
		$unique_name = wp_unique_filename( self::get_upload_dir(), $filename );
		$destination = self::get_upload_dir() . $unique_name;

		if ( false === file_put_contents( $destination, $binary ) ) {
			return false;
		}

		chmod( $destination, 0644 );

		return self::get_upload_url() . $unique_name;
	}
}
