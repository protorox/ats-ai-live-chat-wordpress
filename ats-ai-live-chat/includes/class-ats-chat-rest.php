<?php
/**
 * REST API controller for ATS Chat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_REST {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'ats-chat/v1';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/presence',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'presence' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'visitor_message' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit_lead' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/typing',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'typing' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/visitors',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'visitors' ),
				'permission_callback' => array( $this, 'agent_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/conversation',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'conversation' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/messages',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'messages' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			$this->namespace,
			'/agent/message',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'agent_message' ),
				'permission_callback' => array( $this, 'agent_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/products/search',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'products_search' ),
				'permission_callback' => array( $this, 'agent_permission' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/ai/reply',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'ai_reply' ),
				'permission_callback' => array( $this, 'agent_permission' ),
			)
		);
	}

	/**
	 * Admin route permission callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public function agent_permission( $request ) {
		unset( $request );

		if ( ! ATS_Chat_Plugin::user_can_agent() ) {
			return new WP_Error( 'ats_chat_forbidden', __( 'Insufficient permissions.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
		}

		$nonce = $this->get_wp_rest_nonce();
		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			return new WP_Error( 'ats_chat_bad_nonce', __( 'Invalid REST nonce.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Handle visitor presence updates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function presence( $request ) {
		$nonce_check = $this->assert_public_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$params = $this->request_params( $request );

		$visitor = ATS_Chat_DB::upsert_visitor_presence(
			array(
				'visitor_id'    => isset( $params['visitor_id'] ) ? $params['visitor_id'] : '',
				'current_url'   => isset( $params['current_url'] ) ? $params['current_url'] : '',
				'current_title' => isset( $params['current_title'] ) ? $params['current_title'] : '',
				'user_agent'    => isset( $params['user_agent'] ) ? $params['user_agent'] : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ),
				'referrer'      => isset( $params['referrer'] ) ? $params['referrer'] : ( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' ),
				'name'          => isset( $params['name'] ) ? $params['name'] : '',
				'email'         => isset( $params['email'] ) ? $params['email'] : '',
			)
		);

		$conversation = ATS_Chat_DB::get_or_create_conversation( $visitor['visitor_id'] );
		$settings     = ATS_Chat_DB::get_settings();
		$agents_online = ATS_Chat_DB::has_online_agents();
		$diag         = ATS_Chat_DB::diagnostics();

		return rest_ensure_response(
			array(
				'visitor_id'             => $visitor['visitor_id'],
				'conversation_id'        => $conversation['conversation_id'],
				'agents_online'          => $agents_online,
				'ai_mode'                => sanitize_key( $settings['ai_mode'] ),
				'show_offline_lead_form' => ( ! $agents_online && 'auto' !== $settings['ai_mode'] ),
				'cookie_notice_enabled'  => ! empty( $settings['cookie_notice_enabled'] ),
				'cookie_notice_text'     => sanitize_text_field( (string) $settings['cookie_notice_text'] ),
				'server_ts'              => time(),
				'plugin_version'         => ATS_CHAT_VERSION,
				'tables_ready'           => ! empty( $diag['tables_ready'] ),
			)
		);
	}

	/**
	 * Visitor sends a message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function visitor_message( $request ) {
		$nonce_check = $this->assert_public_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$params    = $this->request_params( $request );
		$visitor_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['visitor_id'] ) ? (string) $params['visitor_id'] : '' );
		$message   = sanitize_textarea_field( isset( $params['message'] ) ? (string) $params['message'] : '' );
		$message   = trim( $message );

		if ( '' === $message ) {
			return new WP_Error( 'ats_chat_empty_message', __( 'Message cannot be empty.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		if ( empty( $visitor_id ) ) {
			$visitor_id = wp_generate_uuid4();
		}

		ATS_Chat_DB::upsert_visitor_presence(
			array(
				'visitor_id'    => $visitor_id,
				'current_url'   => isset( $params['current_url'] ) ? $params['current_url'] : '',
				'current_title' => isset( $params['current_title'] ) ? $params['current_title'] : '',
				'user_agent'    => isset( $params['user_agent'] ) ? $params['user_agent'] : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '' ),
				'referrer'      => isset( $params['referrer'] ) ? $params['referrer'] : ( isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '' ),
			)
		);

		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$conversation    = $conversation_id ? ATS_Chat_DB::get_conversation( $conversation_id ) : null;
		if ( ! $conversation || $conversation['visitor_id'] !== $visitor_id ) {
			$conversation = ATS_Chat_DB::get_or_create_conversation( $visitor_id );
		}

		$stored_message = ATS_Chat_DB::add_message( $conversation['conversation_id'], 'visitor', 'text', $message, array() );
		$ai_message     = ATS_Chat_AI::maybe_auto_reply( $conversation['conversation_id'] );
		$settings       = ATS_Chat_DB::get_settings();
		$agents_online  = ATS_Chat_DB::has_online_agents();

		$response = array(
			'conversation_id' => $conversation['conversation_id'],
			'message'         => $this->format_message( $stored_message ),
			'agents_online'   => $agents_online,
			'ai_mode'         => sanitize_key( $settings['ai_mode'] ),
			'server_ts'       => time(),
		);

		if ( $ai_message ) {
			$response['ai_message'] = $this->format_message( $ai_message );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Submit offline lead form.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function submit_lead( $request ) {
		$nonce_check = $this->assert_public_nonce( $request );
		if ( is_wp_error( $nonce_check ) ) {
			return $nonce_check;
		}

		$params = $this->request_params( $request );
		$name   = sanitize_text_field( isset( $params['name'] ) ? (string) $params['name'] : '' );
		$email  = sanitize_email( isset( $params['email'] ) ? (string) $params['email'] : '' );
		$message = sanitize_textarea_field( isset( $params['message'] ) ? (string) $params['message'] : '' );

		if ( '' === $name || '' === $email || '' === $message ) {
			return new WP_Error( 'ats_chat_invalid_lead', __( 'Name, email, and message are required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'ats_chat_invalid_email', __( 'Invalid email format.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$visitor_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['visitor_id'] ) ? (string) $params['visitor_id'] : '' );
		if ( empty( $visitor_id ) ) {
			$visitor_id = wp_generate_uuid4();
		}

		ATS_Chat_DB::upsert_visitor_presence(
			array(
				'visitor_id'    => $visitor_id,
				'current_url'   => isset( $params['current_url'] ) ? $params['current_url'] : '',
				'current_title' => isset( $params['current_title'] ) ? $params['current_title'] : '',
				'name'          => $name,
				'email'         => $email,
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
				'referrer'      => isset( $_SERVER['HTTP_REFERER'] ) ? wp_unslash( $_SERVER['HTTP_REFERER'] ) : '',
			)
		);

		ATS_Chat_DB::save_lead(
			array(
				'visitor_id'  => $visitor_id,
				'name'        => $name,
				'email'       => $email,
				'message'     => $message,
				'current_url' => isset( $params['current_url'] ) ? $params['current_url'] : '',
			)
		);

		$conversation = ATS_Chat_DB::get_or_create_conversation( $visitor_id );
		$msg_text     = sprintf( 'Offline lead from %s (%s): %s', $name, $email, $message );
		ATS_Chat_DB::add_message( $conversation['conversation_id'], 'visitor', 'text', $msg_text, array( 'is_lead' => true ) );

		return rest_ensure_response(
			array(
				'success'         => true,
				'visitor_id'      => $visitor_id,
				'conversation_id' => $conversation['conversation_id'],
			)
		);
	}

	/**
	 * Typing endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function typing( $request ) {
		$params         = $this->request_params( $request );
		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$visitor_id     = ATS_Chat_DB::sanitize_visitor_id( isset( $params['visitor_id'] ) ? (string) $params['visitor_id'] : '' );
		$preview        = sanitize_text_field( isset( $params['preview'] ) ? (string) $params['preview'] : '' );
		$preview        = function_exists( 'mb_substr' ) ? mb_substr( $preview, 0, 240 ) : substr( $preview, 0, 240 );

		if ( empty( $conversation_id ) ) {
			return new WP_Error( 'ats_chat_missing_conversation', __( 'conversation_id is required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$conversation = ATS_Chat_DB::get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'ats_chat_conversation_not_found', __( 'Conversation not found.', 'ats-ai-live-chat' ), array( 'status' => 404 ) );
		}

		$is_agent = $this->is_agent_request();
		$actor    = 'visitor';

		if ( $is_agent ) {
			$actor = 'agent';
			ATS_Chat_DB::mark_agent_online( get_current_user_id() );
			if ( empty( $visitor_id ) ) {
				$visitor_id = $conversation['visitor_id'];
			}
		} else {
			$nonce_check = $this->assert_public_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}
			if ( empty( $visitor_id ) || $conversation['visitor_id'] !== $visitor_id ) {
				return new WP_Error( 'ats_chat_forbidden', __( 'Conversation does not belong to this visitor.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
			}
		}

		ATS_Chat_DB::add_event(
			$conversation_id,
			$visitor_id,
			$actor,
			'typing',
			array(
				'preview' => $preview,
			)
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'actor'   => $actor,
				'ts'      => time(),
			)
		);
	}

	/**
	 * Get live visitors for admin panel.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function visitors( $request ) {
		ATS_Chat_DB::mark_agent_online( get_current_user_id() );

		$since    = absint( $request->get_param( 'since' ) );
		$visitors = ATS_Chat_DB::get_live_visitors( $since );
		$data     = array();

		foreach ( $visitors as $visitor ) {
			$conversation_id = ! empty( $visitor['conversation_id'] ) ? $visitor['conversation_id'] : '';
			if ( empty( $conversation_id ) ) {
				$conversation = ATS_Chat_DB::get_or_create_conversation( $visitor['visitor_id'] );
				$conversation_id = $conversation['conversation_id'];
			}

			$last_seen_ts = ! empty( $visitor['last_seen_ts'] ) ? absint( $visitor['last_seen_ts'] ) : ATS_Chat_DB::unix_from_mysql( $visitor['last_seen'] );
			$ago          = max( 0, time() - $last_seen_ts );
			$device       = $this->parse_user_agent( isset( $visitor['user_agent'] ) ? (string) $visitor['user_agent'] : '' );

			$data[] = array(
				'visitor_id'       => $visitor['visitor_id'],
				'conversation_id'  => $conversation_id,
				'name'             => ! empty( $visitor['name'] ) ? $visitor['name'] : 'Anonymous',
				'email'            => ! empty( $visitor['email'] ) ? $visitor['email'] : '',
				'current_url'      => ! empty( $visitor['current_url'] ) ? $visitor['current_url'] : '',
				'current_title'    => ! empty( $visitor['current_title'] ) ? $visitor['current_title'] : '',
				'last_seen'        => $visitor['last_seen'],
				'last_seen_ts'     => $last_seen_ts,
				'last_seen_ago'    => $ago,
				'user_agent'       => isset( $visitor['user_agent'] ) ? $visitor['user_agent'] : '',
				'device'           => $device,
				'referrer'         => isset( $visitor['referrer'] ) ? $visitor['referrer'] : '',
				'page_history'     => isset( $visitor['page_history'] ) ? $visitor['page_history'] : array(),
				'cart'             => isset( $visitor['cart'] ) ? $visitor['cart'] : array(),
				'conversation_ts'  => ! empty( $visitor['conversation_updated_at'] ) ? ATS_Chat_DB::unix_from_mysql( $visitor['conversation_updated_at'] ) : 0,
			);
		}

		return rest_ensure_response(
			array(
				'visitors'          => $data,
				'online_agents'     => count( ATS_Chat_DB::get_online_agents() ),
				'server_ts'         => time(),
				'plugin_version'    => ATS_CHAT_VERSION,
				'diagnostics'       => ATS_Chat_DB::diagnostics(),
			)
		);
	}

	/**
	 * Get conversation details.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function conversation( $request ) {
		$params = $this->request_params( $request );

		$is_agent = $this->is_agent_request();
		if ( $is_agent ) {
			ATS_Chat_DB::mark_agent_online( get_current_user_id() );
		} else {
			$nonce_check = $this->assert_public_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}
		}

		$visitor_id      = ATS_Chat_DB::sanitize_visitor_id( isset( $params['visitor_id'] ) ? (string) $params['visitor_id'] : '' );
		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$conversation    = null;

		if ( $conversation_id ) {
			$conversation = ATS_Chat_DB::get_conversation( $conversation_id );
		}
		if ( ! $conversation && $visitor_id ) {
			$conversation = ATS_Chat_DB::get_or_create_conversation( $visitor_id );
		}

		if ( ! $conversation ) {
			return new WP_Error( 'ats_chat_not_found', __( 'Conversation not found.', 'ats-ai-live-chat' ), array( 'status' => 404 ) );
		}

		if ( ! $is_agent ) {
			if ( empty( $visitor_id ) || $conversation['visitor_id'] !== $visitor_id ) {
				return new WP_Error( 'ats_chat_forbidden', __( 'Conversation access denied.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
			}
		}

		$visitor = ATS_Chat_DB::get_visitor( $conversation['visitor_id'] );
		if ( ! $visitor ) {
			$visitor = array(
				'visitor_id'    => $conversation['visitor_id'],
				'name'          => '',
				'email'         => '',
				'current_url'   => '',
				'current_title' => '',
				'last_seen'     => '',
				'page_history'  => array(),
				'cart'          => array(),
				'user_agent'    => '',
				'referrer'      => '',
			);
		}

		return rest_ensure_response(
			array(
				'conversation' => array(
					'conversation_id' => $conversation['conversation_id'],
					'visitor_id'      => $conversation['visitor_id'],
					'status'          => $conversation['status'],
					'updated_at'      => $conversation['updated_at'],
				),
				'visitor'      => array(
					'visitor_id'    => $visitor['visitor_id'],
					'name'          => ! empty( $visitor['name'] ) ? $visitor['name'] : 'Anonymous',
					'email'         => ! empty( $visitor['email'] ) ? $visitor['email'] : '',
					'current_url'   => ! empty( $visitor['current_url'] ) ? $visitor['current_url'] : '',
					'current_title' => ! empty( $visitor['current_title'] ) ? $visitor['current_title'] : '',
					'last_seen'     => ! empty( $visitor['last_seen'] ) ? $visitor['last_seen'] : '',
					'last_seen_ts'  => ! empty( $visitor['last_seen'] ) ? ATS_Chat_DB::unix_from_mysql( $visitor['last_seen'] ) : 0,
					'page_history'  => isset( $visitor['page_history'] ) ? $visitor['page_history'] : array(),
					'cart'          => isset( $visitor['cart'] ) ? $visitor['cart'] : array(),
					'user_agent'    => isset( $visitor['user_agent'] ) ? $visitor['user_agent'] : '',
					'device'        => $this->parse_user_agent( isset( $visitor['user_agent'] ) ? (string) $visitor['user_agent'] : '' ),
					'referrer'      => isset( $visitor['referrer'] ) ? $visitor['referrer'] : '',
				),
				'server_ts'    => time(),
			)
		);
	}

	/**
	 * Get conversation messages and typing state.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function messages( $request ) {
		$params          = $this->request_params( $request );
		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$since           = absint( isset( $params['since'] ) ? $params['since'] : 0 );

		if ( empty( $conversation_id ) ) {
			return new WP_Error( 'ats_chat_missing_conversation', __( 'conversation_id is required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$conversation = ATS_Chat_DB::get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'ats_chat_conversation_not_found', __( 'Conversation not found.', 'ats-ai-live-chat' ), array( 'status' => 404 ) );
		}

		$is_agent = $this->is_agent_request();
		if ( $is_agent ) {
			ATS_Chat_DB::mark_agent_online( get_current_user_id() );
		} else {
			$nonce_check = $this->assert_public_nonce( $request );
			if ( is_wp_error( $nonce_check ) ) {
				return $nonce_check;
			}
			$visitor_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['visitor_id'] ) ? (string) $params['visitor_id'] : '' );
			if ( empty( $visitor_id ) || $conversation['visitor_id'] !== $visitor_id ) {
				return new WP_Error( 'ats_chat_forbidden', __( 'Conversation access denied.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
			}
		}

		$messages = ATS_Chat_DB::get_messages( $conversation_id, $since );
		$formatted = array();
		foreach ( $messages as $message ) {
			$formatted[] = $this->format_message( $message );
		}

		$typing = ATS_Chat_DB::get_typing_state( $conversation_id, $is_agent ? 'agent' : 'visitor' );

		return rest_ensure_response(
			array(
				'messages'      => $formatted,
				'typing'        => $typing,
				'agents_online' => ATS_Chat_DB::has_online_agents(),
				'server_ts'     => time(),
			)
		);
	}

	/**
	 * Agent sends message or product card.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function agent_message( $request ) {
		ATS_Chat_DB::mark_agent_online( get_current_user_id() );

		$params          = $this->request_params( $request );
		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$message_type    = sanitize_key( isset( $params['message_type'] ) ? (string) $params['message_type'] : 'text' );

		if ( empty( $conversation_id ) ) {
			return new WP_Error( 'ats_chat_missing_conversation', __( 'conversation_id is required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$conversation = ATS_Chat_DB::get_conversation( $conversation_id );
		if ( ! $conversation ) {
			return new WP_Error( 'ats_chat_conversation_not_found', __( 'Conversation not found.', 'ats-ai-live-chat' ), array( 'status' => 404 ) );
		}

		$message = null;

		if ( 'product_card' === $message_type ) {
			if ( ! class_exists( 'WooCommerce' ) ) {
				return new WP_Error( 'ats_chat_woo_required', __( 'WooCommerce is required for product cards.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
			}
			$product_id = absint( isset( $params['product_id'] ) ? $params['product_id'] : 0 );
			if ( ! $product_id ) {
				return new WP_Error( 'ats_chat_product_required', __( 'Valid product_id is required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
			}

			$product_card = ATS_Chat_WooCommerce::build_product_card( $product_id );
			if ( empty( $product_card ) ) {
				return new WP_Error( 'ats_chat_product_not_found', __( 'Product not found.', 'ats-ai-live-chat' ), array( 'status' => 404 ) );
			}

			$message = ATS_Chat_DB::add_message( $conversation_id, 'agent', 'product_card', '', $product_card );
		} else {
			$text = sanitize_textarea_field( isset( $params['message'] ) ? (string) $params['message'] : '' );
			$text = trim( $text );
			if ( '' === $text ) {
				return new WP_Error( 'ats_chat_empty_message', __( 'Message cannot be empty.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
			}

			$message = ATS_Chat_DB::add_message( $conversation_id, 'agent', 'text', $text, array() );
		}

		return rest_ensure_response(
			array(
				'message'   => $this->format_message( $message ),
				'server_ts' => time(),
			)
		);
	}

	/**
	 * WooCommerce product search endpoint.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function products_search( $request ) {
		ATS_Chat_DB::mark_agent_online( get_current_user_id() );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_Error( 'ats_chat_woo_required', __( 'WooCommerce is not active.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$query   = sanitize_text_field( (string) $request->get_param( 'q' ) );
		$results = ATS_Chat_WooCommerce::search_products( $query );

		return rest_ensure_response(
			array(
				'results'   => $results,
				'server_ts' => time(),
			)
		);
	}

	/**
	 * Generate AI draft or send AI message.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function ai_reply( $request ) {
		ATS_Chat_DB::mark_agent_online( get_current_user_id() );

		$settings = ATS_Chat_DB::get_settings();
		if ( 'off' === $settings['ai_mode'] ) {
			return new WP_Error( 'ats_chat_ai_disabled', __( 'AI mode is currently off.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$params          = $this->request_params( $request );
		$conversation_id = ATS_Chat_DB::sanitize_visitor_id( isset( $params['conversation_id'] ) ? (string) $params['conversation_id'] : '' );
		$send            = ! empty( $params['send'] );

		if ( empty( $conversation_id ) ) {
			return new WP_Error( 'ats_chat_missing_conversation', __( 'conversation_id is required.', 'ats-ai-live-chat' ), array( 'status' => 400 ) );
		}

		$reply = ATS_Chat_AI::generate_reply_text( $conversation_id );
		if ( is_wp_error( $reply ) ) {
			$reply->add_data( array( 'status' => 500 ) );
			return $reply;
		}

		if ( $send ) {
			$message = ATS_Chat_DB::add_message(
				$conversation_id,
				'ai',
				'text',
				$reply,
				array(
					'mode' => 'manual_send',
				)
			);

			return rest_ensure_response(
				array(
					'sent'      => true,
					'message'   => $this->format_message( $message ),
					'draft'     => $reply,
					'server_ts' => time(),
				)
			);
		}

		return rest_ensure_response(
			array(
				'sent'      => false,
				'draft'     => $reply,
				'server_ts' => time(),
			)
		);
	}

	/**
	 * Normalize request parameters from JSON/body/query.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array<string,mixed>
	 */
	private function request_params( $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$merged = array_merge( $request->get_params(), $params );
		return is_array( $merged ) ? $merged : array();
	}

	/**
	 * Get current request wp_rest nonce from headers.
	 *
	 * @return string
	 */
	private function get_wp_rest_nonce() {
		$nonce = '';
		if ( isset( $_SERVER['HTTP_X_WP_NONCE'] ) ) {
			$nonce = wp_unslash( $_SERVER['HTTP_X_WP_NONCE'] );
		}
		return sanitize_text_field( $nonce );
	}

	/**
	 * Validate visitor-side nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function assert_public_nonce( $request ) {
		$params = $this->request_params( $request );
		$nonce  = '';

		if ( isset( $params['nonce'] ) ) {
			$nonce = sanitize_text_field( (string) $params['nonce'] );
		}

		if ( empty( $nonce ) && isset( $_SERVER['HTTP_X_ATS_CHAT_NONCE'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ATS_CHAT_NONCE'] ) );
		}

		if ( ! ATS_Chat_Plugin::verify_public_nonce( $nonce ) ) {
			return new WP_Error( 'ats_chat_bad_nonce', __( 'Invalid chat nonce.', 'ats-ai-live-chat' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Whether request is authenticated agent request.
	 *
	 * @return bool
	 */
	private function is_agent_request() {
		if ( ! is_user_logged_in() || ! ATS_Chat_Plugin::user_can_agent() ) {
			return false;
		}

		$nonce = $this->get_wp_rest_nonce();
		return ( ! empty( $nonce ) && wp_verify_nonce( $nonce, 'wp_rest' ) );
	}

	/**
	 * Format stored message for API output.
	 *
	 * @param array<string,mixed> $message Message row.
	 * @return array<string,mixed>
	 */
	private function format_message( $message ) {
		$content = isset( $message['content'] ) && is_array( $message['content'] )
			? $message['content']
			: ATS_Chat_DB::decode_json_object( isset( $message['content_json'] ) ? (string) $message['content_json'] : '{}' );

		return array(
			'message_id'      => isset( $message['message_id'] ) ? sanitize_text_field( (string) $message['message_id'] ) : '',
			'conversation_id' => isset( $message['conversation_id'] ) ? sanitize_text_field( (string) $message['conversation_id'] ) : '',
			'sender_type'     => isset( $message['sender_type'] ) ? sanitize_key( (string) $message['sender_type'] ) : 'system',
			'message_type'    => isset( $message['message_type'] ) ? sanitize_key( (string) $message['message_type'] ) : 'text',
			'content_text'    => isset( $message['content_text'] ) ? (string) $message['content_text'] : '',
			'content'         => $content,
			'created_at'      => isset( $message['created_at'] ) ? sanitize_text_field( (string) $message['created_at'] ) : '',
			'ts'              => isset( $message['ts'] ) ? absint( $message['ts'] ) : ( isset( $message['created_at'] ) ? strtotime( (string) $message['created_at'] ) : 0 ),
		);
	}

	/**
	 * Parse user agent lightly for visitor list.
	 *
	 * @param string $user_agent User agent.
	 * @return array<string,string>
	 */
	private function parse_user_agent( $user_agent ) {
		$user_agent_lc = strtolower( $user_agent );

		$device = 'Desktop';
		if ( false !== strpos( $user_agent_lc, 'mobile' ) || false !== strpos( $user_agent_lc, 'iphone' ) || false !== strpos( $user_agent_lc, 'android' ) ) {
			$device = 'Mobile';
		}
		if ( false !== strpos( $user_agent_lc, 'ipad' ) || false !== strpos( $user_agent_lc, 'tablet' ) ) {
			$device = 'Tablet';
		}

		$browser = 'Unknown';
		if ( false !== strpos( $user_agent_lc, 'edg' ) ) {
			$browser = 'Edge';
		} elseif ( false !== strpos( $user_agent_lc, 'chrome' ) ) {
			$browser = 'Chrome';
		} elseif ( false !== strpos( $user_agent_lc, 'safari' ) ) {
			$browser = 'Safari';
		} elseif ( false !== strpos( $user_agent_lc, 'firefox' ) ) {
			$browser = 'Firefox';
		}

		$os = 'Unknown OS';
		if ( false !== strpos( $user_agent_lc, 'windows' ) ) {
			$os = 'Windows';
		} elseif ( false !== strpos( $user_agent_lc, 'mac os' ) || false !== strpos( $user_agent_lc, 'macintosh' ) ) {
			$os = 'macOS';
		} elseif ( false !== strpos( $user_agent_lc, 'android' ) ) {
			$os = 'Android';
		} elseif ( false !== strpos( $user_agent_lc, 'iphone' ) || false !== strpos( $user_agent_lc, 'ipad' ) || false !== strpos( $user_agent_lc, 'ios' ) ) {
			$os = 'iOS';
		} elseif ( false !== strpos( $user_agent_lc, 'linux' ) ) {
			$os = 'Linux';
		}

		return array(
			'device'  => $device,
			'browser' => $browser,
			'os'      => $os,
			'label'   => trim( $device . ' • ' . $browser . ' • ' . $os ),
		);
	}
}
