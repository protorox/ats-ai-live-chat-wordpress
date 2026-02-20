<?php
/**
 * Data layer for ATS Chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_DB {

	/**
	 * Tracks whether schema check has run for this request.
	 *
	 * @var bool
	 */
	private static $schema_checked = false;

	/**
	 * Current local MySQL datetime string in site timezone.
	 *
	 * @return string
	 */
	public static function now_mysql() {
		return current_time( 'mysql' );
	}

	/**
	 * Convert unix timestamp to local MySQL datetime string using site timezone.
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function mysql_from_unix( $timestamp ) {
		$timestamp = absint( $timestamp );
		if ( ! $timestamp ) {
			$timestamp = time();
		}

		$dt = new DateTime( '@' . $timestamp );
		$dt->setTimezone( wp_timezone() );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Convert local MySQL datetime (site timezone) to unix timestamp.
	 *
	 * @param string $mysql_datetime MySQL datetime.
	 * @return int
	 */
	public static function unix_from_mysql( $mysql_datetime ) {
		$mysql_datetime = (string) $mysql_datetime;
		if ( '' === $mysql_datetime ) {
			return 0;
		}

		$gmt = get_gmt_from_date( $mysql_datetime, 'Y-m-d H:i:s' );
		return $gmt ? strtotime( $gmt . ' UTC' ) : 0;
	}

	/**
	 * Visitors table name.
	 *
	 * @return string
	 */
	public static function visitors_table() {
		global $wpdb;
		return $wpdb->prefix . 'ats_chat_visitors';
	}

	/**
	 * Check if required tables exist.
	 *
	 * @return bool
	 */
	public static function are_tables_ready() {
		global $wpdb;

		$tables = array(
			self::visitors_table(),
			self::conversations_table(),
			self::messages_table(),
			self::events_table(),
			self::leads_table(),
		);

		foreach ( $tables as $table ) {
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Ensure schema exists even if activation hook was skipped on deploy.
	 *
	 * @return void
	 */
	public static function ensure_schema() {
		if ( self::$schema_checked ) {
			return;
		}

		self::$schema_checked = true;
		if ( ! self::are_tables_ready() ) {
			self::create_tables();
		}
	}

	/**
	 * Conversations table name.
	 *
	 * @return string
	 */
	public static function conversations_table() {
		global $wpdb;
		return $wpdb->prefix . 'ats_chat_conversations';
	}

	/**
	 * Messages table name.
	 *
	 * @return string
	 */
	public static function messages_table() {
		global $wpdb;
		return $wpdb->prefix . 'ats_chat_messages';
	}

	/**
	 * Events table name.
	 *
	 * @return string
	 */
	public static function events_table() {
		global $wpdb;
		return $wpdb->prefix . 'ats_chat_events';
	}

	/**
	 * Leads table name.
	 *
	 * @return string
	 */
	public static function leads_table() {
		global $wpdb;
		return $wpdb->prefix . 'ats_chat_leads';
	}

	/**
	 * Create plugin tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$visitors        = self::visitors_table();
		$conversations   = self::conversations_table();
		$messages        = self::messages_table();
		$events          = self::events_table();
		$leads           = self::leads_table();

		$sql = "
		CREATE TABLE {$visitors} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_id varchar(64) NOT NULL,
			name varchar(191) DEFAULT NULL,
			email varchar(191) DEFAULT NULL,
			created_at datetime NOT NULL,
			last_seen datetime NOT NULL,
			current_url text DEFAULT NULL,
			current_title varchar(255) DEFAULT NULL,
			page_history_json longtext DEFAULT NULL,
			cart_json longtext DEFAULT NULL,
			user_agent text DEFAULT NULL,
			referrer text DEFAULT NULL,
			consent tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY visitor_id (visitor_id),
			KEY last_seen (last_seen)
		) {$charset_collate};

		CREATE TABLE {$conversations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id varchar(64) NOT NULL,
			visitor_id varchar(64) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'open',
			assigned_agent_user_id bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY conversation_id (conversation_id),
			KEY visitor_id (visitor_id),
			KEY updated_at (updated_at)
		) {$charset_collate};

		CREATE TABLE {$messages} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			message_id varchar(64) NOT NULL,
			conversation_id varchar(64) NOT NULL,
			sender_type varchar(20) NOT NULL,
			message_type varchar(30) NOT NULL,
			content_text longtext DEFAULT NULL,
			content_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			seen_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY message_id (message_id),
			KEY conversation_id (conversation_id),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$events} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id varchar(64) NOT NULL,
			visitor_id varchar(64) DEFAULT NULL,
			actor_type varchar(20) NOT NULL,
			event_type varchar(30) NOT NULL,
			payload_json text DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY conversation_id (conversation_id),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};

		CREATE TABLE {$leads} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			visitor_id varchar(64) NOT NULL,
			name varchar(191) NOT NULL,
			email varchar(191) NOT NULL,
			message text NOT NULL,
			current_url text DEFAULT NULL,
			created_at datetime NOT NULL,
			handled tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY visitor_id (visitor_id),
			KEY created_at (created_at)
		) {$charset_collate};
		";

		dbDelta( $sql );
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	public static function default_settings() {
		return array(
			'ai_api_key'             => '',
			'ai_model'               => 'gpt-4o-mini',
			'ai_mode'                => 'off',
			'ai_system_prompt'       => 'You are a helpful support agent. Be concise, ask clarifying questions, and never invent shipping, pricing, stock, or policy details unless known from provided WooCommerce/site context.',
			'cookie_notice_enabled'  => 1,
			'cookie_notice_text'     => 'We use a cookie to remember your chat session so we can provide support.',
			'data_retention_days'    => 30,
		);
	}

	/**
	 * Get plugin settings with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_settings() {
		$settings = get_option( 'ats_chat_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Normalize visitor ID.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return string
	 */
	public static function sanitize_visitor_id( $visitor_id ) {
		$visitor_id = (string) $visitor_id;
		$visitor_id = preg_replace( '/[^a-zA-Z0-9\-]/', '', $visitor_id );
		if ( empty( $visitor_id ) ) {
			$visitor_id = wp_generate_uuid4();
		}
		return substr( $visitor_id, 0, 64 );
	}

	/**
	 * Create or update visitor presence and page history.
	 *
	 * @param array<string,mixed> $data Presence payload.
	 * @return array<string,mixed>
	 */
	public static function upsert_visitor_presence( $data ) {
		global $wpdb;
		self::ensure_schema();

		$visitors   = self::visitors_table();
		$timestamp  = self::now_mysql();
		$visitor_id = self::sanitize_visitor_id( isset( $data['visitor_id'] ) ? (string) $data['visitor_id'] : '' );
		$current_url = isset( $data['current_url'] ) ? esc_url_raw( (string) $data['current_url'] ) : '';
		$current_title = isset( $data['current_title'] ) ? sanitize_text_field( (string) $data['current_title'] ) : '';
		$user_agent = isset( $data['user_agent'] ) ? sanitize_textarea_field( (string) $data['user_agent'] ) : '';
		$referrer   = isset( $data['referrer'] ) ? esc_url_raw( (string) $data['referrer'] ) : '';
		$name       = isset( $data['name'] ) ? sanitize_text_field( (string) $data['name'] ) : '';
		$email      = isset( $data['email'] ) ? sanitize_email( (string) $data['email'] ) : '';

		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$visitors} WHERE visitor_id = %s LIMIT 1",
				$visitor_id
			),
			ARRAY_A
		);

		$page_history = array();
		if ( $existing && ! empty( $existing['page_history_json'] ) ) {
			$decoded = json_decode( $existing['page_history_json'], true );
			if ( is_array( $decoded ) ) {
				$page_history = $decoded;
			}
		}

		$page_history = self::append_page_history(
			$page_history,
			$current_url,
			$current_title,
			$timestamp
		);

		$payload = array(
			'last_seen'         => $timestamp,
			'current_url'       => $current_url,
			'current_title'     => $current_title,
			'page_history_json' => wp_json_encode( $page_history ),
			'user_agent'        => $user_agent,
			'referrer'          => $referrer,
		);

		$formats = array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		);

		if ( $name ) {
			$payload['name'] = $name;
			$formats[]       = '%s';
		}

		if ( $email ) {
			$payload['email'] = $email;
			$formats[]        = '%s';
		}

		if ( $existing ) {
			$wpdb->update(
				$visitors,
				$payload,
				array( 'visitor_id' => $visitor_id ),
				$formats,
				array( '%s' )
			);
		} else {
			$payload['visitor_id'] = $visitor_id;
			$payload['created_at'] = $timestamp;
			$insert_formats        = $formats;
			$insert_formats[]      = '%s';
			$insert_formats[]      = '%s';
			$wpdb->insert( $visitors, $payload, $insert_formats );
		}

		$visitor = self::get_visitor( $visitor_id );
		if ( ! $visitor ) {
			$visitor = array(
				'visitor_id'        => $visitor_id,
				'current_url'       => $current_url,
				'current_title'     => $current_title,
				'last_seen'         => $timestamp,
				'page_history_json' => wp_json_encode( $page_history ),
			);
		}

		return $visitor;
	}

	/**
	 * Append page history item and keep the last 10 records.
	 *
	 * @param array<int,array<string,string>> $history Existing history.
	 * @param string                           $url Current URL.
	 * @param string                           $title Current title.
	 * @param string                           $seen_at Datetime.
	 * @return array<int,array<string,string>>
	 */
	public static function append_page_history( $history, $url, $title, $seen_at ) {
		$url   = (string) $url;
		$title = (string) $title;

		if ( empty( $url ) ) {
			return array_slice( is_array( $history ) ? $history : array(), -10 );
		}

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		$last_index = count( $history ) - 1;
		if ( $last_index >= 0 && isset( $history[ $last_index ]['url'] ) && $history[ $last_index ]['url'] === $url ) {
			$history[ $last_index ]['seen_at'] = $seen_at;
			if ( ! empty( $title ) ) {
				$history[ $last_index ]['title'] = $title;
			}
		} else {
			$history[] = array(
				'url'     => $url,
				'title'   => $title,
				'seen_at' => $seen_at,
			);
		}

		return array_slice( $history, -10 );
	}

	/**
	 * Get visitor by ID.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_visitor( $visitor_id ) {
		global $wpdb;
		self::ensure_schema();

		$visitors = self::visitors_table();
		$row      = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$visitors} WHERE visitor_id = %s LIMIT 1",
				self::sanitize_visitor_id( $visitor_id )
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['page_history'] = self::decode_json_array( $row['page_history_json'] );
		$row['cart']         = self::decode_json_array( $row['cart_json'] );

		return $row;
	}

	/**
	 * Save known visitor profile fields.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @param string $name Name.
	 * @param string $email Email.
	 * @return void
	 */
	public static function save_visitor_profile( $visitor_id, $name, $email ) {
		global $wpdb;
		self::ensure_schema();

		$wpdb->update(
			self::visitors_table(),
			array(
				'name'  => sanitize_text_field( $name ),
				'email' => sanitize_email( $email ),
			),
			array( 'visitor_id' => self::sanitize_visitor_id( $visitor_id ) ),
			array( '%s', '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Save cart context for a visitor.
	 *
	 * @param string                               $visitor_id Visitor ID.
	 * @param array<int,array<string,int|string>> $cart_items Cart items.
	 * @return void
	 */
	public static function save_cart_context( $visitor_id, $cart_items ) {
		global $wpdb;
		self::ensure_schema();

		$wpdb->update(
			self::visitors_table(),
			array(
				'cart_json' => wp_json_encode( is_array( $cart_items ) ? $cart_items : array() ),
			),
			array( 'visitor_id' => self::sanitize_visitor_id( $visitor_id ) ),
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Get existing open conversation for visitor or create one.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return array<string,mixed>
	 */
	public static function get_or_create_conversation( $visitor_id ) {
		global $wpdb;
		self::ensure_schema();

		$conversation = self::get_conversation_by_visitor( $visitor_id );
		if ( $conversation ) {
			return $conversation;
		}

		$conversation_id = wp_generate_uuid4();
		$now             = self::now_mysql();

		$wpdb->insert(
			self::conversations_table(),
			array(
				'conversation_id'         => $conversation_id,
				'visitor_id'              => self::sanitize_visitor_id( $visitor_id ),
				'status'                  => 'open',
				'assigned_agent_user_id'  => null,
				'created_at'              => $now,
				'updated_at'              => $now,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		$conversation = self::get_conversation( $conversation_id );
		if ( ! $conversation ) {
			$conversation = array(
				'conversation_id' => $conversation_id,
				'visitor_id'      => self::sanitize_visitor_id( $visitor_id ),
				'status'          => 'open',
				'created_at'      => $now,
				'updated_at'      => $now,
			);
		}

		return $conversation;
	}

	/**
	 * Get open conversation by visitor.
	 *
	 * @param string $visitor_id Visitor ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_conversation_by_visitor( $visitor_id ) {
		global $wpdb;
		self::ensure_schema();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::conversations_table() . " WHERE visitor_id = %s AND status = 'open' ORDER BY updated_at DESC LIMIT 1",
				self::sanitize_visitor_id( $visitor_id )
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Get conversation by conversation ID.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @return array<string,mixed>|null
	 */
	public static function get_conversation( $conversation_id ) {
		global $wpdb;
		self::ensure_schema();

		$conversation_id = self::sanitize_visitor_id( $conversation_id );
		$row             = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::conversations_table() . " WHERE conversation_id = %s LIMIT 1",
				$conversation_id
			),
			ARRAY_A
		);

		return $row ? $row : null;
	}

	/**
	 * Add chat message.
	 *
	 * @param string               $conversation_id Conversation ID.
	 * @param string               $sender_type Sender type.
	 * @param string               $message_type Message type.
	 * @param string               $content_text Text content.
	 * @param array<string,mixed>  $content_json Structured content.
	 * @return array<string,mixed>
	 */
	public static function add_message( $conversation_id, $sender_type, $message_type, $content_text = '', $content_json = array() ) {
		global $wpdb;
		self::ensure_schema();

		$allowed_sender_types = array( 'visitor', 'agent', 'ai', 'system' );
		$allowed_message_types = array( 'text', 'product_card', 'system' );

		if ( ! in_array( $sender_type, $allowed_sender_types, true ) ) {
			$sender_type = 'system';
		}
		if ( ! in_array( $message_type, $allowed_message_types, true ) ) {
			$message_type = 'text';
		}

		$conversation_id = self::sanitize_visitor_id( $conversation_id );
		$message_id      = wp_generate_uuid4();
		$now             = self::now_mysql();
		$content_text    = sanitize_textarea_field( $content_text );
		$content_json    = is_array( $content_json ) ? $content_json : array();

		$wpdb->insert(
			self::messages_table(),
			array(
				'message_id'       => $message_id,
				'conversation_id'  => $conversation_id,
				'sender_type'      => $sender_type,
				'message_type'     => $message_type,
				'content_text'     => $content_text,
				'content_json'     => wp_json_encode( $content_json ),
				'created_at'       => $now,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		$wpdb->update(
			self::conversations_table(),
			array( 'updated_at' => $now ),
			array( 'conversation_id' => $conversation_id ),
			array( '%s' ),
			array( '%s' )
		);

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::messages_table() . " WHERE message_id = %s LIMIT 1",
				$message_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$row = array(
				'message_id'      => $message_id,
				'conversation_id' => $conversation_id,
				'sender_type'     => $sender_type,
				'message_type'    => $message_type,
				'content_text'    => $content_text,
				'content_json'    => wp_json_encode( $content_json ),
				'created_at'      => $now,
			);
		}

		$row['content'] = self::decode_json_object( isset( $row['content_json'] ) ? $row['content_json'] : '{}' );
		$row['ts']      = self::unix_from_mysql( $row['created_at'] );

		return $row;
	}

	/**
	 * List messages in a conversation.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @param int    $since Optional unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_messages( $conversation_id, $since = 0 ) {
		global $wpdb;
		self::ensure_schema();

		$conversation_id = self::sanitize_visitor_id( $conversation_id );
		$query           = "SELECT * FROM " . self::messages_table() . " WHERE conversation_id = %s";
		$params          = array( $conversation_id );

		if ( $since > 0 ) {
			$since_mysql = self::mysql_from_unix( (int) $since );
			$query      .= ' AND created_at > %s';
			$params[]    = $since_mysql;
		}

		$query .= ' ORDER BY created_at ASC LIMIT 200';

		$prepared = $wpdb->prepare( $query, $params );
		$rows     = $wpdb->get_results( $prepared, ARRAY_A );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['content'] = self::decode_json_object( isset( $row['content_json'] ) ? $row['content_json'] : '{}' );
			$row['ts']      = self::unix_from_mysql( $row['created_at'] );
		}

		return $rows;
	}

	/**
	 * Track visitor or agent typing event.
	 *
	 * @param string               $conversation_id Conversation ID.
	 * @param string               $visitor_id Visitor ID.
	 * @param string               $actor_type Actor type.
	 * @param string               $event_type Event type.
	 * @param array<string,mixed>  $payload Payload.
	 * @return void
	 */
	public static function add_event( $conversation_id, $visitor_id, $actor_type, $event_type, $payload = array() ) {
		global $wpdb;
		self::ensure_schema();

		$conversation_id = self::sanitize_visitor_id( $conversation_id );
		$visitor_id      = self::sanitize_visitor_id( $visitor_id );
		$actor_type      = in_array( $actor_type, array( 'visitor', 'agent' ), true ) ? $actor_type : 'visitor';
		$event_type      = sanitize_key( $event_type );

		if ( ! is_array( $payload ) ) {
			$payload = array();
		}

		if ( isset( $payload['preview'] ) ) {
			$payload['preview'] = sanitize_text_field( wp_trim_words( (string) $payload['preview'], 20, '…' ) );
		}

		$wpdb->insert(
			self::events_table(),
			array(
				'conversation_id' => $conversation_id,
				'visitor_id'      => $visitor_id,
				'actor_type'      => $actor_type,
				'event_type'      => $event_type,
				'payload_json'    => wp_json_encode( $payload ),
				'created_at'      => self::now_mysql(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Typing state for current conversation based on latest events.
	 *
	 * @param string $conversation_id Conversation ID.
	 * @param string $viewer_type viewer type.
	 * @return array<string,mixed>
	 */
	public static function get_typing_state( $conversation_id, $viewer_type ) {
		global $wpdb;
		self::ensure_schema();

		$target_actor = ( 'agent' === $viewer_type ) ? 'visitor' : 'agent';
		$cutoff       = self::mysql_from_unix( time() - 6 );
		$row          = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM " . self::events_table() . " WHERE conversation_id = %s AND actor_type = %s AND event_type = 'typing' AND created_at >= %s ORDER BY created_at DESC LIMIT 1",
				self::sanitize_visitor_id( $conversation_id ),
				$target_actor,
				$cutoff
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return array(
				'is_typing' => false,
				'preview'   => '',
				'actor'     => $target_actor,
				'ts'        => 0,
			);
		}

		$payload = self::decode_json_object( isset( $row['payload_json'] ) ? $row['payload_json'] : '{}' );
		$preview = isset( $payload['preview'] ) ? sanitize_text_field( (string) $payload['preview'] ) : '';

		return array(
			'is_typing' => true,
			'preview'   => $preview,
			'actor'     => $target_actor,
			'ts'        => self::unix_from_mysql( $row['created_at'] ),
		);
	}

	/**
	 * Get active visitors list for admin panel.
	 *
	 * @param int $since Optional unix timestamp.
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_live_visitors( $since = 0 ) {
		global $wpdb;
		self::ensure_schema();

		$active_cutoff = self::mysql_from_unix( time() - 120 );
		$rows          = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.*, c.conversation_id, c.updated_at AS conversation_updated_at
				 FROM " . self::visitors_table() . " v
				 LEFT JOIN " . self::conversations_table() . " c ON c.visitor_id = v.visitor_id AND c.status = 'open'
				 WHERE v.last_seen >= %s
				 ORDER BY v.last_seen DESC
				 LIMIT 250",
				$active_cutoff
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $rows as $row ) {
			$last_seen_ts = self::unix_from_mysql( $row['last_seen'] );
			$updated_ts   = ! empty( $row['conversation_updated_at'] ) ? self::unix_from_mysql( $row['conversation_updated_at'] ) : 0;
			if ( $since > 0 && $last_seen_ts <= $since && $updated_ts <= $since ) {
				continue;
			}
			$row['page_history'] = self::decode_json_array( $row['page_history_json'] );
			$row['cart']         = self::decode_json_array( $row['cart_json'] );
			$row['last_seen_ts'] = $last_seen_ts;
			$filtered[]          = $row;
		}

		return $filtered;
	}

	/**
	 * Save an offline lead.
	 *
	 * @param array<string,string> $data Lead payload.
	 * @return void
	 */
	public static function save_lead( $data ) {
		global $wpdb;
		self::ensure_schema();

		$visitor_id = self::sanitize_visitor_id( isset( $data['visitor_id'] ) ? (string) $data['visitor_id'] : '' );
		$name       = sanitize_text_field( isset( $data['name'] ) ? (string) $data['name'] : '' );
		$email      = sanitize_email( isset( $data['email'] ) ? (string) $data['email'] : '' );
		$message    = sanitize_textarea_field( isset( $data['message'] ) ? (string) $data['message'] : '' );
		$current_url = esc_url_raw( isset( $data['current_url'] ) ? (string) $data['current_url'] : '' );

		$wpdb->insert(
			self::leads_table(),
			array(
				'visitor_id'  => $visitor_id,
				'name'        => $name,
				'email'       => $email,
				'message'     => $message,
				'current_url' => $current_url,
				'created_at'  => self::now_mysql(),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( $visitor_id && ( $name || $email ) ) {
			self::save_visitor_profile( $visitor_id, $name, $email );
		}
	}

	/**
	 * Mark agent user online.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function mark_agent_online( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$presence            = get_option( 'ats_chat_agent_presence', array() );
		$presence            = is_array( $presence ) ? $presence : array();
		$presence[ $user_id ] = time();
		update_option( 'ats_chat_agent_presence', $presence, false );
	}

	/**
	 * Get currently online agents.
	 *
	 * @return array<int,int>
	 */
	public static function get_online_agents() {
		$presence = get_option( 'ats_chat_agent_presence', array() );
		$presence = is_array( $presence ) ? $presence : array();
		$cutoff   = time() - 120;
		$online   = array();
		$changed  = false;

		foreach ( $presence as $user_id => $timestamp ) {
			$user_id    = absint( $user_id );
			$timestamp  = absint( $timestamp );
			if ( $timestamp >= $cutoff ) {
				$online[ $user_id ] = $timestamp;
			} else {
				$changed = true;
			}
		}

		if ( $changed ) {
			update_option( 'ats_chat_agent_presence', $online, false );
		}

		return $online;
	}

	/**
	 * Whether at least one agent is online.
	 *
	 * @return bool
	 */
	public static function has_online_agents() {
		$online = self::get_online_agents();
		return ! empty( $online );
	}

	/**
	 * Run retention cleanup.
	 *
	 * @param int $days Retention days.
	 * @return void
	 */
	public static function retention_cleanup( $days ) {
		global $wpdb;

		$days   = max( 1, absint( $days ) );
		$cutoff = self::mysql_from_unix( time() - ( $days * DAY_IN_SECONDS ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::messages_table() . " WHERE created_at < %s",
				$cutoff
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::events_table() . " WHERE created_at < %s",
				$cutoff
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::leads_table() . " WHERE created_at < %s",
				$cutoff
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE c FROM " . self::conversations_table() . " c
				 LEFT JOIN " . self::messages_table() . " m ON m.conversation_id = c.conversation_id
				 WHERE c.updated_at < %s AND m.id IS NULL",
				$cutoff
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE v FROM " . self::visitors_table() . " v
				 LEFT JOIN " . self::conversations_table() . " c ON c.visitor_id = v.visitor_id
				 WHERE v.last_seen < %s AND c.id IS NULL",
				$cutoff
			)
		);
	}

	/**
	 * Decode JSON object into array.
	 *
	 * @param string $json JSON value.
	 * @return array<string,mixed>
	 */
	public static function decode_json_object( $json ) {
		$decoded = json_decode( (string) $json, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Decode JSON list into indexed array.
	 *
	 * @param string $json JSON value.
	 * @return array<int,mixed>
	 */
	public static function decode_json_array( $json ) {
		$decoded = json_decode( (string) $json, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		return array_values( $decoded );
	}
}
