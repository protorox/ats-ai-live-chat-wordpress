<?php
/**
 * Plugin bootstrap.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class.
 */
class ATSLC_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ATSLC_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin runtime.
	 *
	 * @var ATSLC_Admin
	 */
	private $admin;

	/**
	 * Public runtime.
	 *
	 * @var ATSLC_Public
	 */
	private $public;

	/**
	 * GitHub updater runtime.
	 *
	 * @var ATSLC_GitHub_Updater
	 */
	private $updater;

	/**
	 * Get the singleton instance.
	 *
	 * @return ATSLC_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->admin   = new ATSLC_Admin();
		$this->public  = new ATSLC_Public();
		$this->updater = new ATSLC_GitHub_Updater();

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'ats-live-chat', false, dirname( plugin_basename( ATSLC_PLUGIN_FILE ) ) . '/languages' );
	}
}
