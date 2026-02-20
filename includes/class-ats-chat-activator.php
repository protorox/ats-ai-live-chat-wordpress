<?php
/**
 * Activation routines.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_Activator {

	/**
	 * Plugin activation hook.
	 *
	 * @return void
	 */
	public static function activate() {
		ATS_Chat_DB::create_tables();

		$settings = get_option( 'ats_chat_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$settings = wp_parse_args( $settings, ATS_Chat_DB::default_settings() );
		update_option( 'ats_chat_settings', $settings, false );

		if ( ! wp_next_scheduled( 'ats_chat_retention_cleanup' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'ats_chat_retention_cleanup' );
		}

		update_option( 'ats_chat_version', ATS_CHAT_VERSION, false );
	}
}
