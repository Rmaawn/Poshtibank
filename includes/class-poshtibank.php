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
		$this->define_api_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 */
	private function load_dependencies() {
		// Hook orchestrator and internationalization.
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-loader.php';
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-i18n.php';

		// Global settings and configuration manager.
		require_once POSHTIBANK_PATH . 'includes/class-poshtibank-settings.php';

		// Database layer.
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-db.php';
		require_once POSHTIBANK_PATH . 'includes/db/class-poshtibank-schema.php';

		// Cron scheduler.
		require_once POSHTIBANK_PATH . 'includes/cron/class-poshtibank-cron.php';

		// Admin controller & views.
		require_once POSHTIBANK_PATH . 'includes/admin/class-poshtibank-admin.php';

		$this->loader = new Poshtibank_Loader();

		// Tables were only ever created on activation, so replacing the plugin
		// files over a live install could leave the site without them.
		$this->loader->add_action( 'plugins_loaded', $this, 'maybe_upgrade_schema', 20 );
	}

	/**
	 * Bring the database schema up to the version this build expects.
	 */
	public function maybe_upgrade_schema() {
		Poshtibank_Schema::maybe_upgrade();
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
	 * Register all of the hooks related to the admin area functionality.
	 */
	private function define_admin_hooks() {
		if ( is_admin() ) {
			$admin = new Poshtibank_Admin();
			$admin->init();
		}
	}

	/**
	 * Register all REST API endpoints for Client & Webhooks (Phase 3).
	 */
	private function define_api_hooks() {
		require_once POSHTIBANK_PATH . 'includes/api/class-poshtibank-rest-api.php';
		$api = new Poshtibank_REST_API();
		$this->loader->add_action( 'rest_api_init', $api, 'register_routes' );
	}

	/**
	 * Register all of the hooks related to the public-facing widget (Phase 4).
	 */
	private function define_public_hooks() {
		require_once POSHTIBANK_PATH . 'includes/frontend/class-poshtibank-frontend.php';
		$frontend = new Poshtibank_Frontend();
		$frontend->init();
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
