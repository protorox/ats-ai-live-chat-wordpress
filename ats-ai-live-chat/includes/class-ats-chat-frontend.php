<?php
/**
 * Frontend widget rendering and assets.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_Frontend {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_widget' ) );
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		$settings = ATS_Chat_DB::get_settings();
		$user     = wp_get_current_user();
		$name     = ( $user && $user->exists() ) ? $user->display_name : '';
		$email    = ( $user && $user->exists() ) ? $user->user_email : '';

		wp_enqueue_style(
			'ats-chat-frontend',
			ATS_CHAT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			ATS_CHAT_VERSION
		);

		wp_enqueue_script(
			'ats-chat-frontend',
			ATS_CHAT_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			ATS_CHAT_VERSION,
			true
		);

		wp_localize_script(
			'ats-chat-frontend',
			'ATSLiveChat',
			array(
				'restBase'      => esc_url_raw( rest_url( 'ats-chat/v1' ) ),
				'nonce'         => wp_create_nonce( 'ats_chat_public' ),
				'siteName'      => get_bloginfo( 'name' ),
				'visitorName'   => $name,
				'visitorEmail'  => $email,
				'cookieNotice'  => ! empty( $settings['cookie_notice_enabled'] ) ? sanitize_text_field( (string) $settings['cookie_notice_text'] ) : '',
				'pollMs'        => 2000,
				'presenceMs'    => 10000,
				'strings'       => array(
					'headerTitle'       => __( 'Live Chat', 'ats-ai-live-chat' ),
					'headerSubtitle'    => __( 'We usually reply in a few minutes.', 'ats-ai-live-chat' ),
					'placeholder'       => __( 'Type your message…', 'ats-ai-live-chat' ),
					'send'              => __( 'Send', 'ats-ai-live-chat' ),
					'offlineTitle'      => __( 'No agents online right now', 'ats-ai-live-chat' ),
					'offlineSub'        => __( 'Leave your details and we will follow up.', 'ats-ai-live-chat' ),
					'leadName'          => __( 'Your name', 'ats-ai-live-chat' ),
					'leadEmail'         => __( 'Email', 'ats-ai-live-chat' ),
					'leadMessage'       => __( 'How can we help?', 'ats-ai-live-chat' ),
					'leadSend'          => __( 'Send request', 'ats-ai-live-chat' ),
					'typingAgent'       => __( 'Agent is typing…', 'ats-ai-live-chat' ),
					'thanks'            => __( 'Thanks! We received your message.', 'ats-ai-live-chat' ),
				),
			)
		);
	}

	/**
	 * Render frontend chat shell.
	 *
	 * @return void
	 */
	public function render_widget() {
		if ( is_admin() ) {
			return;
		}
		?>
		<div id="ats-chat-widget" class="ats-chat-widget" aria-live="polite">
			<button id="ats-chat-toggle" class="ats-chat-toggle" aria-expanded="false" aria-controls="ats-chat-panel">
				<span class="ats-chat-toggle-label"><?php esc_html_e( 'Chat', 'ats-ai-live-chat' ); ?></span>
			</button>
			<div id="ats-chat-panel" class="ats-chat-panel" style="display:none;">
				<div class="ats-chat-panel-header">
					<div>
						<strong><?php esc_html_e( 'Live Chat', 'ats-ai-live-chat' ); ?></strong>
						<div class="ats-chat-panel-subtitle"><?php esc_html_e( 'Ask us anything', 'ats-ai-live-chat' ); ?></div>
					</div>
					<button type="button" id="ats-chat-close" class="ats-chat-close" aria-label="<?php esc_attr_e( 'Close chat', 'ats-ai-live-chat' ); ?>">×</button>
				</div>

				<div id="ats-chat-cookie-notice" class="ats-chat-cookie-notice" style="display:none;"></div>
				<div id="ats-chat-messages" class="ats-chat-messages"></div>
				<div id="ats-chat-typing" class="ats-chat-typing"></div>

				<div id="ats-chat-composer" class="ats-chat-composer">
					<textarea id="ats-chat-input" rows="2" placeholder="<?php esc_attr_e( 'Type your message…', 'ats-ai-live-chat' ); ?>"></textarea>
					<button type="button" id="ats-chat-send" class="ats-chat-send"><?php esc_html_e( 'Send', 'ats-ai-live-chat' ); ?></button>
				</div>

				<div id="ats-chat-offline" class="ats-chat-offline" style="display:none;">
					<h4><?php esc_html_e( 'No agents online right now', 'ats-ai-live-chat' ); ?></h4>
					<p><?php esc_html_e( 'Leave your details and we will follow up soon.', 'ats-ai-live-chat' ); ?></p>
					<input id="ats-chat-lead-name" type="text" placeholder="<?php esc_attr_e( 'Your name', 'ats-ai-live-chat' ); ?>" />
					<input id="ats-chat-lead-email" type="email" placeholder="<?php esc_attr_e( 'Email', 'ats-ai-live-chat' ); ?>" />
					<textarea id="ats-chat-lead-message" rows="3" placeholder="<?php esc_attr_e( 'How can we help?', 'ats-ai-live-chat' ); ?>"></textarea>
					<button type="button" id="ats-chat-lead-send" class="ats-chat-send"><?php esc_html_e( 'Send request', 'ats-ai-live-chat' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}
}
