<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Poshtibank
 */
class Poshtibank {

	/**
	 * The single instance of the class.
	 *
	 * @var Poshtibank|null
	 */
	protected static $instance = null;

	/**
	 * The loader that's responsible for maintaining and registering all hooks.
	 *
	 * @var Poshtibank_Loader
	 */
	protected $loader;

	/**
	 * Main Poshtibank Instance.
	 *
	 * Ensures only one instance of Poshtibank is loaded or can be loaded.
	 *
	 * @return Poshtibank
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define the core functionality of the plugin.
	 */
	public function __construct() {
		$this->load_dependencies();
		$this->set_locale();
		$this->define_cron_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// Hook orchestrator and internationalization.
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-loader.php';
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-i18n.php';

		// Database layer.
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-db.php';
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-schema.php';

		// Cron scheduler.
		require_once POSHTIBANK_PATH . 'includes/cron/class-poshtibank-cron.php';

		$this->loader = new Poshtibank_Loader();
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 */
	private function set_locale() {
		$plugin_i18n = new Poshtibank_I18n();
		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
	}

	/**
	 * Register cron hooks for background chat purging.
	 */
	private function define_cron_hooks() {
		$cron = new Poshtibank_Cron();
		$this->loader->add_action( 'init', $cron, 'init' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality (Phase 2+).
	 */
	private function define_admin_hooks() {
		// Placeholder for Phase 2: Poshtibank_Admin initialization.
		if ( is_admin() && file_exists( POSHTIBANK_PATH . 'includes/admin/class-poshtibank-admin.php' ) ) {
			require_once POSHTIBANK_PATH . 'includes/admin/class-poshtibank-admin.php';
			// Phase 2 hook registration.
		}
	}

	/**
	 * Register all of the hooks related to the public-facing functionality (Phase 3 & 4+).
	 */
	private function define_public_hooks() {
		// Placeholder for Phase 3 (REST API) & Phase 4 (Public widget).
		if ( file_exists( POSHTIBANK_PATH . 'includes/frontend/class-poshtibank-frontend.php' ) ) {
			require_once POSHTIBANK_PATH . 'includes/frontend/class-poshtibank-frontend.php';
			// Phase 4 hook registration.
		}
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * Get the reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @return Poshtibank_Loader
	 */
	public function get_loader() {
		return $this->loader;
	}
}
