<?php
/**
 * Front-end widget functionality.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public controller.
 */
class ATSLC_Public {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
		add_action( 'wp_ajax_atslc_send_message', array( $this, 'handle_send_message' ) );
		add_action( 'wp_ajax_nopriv_atslc_send_message', array( $this, 'handle_send_message' ) );
	}

	/**
	 * Enqueue front-end assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! ATSLC_Helpers::should_render_widget() ) {
			return;
		}

		$settings      = ATSLC_Options::get();
		$online_status = ATSLC_Helpers::get_online_status( $settings );
		$current_user  = wp_get_current_user();
		$profile       = array(
			'name'  => $current_user instanceof WP_User && $current_user->exists() ? $current_user->display_name : '',
			'email' => $current_user instanceof WP_User && $current_user->exists() ? $current_user->user_email : '',
		);
		$initial_messages = array();

		if ( ! empty( $settings['show_welcome'] ) && ! empty( $settings['welcome_message'] ) ) {
			$initial_messages[] = array(
				'role'    => 'agent',
				'message' => $settings['welcome_message'],
			);
		}

		wp_enqueue_style(
			'atslc-public',
			ATSLC_PLUGIN_URL . 'assets/css/public.css',
			array(),
			ATSLC_VERSION
		);

		if ( ! empty( $settings['custom_css'] ) ) {
			wp_add_inline_style( 'atslc-public', $settings['custom_css'] );
		}

		wp_enqueue_script(
			'atslc-public',
			ATSLC_PLUGIN_URL . 'assets/js/public.js',
			array(),
			ATSLC_VERSION,
			true
		);

		wp_localize_script(
			'atslc-public',
			'atslcConfig',
			array(
				'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
				'nonce'            => wp_create_nonce( 'atslc_chat_nonce' ),
				'isOnline'         => (bool) $online_status['is_online'],
				'statusLabel'      => $online_status['label'],
				'pageUrl'          => ATSLC_Helpers::get_current_page_url(),
				'autoOpenSeconds'  => absint( $settings['auto_open_seconds'] ),
				'soundEnabled'     => ! empty( $settings['sound_notifications'] ),
				'initialMessages'  => $initial_messages,
				'profile'          => $profile,
				'strings'          => array(
					'startChatError' => __( 'Please enter your name to start the chat.', 'ats-live-chat' ),
					'offlineError'   => __( 'Please complete your name, email, and message.', 'ats-live-chat' ),
					'messageError'   => __( 'Please enter a message before sending.', 'ats-live-chat' ),
					'submitError'    => __( 'We could not send your message. Please try again.', 'ats-live-chat' ),
					'chatReady'      => __( 'You are connected. Send your message below.', 'ats-live-chat' ),
					'offlineThanks'  => __( 'Thanks. Your message has been received.', 'ats-live-chat' ),
				),
			)
		);
	}

	/**
	 * Render the widget markup.
	 *
	 * @return void
	 */
	public function render_widget() {
		if ( ! ATSLC_Helpers::should_render_widget() ) {
			return;
		}

		$settings      = ATSLC_Options::get();
		$online_status = ATSLC_Helpers::get_online_status( $settings );
		$avatar_url    = ATSLC_Helpers::get_agent_avatar_url( $settings );
		$css_vars      = ATSLC_Helpers::get_css_variables( $settings );
		$classes       = array(
			'atslc-widget-shell',
			'atslc-position-' . $settings['position'],
		);

		if ( ! empty( $settings['hide_on_mobile'] ) ) {
			$classes[] = 'atslc-hide-mobile';
		}

		if ( ! empty( $settings['hide_on_desktop'] ) ) {
			$classes[] = 'atslc-hide-desktop';
		}

		include ATSLC_PLUGIN_DIR . 'public/views/widget.php';
	}

	/**
	 * Handle AJAX message submissions.
	 *
	 * @return void
	 */
	public function handle_send_message() {
		check_ajax_referer( 'atslc_chat_nonce', 'nonce' );

		$settings      = ATSLC_Options::get();
		$online_status = ATSLC_Helpers::get_online_status( $settings );
		$is_online     = ! empty( $online_status['is_online'] );

		$visitor_name  = isset( $_POST['visitor_name'] ) ? sanitize_text_field( wp_unslash( $_POST['visitor_name'] ) ) : '';
		$visitor_email = isset( $_POST['visitor_email'] ) ? sanitize_email( wp_unslash( $_POST['visitor_email'] ) ) : '';
		$message       = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
		$page_url      = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
		$session_id    = isset( $_POST['session_id'] ) ? sanitize_key( wp_unslash( $_POST['session_id'] ) ) : '';
		$session_id    = $session_id ? $session_id : wp_generate_password( 20, false, false );

		if ( '' === $message ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a message.', 'ats-live-chat' ),
				),
				400
			);
		}

		if ( ! $is_online && ( '' === $visitor_name || '' === $visitor_email ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please provide your name and email so we can follow up.', 'ats-live-chat' ),
				),
				400
			);
		}

		if ( $is_online && '' === $visitor_name ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please provide your name to start the chat.', 'ats-live-chat' ),
				),
				400
			);
		}

		$message_id = ATSLC_DB::insert_message(
			array(
				'session_id'    => $session_id,
				'visitor_name'  => $visitor_name,
				'visitor_email' => $visitor_email,
				'message'       => $message,
				'context'       => $is_online ? 'online' : 'offline',
				'page_url'      => $page_url,
				'status'        => 'unread',
				'meta'          => array(
					'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
					'referrer'   => isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				),
			)
		);

		if ( ! $message_id ) {
			wp_send_json_error(
				array(
					'message' => __( 'The message could not be stored.', 'ats-live-chat' ),
				),
				500
			);
		}

		if ( ! empty( $settings['email_notifications'] ) && ! empty( $settings['notification_email'] ) ) {
			$this->send_notification_email(
				$settings['notification_email'],
				array(
					'context'       => $is_online ? 'online' : 'offline',
					'visitor_name'  => $visitor_name,
					'visitor_email' => $visitor_email,
					'message'       => $message,
					'page_url'      => $page_url,
				)
			);
		}

		$system_reply = $is_online
			? sprintf(
				/* translators: %s: visitor name. */
				__( 'Thanks %s. Our team has received your message and will follow up as soon as possible.', 'ats-live-chat' ),
				$visitor_name
			)
			: sprintf(
				/* translators: %s: visitor name. */
				__( 'Thanks %s. Your offline message has been sent successfully.', 'ats-live-chat' ),
				$visitor_name
			);

		wp_send_json_success(
			array(
				'message_id'    => $message_id,
				'context'       => $is_online ? 'online' : 'offline',
				'system_reply'  => $system_reply,
				'status_label'  => $online_status['label'],
				'session_id'    => $session_id,
			)
		);
	}

	/**
	 * Send an email notification.
	 *
	 * @param string $email Recipient email.
	 * @param array  $payload Message payload.
	 * @return void
	 */
	private function send_notification_email( $email, $payload ) {
		$subject = sprintf(
			/* translators: %s: context label. */
			__( '[ATS Live Chat] New %s message', 'ats-live-chat' ),
			ucfirst( $payload['context'] )
		);

		$body = sprintf(
			"Context: %s\nName: %s\nEmail: %s\nPage: %s\n\nMessage:\n%s",
			ucfirst( $payload['context'] ),
			$payload['visitor_name'] ? $payload['visitor_name'] : __( 'Anonymous', 'ats-live-chat' ),
			$payload['visitor_email'] ? $payload['visitor_email'] : __( 'Not provided', 'ats-live-chat' ),
			$payload['page_url'] ? $payload['page_url'] : __( 'Unknown', 'ats-live-chat' ),
			$payload['message']
		);

		wp_mail( sanitize_email( $email ), $subject, $body );
	}
}
