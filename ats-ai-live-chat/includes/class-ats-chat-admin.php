<?php
/**
 * Admin UI and settings.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public function admin_menu() {
		if ( ! ATS_Chat_Plugin::user_can_agent() ) {
			return;
		}

		add_menu_page(
			__( 'Live Chat', 'ats-ai-live-chat' ),
			__( 'Live Chat', 'ats-ai-live-chat' ),
			'edit_posts',
			'ats-chat-live-chat',
			array( $this, 'render_live_chat_page' ),
			'dashicons-format-chat',
			56
		);

		add_submenu_page(
			'ats-chat-live-chat',
			__( 'Live Chat Settings', 'ats-ai-live-chat' ),
			__( 'Settings', 'ats-ai-live-chat' ),
			'manage_options',
			'ats-chat-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'ats_chat_settings_group',
			'ats_chat_settings',
			array( $this, 'sanitize_settings' )
		);
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array<string,mixed> $input Raw settings.
	 * @return array<string,mixed>
	 */
	public function sanitize_settings( $input ) {
		$defaults = ATS_Chat_DB::default_settings();
		$current  = ATS_Chat_DB::get_settings();
		$input    = is_array( $input ) ? $input : array();

		$sanitized                         = array();
		$api_key                           = isset( $input['ai_api_key'] ) ? trim( (string) $input['ai_api_key'] ) : '';
		$sanitized['ai_api_key']           = '' !== $api_key ? sanitize_text_field( $api_key ) : $current['ai_api_key'];
		$sanitized['ai_model']             = isset( $input['ai_model'] ) ? sanitize_text_field( (string) $input['ai_model'] ) : $defaults['ai_model'];
		$sanitized['ai_system_prompt']     = isset( $input['ai_system_prompt'] ) ? sanitize_textarea_field( (string) $input['ai_system_prompt'] ) : $defaults['ai_system_prompt'];
		$sanitized['cookie_notice_text']   = isset( $input['cookie_notice_text'] ) ? sanitize_text_field( (string) $input['cookie_notice_text'] ) : $defaults['cookie_notice_text'];
		$sanitized['cookie_notice_enabled'] = ! empty( $input['cookie_notice_enabled'] ) ? 1 : 0;

		$ai_mode = isset( $input['ai_mode'] ) ? sanitize_key( (string) $input['ai_mode'] ) : 'off';
		if ( ! in_array( $ai_mode, array( 'off', 'auto', 'draft' ), true ) ) {
			$ai_mode = 'off';
		}
		$sanitized['ai_mode'] = $ai_mode;

		$retention = isset( $input['data_retention_days'] ) ? absint( $input['data_retention_days'] ) : 30;
		$retention = max( 1, min( 3650, $retention ) );
		$sanitized['data_retention_days'] = $retention;

		return wp_parse_args( $sanitized, $defaults );
	}

	/**
	 * Enqueue admin styles/scripts.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( ! in_array( $hook, array( 'toplevel_page_ats-chat-live-chat', 'ats-chat-live-chat_page_ats-chat-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'ats-chat-admin',
			ATS_CHAT_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ATS_CHAT_VERSION
		);

		if ( 'toplevel_page_ats-chat-live-chat' === $hook ) {
			wp_enqueue_script(
				'ats-chat-admin',
				ATS_CHAT_PLUGIN_URL . 'assets/js/admin.js',
				array(),
				ATS_CHAT_VERSION,
				true
			);

			wp_localize_script(
				'ats-chat-admin',
				'ATSLiveChatAdmin',
				array(
					'restBase'       => esc_url_raw( rest_url( 'ats-chat/v1' ) ),
					'nonce'          => wp_create_nonce( 'wp_rest' ),
					'wooEnabled'     => class_exists( 'WooCommerce' ),
					'aiMode'         => ATS_Chat_DB::get_settings()['ai_mode'],
					'strings'        => array(
						'loading'               => __( 'Loading...', 'ats-ai-live-chat' ),
						'noVisitors'            => __( 'No active visitors in the last 2 minutes.', 'ats-ai-live-chat' ),
						'selectVisitor'         => __( 'Select a visitor to start.', 'ats-ai-live-chat' ),
						'send'                  => __( 'Send', 'ats-ai-live-chat' ),
						'typingVisitor'         => __( 'Visitor is typing...', 'ats-ai-live-chat' ),
						'typingAgent'           => __( 'Agent is typing...', 'ats-ai-live-chat' ),
						'searchProducts'        => __( 'Search products by title or SKU', 'ats-ai-live-chat' ),
						'useDraft'              => __( 'Use draft', 'ats-ai-live-chat' ),
					),
				)
			);
		}
	}

	/**
	 * Render live chat page.
	 *
	 * @return void
	 */
	public function render_live_chat_page() {
		if ( ! ATS_Chat_Plugin::user_can_agent() ) {
			wp_die( esc_html__( 'You do not have access to this page.', 'ats-ai-live-chat' ) );
		}

		/* translators: %s: plugin build number. */
		$build_label = sprintf( __( 'Build %s', 'ats-ai-live-chat' ), ATS_CHAT_VERSION );
		?>
		<div class="wrap ats-chat-admin-wrap">
			<h1 class="ats-chat-page-title">
				<span><?php esc_html_e( 'Live Chat', 'ats-ai-live-chat' ); ?></span>
				<span class="ats-chat-build-tag"><?php echo esc_html( $build_label ); ?></span>
			</h1>
			<div id="ats-chat-admin-app" class="ats-chat-admin-app">
				<div class="ats-chat-left">
					<div class="ats-chat-left-header">
						<strong><?php esc_html_e( 'Live Visitors', 'ats-ai-live-chat' ); ?></strong>
						<span id="ats-chat-agent-status" class="ats-chat-badge"><?php esc_html_e( 'Online', 'ats-ai-live-chat' ); ?></span>
					</div>
					<div id="ats-chat-visitors" class="ats-chat-visitors"></div>
				</div>
				<div class="ats-chat-right">
					<div id="ats-chat-conversation-header" class="ats-chat-conversation-header">
						<?php esc_html_e( 'Select a visitor to view the conversation.', 'ats-ai-live-chat' ); ?>
					</div>
					<div class="ats-chat-meta-grid">
						<div>
							<h3><?php esc_html_e( 'Recent Page Views', 'ats-ai-live-chat' ); ?></h3>
							<div id="ats-chat-page-history" class="ats-chat-page-history"></div>
						</div>
						<div>
							<h3><?php esc_html_e( 'Cart Context', 'ats-ai-live-chat' ); ?></h3>
							<div id="ats-chat-cart-context" class="ats-chat-cart-context"></div>
						</div>
					</div>
					<div id="ats-chat-thread" class="ats-chat-thread"></div>
					<div id="ats-chat-typing" class="ats-chat-typing"></div>
					<div class="ats-chat-composer">
						<textarea id="ats-chat-reply" rows="3" placeholder="<?php echo esc_attr__( 'Type your reply...', 'ats-ai-live-chat' ); ?>"></textarea>
						<div class="ats-chat-actions">
							<button type="button" id="ats-chat-send-product" class="button"><?php esc_html_e( 'Send Product', 'ats-ai-live-chat' ); ?></button>
							<button type="button" id="ats-chat-ai-draft" class="button"><?php esc_html_e( 'AI Draft', 'ats-ai-live-chat' ); ?></button>
							<button type="button" id="ats-chat-use-draft" class="button" style="display:none;"><?php esc_html_e( 'Use Draft', 'ats-ai-live-chat' ); ?></button>
							<button type="button" id="ats-chat-send" class="button button-primary"><?php esc_html_e( 'Send', 'ats-ai-live-chat' ); ?></button>
						</div>
						<div id="ats-chat-draft" class="ats-chat-draft" style="display:none;"></div>
					</div>
				</div>
			</div>

			<div id="ats-chat-product-modal" class="ats-chat-modal" style="display:none;">
				<div class="ats-chat-modal-content">
					<div class="ats-chat-modal-header">
						<strong><?php esc_html_e( 'Send Product', 'ats-ai-live-chat' ); ?></strong>
						<button type="button" id="ats-chat-modal-close" class="button-link">×</button>
					</div>
					<input type="text" id="ats-chat-product-search" placeholder="<?php echo esc_attr__( 'Search by product title or SKU...', 'ats-ai-live-chat' ); ?>" />
					<div id="ats-chat-product-results" class="ats-chat-product-results"></div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage settings.', 'ats-ai-live-chat' ) );
		}

		$settings = ATS_Chat_DB::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Live Chat Settings', 'ats-ai-live-chat' ); ?></h1>
			<form method="post" action="options.php" class="ats-chat-settings-form">
				<?php settings_fields( 'ats_chat_settings_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="ats_chat_ai_api_key"><?php esc_html_e( 'OpenAI API Key', 'ats-ai-live-chat' ); ?></label></th>
						<td>
							<input type="password" id="ats_chat_ai_api_key" name="ats_chat_settings[ai_api_key]" value="<?php echo esc_attr( $settings['ai_api_key'] ); ?>" class="regular-text" autocomplete="off" />
							<p class="description"><?php esc_html_e( 'Stored server-side only. Never exposed to frontend visitors.', 'ats-ai-live-chat' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_ai_model"><?php esc_html_e( 'AI Model', 'ats-ai-live-chat' ); ?></label></th>
						<td><input type="text" id="ats_chat_ai_model" name="ats_chat_settings[ai_model]" value="<?php echo esc_attr( $settings['ai_model'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_ai_mode"><?php esc_html_e( 'AI Mode', 'ats-ai-live-chat' ); ?></label></th>
						<td>
							<select id="ats_chat_ai_mode" name="ats_chat_settings[ai_mode]">
								<option value="off" <?php selected( $settings['ai_mode'], 'off' ); ?>><?php esc_html_e( 'Off', 'ats-ai-live-chat' ); ?></option>
								<option value="auto" <?php selected( $settings['ai_mode'], 'auto' ); ?>><?php esc_html_e( 'Auto-reply when no agents online', 'ats-ai-live-chat' ); ?></option>
								<option value="draft" <?php selected( $settings['ai_mode'], 'draft' ); ?>><?php esc_html_e( 'Draft replies for agents', 'ats-ai-live-chat' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_ai_system_prompt"><?php esc_html_e( 'AI System Prompt', 'ats-ai-live-chat' ); ?></label></th>
						<td><textarea id="ats_chat_ai_system_prompt" name="ats_chat_settings[ai_system_prompt]" rows="5" class="large-text"><?php echo esc_textarea( $settings['ai_system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_cookie_notice_enabled"><?php esc_html_e( 'Cookie Consent Notice', 'ats-ai-live-chat' ); ?></label></th>
						<td>
							<label>
								<input type="checkbox" id="ats_chat_cookie_notice_enabled" name="ats_chat_settings[cookie_notice_enabled]" value="1" <?php checked( ! empty( $settings['cookie_notice_enabled'] ) ); ?> />
								<?php esc_html_e( 'Enable cookie consent notice in chat widget', 'ats-ai-live-chat' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_cookie_notice_text"><?php esc_html_e( 'Cookie Notice Text', 'ats-ai-live-chat' ); ?></label></th>
						<td><input type="text" id="ats_chat_cookie_notice_text" name="ats_chat_settings[cookie_notice_text]" value="<?php echo esc_attr( $settings['cookie_notice_text'] ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="ats_chat_data_retention_days"><?php esc_html_e( 'Data Retention (days)', 'ats-ai-live-chat' ); ?></label></th>
						<td>
							<input type="number" min="1" max="3650" id="ats_chat_data_retention_days" name="ats_chat_settings[data_retention_days]" value="<?php echo esc_attr( (string) $settings['data_retention_days'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Chats and visitor data older than this will be cleaned daily via WP-Cron.', 'ats-ai-live-chat' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
