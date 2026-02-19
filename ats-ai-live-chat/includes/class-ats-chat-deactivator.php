<?php
/**
 * Deactivation routines.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_Deactivator {

	/**
	 * Plugin deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'ats_chat_retention_cleanup' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'ats_chat_retention_cleanup' );
		}
	}
}
