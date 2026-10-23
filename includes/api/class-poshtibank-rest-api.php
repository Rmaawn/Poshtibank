<?php
/**
 * REST API Coordinator for Poshtibank.
 *
 * @package Poshtibank
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once POSHTIBANK_PATH . 'includes/api/class-poshtibank-rest-client.php';
require_once POSHTIBANK_PATH . 'includes/api/class-poshtibank-rest-webhook.php';

/**
 * Class Poshtibank_REST_API
 */
class Poshtibank_REST_API {

	/**
	 * Client routes handler.
	 *
	 * @var Poshtibank_REST_Client
	 */
	protected $client_routes;

	/**
	 * Webhook routes handler.
	 *
	 * @var Poshtibank_REST_Webhook
	 */
	protected $webhook_routes;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client_routes  = new Poshtibank_REST_Client();
		$this->webhook_routes = new Poshtibank_REST_Webhook();
	}

	/**
	 * Register all REST API routes on rest_api_init.
	 */
	public function register_routes() {
		$this->client_routes->register_routes();
		$this->webhook_routes->register_routes();
	}
}
