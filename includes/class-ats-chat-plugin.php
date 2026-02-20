<?php
/**
 * Core plugin orchestration.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var ATS_Chat_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ATS_Chat_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->maybe_upgrade();

		ATS_Chat_WooCommerce::init();
		new ATS_Chat_GitHub_Updater( ATS_CHAT_PLUGIN_FILE );
		new ATS_Chat_REST();
		new ATS_Chat_Admin();
		new ATS_Chat_Frontend();

		add_action( 'ats_chat_retention_cleanup', array( $this, 'run_retention_cleanup' ) );
	}

	/**
	 * Plugin upgrade path.
	 *
	 * @return void
	 */
	private function maybe_upgrade() {
		$current_version = get_option( 'ats_chat_version', '' );
		$needs_update    = version_compare( (string) $current_version, ATS_CHAT_VERSION, '<' );
		$missing_tables  = ! ATS_Chat_DB::are_tables_ready();

		if ( $needs_update || $missing_tables ) {
			ATS_Chat_DB::create_tables();
			update_option( 'ats_chat_version', ATS_CHAT_VERSION, false );
		}
	}

	/**
	 * Cron retention cleanup.
	 *
	 * @return void
	 */
	public function run_retention_cleanup() {
		$settings = ATS_Chat_DB::get_settings();
		$days     = isset( $settings['data_retention_days'] ) ? absint( $settings['data_retention_days'] ) : 30;
		ATS_Chat_DB::retention_cleanup( $days );
	}

	/**
	 * Check whether user can act as chat agent.
	 *
	 * @param int $user_id Optional user ID.
	 * @return bool
	 */
	public static function user_can_agent( $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( $user_id > 0 ) {
			return user_can( $user_id, 'manage_options' )
				|| user_can( $user_id, 'edit_others_posts' )
				|| user_can( $user_id, 'manage_woocommerce' );
		}

		return current_user_can( 'manage_options' )
			|| current_user_can( 'edit_others_posts' )
			|| current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Validate public nonce for anonymous endpoints.
	 *
	 * @param string $nonce Request nonce.
	 * @return bool
	 */
	public static function verify_public_nonce( $nonce ) {
		return (bool) wp_verify_nonce( (string) $nonce, 'ats_chat_public' );
	}
}
