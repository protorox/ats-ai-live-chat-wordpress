<?php
/**
 * Database layer for ATS Live Chat.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles custom table setup and data access.
 */
class ATSLC_DB {

	/**
	 * Get the messages table name.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'atslc_messages';
	}

	/**
	 * Create the custom table and seed defaults.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL,
			visitor_name varchar(120) NOT NULL DEFAULT '',
			visitor_email varchar(190) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			context varchar(20) NOT NULL DEFAULT 'offline',
			page_url text NULL,
			status varchar(20) NOT NULL DEFAULT 'unread',
			meta longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY context (context),
			KEY session_id (session_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );

		if ( ! get_option( ATSLC_Options::OPTION_KEY ) ) {
			add_option( ATSLC_Options::OPTION_KEY, ATSLC_Options::get_defaults() );
		}
	}

	/**
	 * Placeholder deactivation hook.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Intentionally empty. No scheduled tasks or rewrites to clean up.
	}

	/**
	 * Insert a message record.
	 *
	 * @param array $data Message payload.
	 * @return int|false
	 */
	public static function insert_message( $data ) {
		global $wpdb;

		$defaults = array(
			'session_id'    => wp_generate_password( 20, false, false ),
			'visitor_name'  => '',
			'visitor_email' => '',
			'message'       => '',
			'context'       => 'offline',
			'page_url'      => '',
			'status'        => 'unread',
			'meta'          => '',
			'created_at'    => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$inserted = $wpdb->insert(
			self::get_table_name(),
			array(
				'session_id'    => sanitize_text_field( $data['session_id'] ),
				'visitor_name'  => sanitize_text_field( $data['visitor_name'] ),
				'visitor_email' => sanitize_email( $data['visitor_email'] ),
				'message'       => sanitize_textarea_field( $data['message'] ),
				'context'       => in_array( $data['context'], array( 'online', 'offline' ), true ) ? $data['context'] : 'offline',
				'page_url'      => esc_url_raw( $data['page_url'] ),
				'status'        => in_array( $data['status'], array( 'read', 'unread' ), true ) ? $data['status'] : 'unread',
				'meta'          => is_string( $data['meta'] ) ? wp_json_encode( maybe_unserialize( $data['meta'] ) ) : wp_json_encode( $data['meta'] ),
				'created_at'    => $data['created_at'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch paginated messages.
	 *
	 * @param array $args Query arguments.
	 * @return array
	 */
	public static function get_messages( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => '',
			'offset' => 0,
			'limit'  => 20,
		);

		$args   = wp_parse_args( $args, $defaults );
		$where  = 'WHERE 1=1';
		$params = array();

		if ( in_array( $args['status'], array( 'read', 'unread' ), true ) ) {
			$where    .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$params[] = absint( $args['limit'] );
		$params[] = absint( $args['offset'] );

		$sql = $wpdb->prepare(
			"SELECT * FROM " . self::get_table_name() . " {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
			$params
		);

		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count messages for a status or all messages.
	 *
	 * @param string $status Optional status filter.
	 * @return int
	 */
	public static function count_messages( $status = '' ) {
		global $wpdb;

		if ( in_array( $status, array( 'read', 'unread' ), true ) ) {
			$sql = $wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::get_table_name() . ' WHERE status = %s',
				$status
			);

			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::get_table_name() ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Mark a message as read.
	 *
	 * @param int $message_id Message ID.
	 * @return bool
	 */
	public static function mark_read( $message_id ) {
		global $wpdb;

		return false !== $wpdb->update(
			self::get_table_name(),
			array( 'status' => 'read' ),
			array( 'id' => absint( $message_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Delete a message.
	 *
	 * @param int $message_id Message ID.
	 * @return bool
	 */
	public static function delete_message( $message_id ) {
		global $wpdb;

		return false !== $wpdb->delete(
			self::get_table_name(),
			array( 'id' => absint( $message_id ) ),
			array( '%d' )
		);
	}
}
