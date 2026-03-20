<?php
/**
 * Admin-side functionality.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin controller.
 */
class ATSLC_Admin {

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	private $settings_slug = 'atslc-live-chat';

	/**
	 * Messages page slug.
	 *
	 * @var string
	 */
	private $messages_slug = 'atslc-live-chat-messages';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_atslc_mark_message_read', array( $this, 'handle_mark_read' ) );
		add_action( 'admin_post_atslc_delete_message', array( $this, 'handle_delete_message' ) );
	}

	/**
	 * Register admin menu items.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'ATS Live Chat', 'ats-live-chat' ),
			__( 'ATS Live Chat', 'ats-live-chat' ),
			'manage_options',
			$this->settings_slug,
			array( $this, 'render_settings_page' ),
			'dashicons-format-chat',
			58
		);

		add_submenu_page(
			$this->settings_slug,
			__( 'Settings', 'ats-live-chat' ),
			__( 'Settings', 'ats-live-chat' ),
			'manage_options',
			$this->settings_slug,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			$this->settings_slug,
			__( 'Offline Messages', 'ats-live-chat' ),
			__( 'Offline Messages', 'ats-live-chat' ),
			'manage_options',
			$this->messages_slug,
			array( $this, 'render_messages_page' )
		);
	}

	/**
	 * Register settings, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'atslc_settings_group',
			ATSLC_Options::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'ATSLC_Options', 'sanitize' ),
				'default'           => ATSLC_Options::get_defaults(),
			)
		);

		add_settings_section(
			'atslc_general_section',
			__( 'General', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_general'
		);

		add_settings_field(
			'enabled',
			__( 'Enable widget', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_general',
			'atslc_general_section',
			array(
				'key'         => 'enabled',
				'description' => __( 'Turn the chat widget on or off globally.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'widget_title',
			__( 'Widget title', 'ats-live-chat' ),
			array( $this, 'render_text_field' ),
			'atslc_general',
			'atslc_general_section',
			array(
				'key'         => 'widget_title',
				'description' => __( 'Shown in the widget header.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'agent_name',
			__( 'Agent name', 'ats-live-chat' ),
			array( $this, 'render_text_field' ),
			'atslc_general',
			'atslc_general_section',
			array(
				'key'         => 'agent_name',
				'description' => __( 'Displayed next to the avatar and status.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'agent_avatar_id',
			__( 'Agent avatar', 'ats-live-chat' ),
			array( $this, 'render_media_field' ),
			'atslc_general',
			'atslc_general_section',
			array(
				'key'         => 'agent_avatar_id',
				'description' => __( 'Choose a WordPress media library image for the chat header.', 'ats-live-chat' ),
			)
		);

		add_settings_section(
			'atslc_copy_section',
			__( 'Widget Copy', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_copy'
		);

		add_settings_field(
			'show_welcome',
			__( 'Show welcome message', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_copy',
			'atslc_copy_section',
			array(
				'key'         => 'show_welcome',
				'description' => __( 'Display the welcome message as the first chat bubble.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'welcome_message',
			__( 'Welcome message', 'ats-live-chat' ),
			array( $this, 'render_textarea_field' ),
			'atslc_copy',
			'atslc_copy_section',
			array(
				'key'         => 'welcome_message',
				'rows'        => 4,
				'description' => __( 'Used when the widget opens and the welcome toggle is enabled.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'offline_message',
			__( 'Offline message', 'ats-live-chat' ),
			array( $this, 'render_textarea_field' ),
			'atslc_copy',
			'atslc_copy_section',
			array(
				'key'         => 'offline_message',
				'rows'        => 4,
				'description' => __( 'Shown above the offline message form when the team is unavailable.', 'ats-live-chat' ),
			)
		);

		add_settings_section(
			'atslc_appearance_section',
			__( 'Appearance', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_appearance'
		);

		add_settings_field(
			'position',
			__( 'Widget position', 'ats-live-chat' ),
			array( $this, 'render_select_field' ),
			'atslc_appearance',
			'atslc_appearance_section',
			array(
				'key'         => 'position',
				'options'     => array(
					'right' => __( 'Bottom right', 'ats-live-chat' ),
					'left'  => __( 'Bottom left', 'ats-live-chat' ),
				),
				'description' => __( 'Controls where the floating launcher sits on the viewport.', 'ats-live-chat' ),
			)
		);

		foreach ( array(
			'primary_color' => __( 'Primary color', 'ats-live-chat' ),
			'button_color'  => __( 'Button color', 'ats-live-chat' ),
			'header_color'  => __( 'Header color', 'ats-live-chat' ),
			'text_color'    => __( 'Text color', 'ats-live-chat' ),
		) as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_color_field' ),
				'atslc_appearance',
				'atslc_appearance_section',
				array(
					'key' => $key,
				)
			);
		}

		add_settings_section(
			'atslc_behavior_section',
			__( 'Behavior', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_behavior'
		);

		add_settings_field(
			'auto_open_seconds',
			__( 'Auto-open delay', 'ats-live-chat' ),
			array( $this, 'render_number_field' ),
			'atslc_behavior',
			'atslc_behavior_section',
			array(
				'key'         => 'auto_open_seconds',
				'min'         => 0,
				'max'         => 120,
				'description' => __( 'Open the widget automatically after X seconds. Use 0 to disable.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'sound_notifications',
			__( 'Sound notifications', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_behavior',
			'atslc_behavior_section',
			array(
				'key'         => 'sound_notifications',
				'description' => __( 'Play a subtle confirmation tone when a message is acknowledged.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'hide_on_mobile',
			__( 'Hide on mobile', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_behavior',
			'atslc_behavior_section',
			array(
				'key'         => 'hide_on_mobile',
				'description' => __( 'Do not render the widget below 768px wide screens.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'hide_on_desktop',
			__( 'Hide on desktop', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_behavior',
			'atslc_behavior_section',
			array(
				'key'         => 'hide_on_desktop',
				'description' => __( 'Do not render the widget at 768px and above.', 'ats-live-chat' ),
			)
		);

		add_settings_section(
			'atslc_notifications_section',
			__( 'Notifications', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_notifications'
		);

		add_settings_field(
			'email_notifications',
			__( 'Email notifications', 'ats-live-chat' ),
			array( $this, 'render_checkbox_field' ),
			'atslc_notifications',
			'atslc_notifications_section',
			array(
				'key'         => 'email_notifications',
				'description' => __( 'Send an email when a new message is captured.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'notification_email',
			__( 'Notification email', 'ats-live-chat' ),
			array( $this, 'render_email_field' ),
			'atslc_notifications',
			'atslc_notifications_section',
			array(
				'key'         => 'notification_email',
				'description' => __( 'Where offline and online chat capture alerts should be delivered.', 'ats-live-chat' ),
			)
		);

		add_settings_section(
			'atslc_availability_section',
			__( 'Business Hours', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_availability'
		);

		add_settings_field(
			'business_hours',
			__( 'Online schedule', 'ats-live-chat' ),
			array( $this, 'render_business_hours_field' ),
			'atslc_availability',
			'atslc_availability_section',
			array(
				'key' => 'business_hours',
			)
		);

		add_settings_section(
			'atslc_advanced_section',
			__( 'Advanced', 'ats-live-chat' ),
			array( $this, 'render_section_intro' ),
			'atslc_advanced'
		);

		add_settings_field(
			'exclude_rules',
			__( 'Exclude pages or posts', 'ats-live-chat' ),
			array( $this, 'render_textarea_field' ),
			'atslc_advanced',
			'atslc_advanced_section',
			array(
				'key'         => 'exclude_rules',
				'rows'        => 4,
				'description' => __( 'Enter one page ID, slug, or path fragment per line. Example: 42, contact, thank-you.', 'ats-live-chat' ),
			)
		);

		add_settings_field(
			'custom_css',
			__( 'Custom CSS', 'ats-live-chat' ),
			array( $this, 'render_textarea_field' ),
			'atslc_advanced',
			'atslc_advanced_section',
			array(
				'key'         => 'custom_css',
				'rows'        => 8,
				'code'        => true,
				'description' => __( 'Optional CSS appended after the plugin stylesheet for site-specific refinements.', 'ats-live-chat' ),
			)
		);
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook_suffix Current page hook.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		$allowed_hooks = array(
			'toplevel_page_' . $this->settings_slug,
			$this->settings_slug . '_page_' . $this->messages_slug,
		);

		if ( ! in_array( $hook_suffix, $allowed_hooks, true ) ) {
			return;
		}

		wp_enqueue_media();

		wp_enqueue_style(
			'atslc-admin',
			ATSLC_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			ATSLC_VERSION
		);

		wp_enqueue_script(
			'atslc-admin',
			ATSLC_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			ATSLC_VERSION,
			true
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = ATSLC_Options::get();

		include ATSLC_PLUGIN_DIR . 'admin/views/settings-page.php';
	}

	/**
	 * Render the offline messages page.
	 *
	 * @return void
	 */
	public function render_messages_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$page_number   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page      = 20;
		$offset        = ( $page_number - 1 ) * $per_page;
		$messages      = ATSLC_DB::get_messages(
			array(
				'status' => $status_filter,
				'offset' => $offset,
				'limit'  => $per_page,
			)
		);
		$total         = ATSLC_DB::count_messages( $status_filter );
		$total_pages   = max( 1, (int) ceil( $total / $per_page ) );
		$counts        = array(
			'all'    => ATSLC_DB::count_messages(),
			'unread' => ATSLC_DB::count_messages( 'unread' ),
			'read'   => ATSLC_DB::count_messages( 'read' ),
		);

		include ATSLC_PLUGIN_DIR . 'admin/views/messages-page.php';
	}

	/**
	 * Mark a message as read.
	 *
	 * @return void
	 */
	public function handle_mark_read() {
		$this->verify_message_action();

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$updated    = $message_id ? ATSLC_DB::mark_read( $message_id ) : false;

		$this->redirect_to_messages_page( $updated ? 'marked-read' : 'failed' );
	}

	/**
	 * Delete a message.
	 *
	 * @return void
	 */
	public function handle_delete_message() {
		$this->verify_message_action();

		$message_id = isset( $_POST['message_id'] ) ? absint( $_POST['message_id'] ) : 0;
		$deleted    = $message_id ? ATSLC_DB::delete_message( $message_id ) : false;

		$this->redirect_to_messages_page( $deleted ? 'deleted' : 'failed' );
	}

	/**
	 * Verify message-management permissions and nonce.
	 *
	 * @return void
	 */
	private function verify_message_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage ATS Live Chat messages.', 'ats-live-chat' ) );
		}

		check_admin_referer( 'atslc_message_action', 'atslc_message_nonce' );
	}

	/**
	 * Redirect back to the messages page.
	 *
	 * @param string $notice Notice slug.
	 * @return void
	 */
	private function redirect_to_messages_page( $notice ) {
		$url = add_query_arg(
			array(
				'page'         => $this->messages_slug,
				'atslc_notice' => $notice,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Shared section intro callback.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		echo '<p class="atslc-section-copy">' . esc_html__( 'Configure the front-end widget behavior and styling below.', 'ats-live-chat' ) . '</p>';
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_checkbox_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = ! empty( $settings[ $key ] ) ? 1 : 0;
		$id       = 'atslc_' . $key;

		printf(
			'<label class="atslc-toggle" for="%1$s"><input type="checkbox" id="%1$s" name="%2$s[%3$s]" value="1" %4$s /><span>%5$s</span></label>',
			esc_attr( $id ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			esc_attr( $key ),
			checked( $value, 1, false ),
			isset( $args['description'] ) ? esc_html( $args['description'] ) : ''
		);
	}

	/**
	 * Render a text field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_text_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? '';

		printf(
			'<input class="regular-text atslc-input" type="text" id="atslc_%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			esc_attr( $value )
		);

		$this->render_description( $args );
	}

	/**
	 * Render an email field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_email_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? '';

		printf(
			'<input class="regular-text atslc-input" type="email" id="atslc_%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			esc_attr( $value )
		);

		$this->render_description( $args );
	}

	/**
	 * Render a textarea field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_textarea_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? '';
		$rows     = ! empty( $args['rows'] ) ? absint( $args['rows'] ) : 4;
		$class    = ! empty( $args['code'] ) ? 'atslc-textarea atslc-textarea-code' : 'atslc-textarea';

		printf(
			'<textarea class="%1$s" rows="%2$d" id="atslc_%3$s" name="%4$s[%3$s]">%5$s</textarea>',
			esc_attr( $class ),
			$rows,
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			esc_textarea( $value )
		);

		$this->render_description( $args );
	}

	/**
	 * Render a select field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_select_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? '';

		printf(
			'<select class="atslc-select" id="atslc_%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY )
		);

		foreach ( $args['options'] as $option_value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $option_value ),
				selected( $value, $option_value, false ),
				esc_html( $label )
			);
		}

		echo '</select>';

		$this->render_description( $args );
	}

	/**
	 * Render a number field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_number_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = absint( $settings[ $key ] ?? 0 );
		$min      = isset( $args['min'] ) ? absint( $args['min'] ) : 0;
		$max      = isset( $args['max'] ) ? absint( $args['max'] ) : 999;

		printf(
			'<input class="small-text atslc-input" type="number" min="%1$d" max="%2$d" id="atslc_%3$s" name="%4$s[%3$s]" value="%5$d" />',
			$min,
			$max,
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			$value
		);

		$this->render_description( $args );
	}

	/**
	 * Render a color field.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_color_field( $args ) {
		$settings = ATSLC_Options::get();
		$key      = $args['key'];
		$value    = $settings[ $key ] ?? '';

		printf(
			'<input class="atslc-color-field" type="color" id="atslc_%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( ATSLC_Options::OPTION_KEY ),
			esc_attr( $value )
		);
	}

	/**
	 * Render the agent avatar uploader.
	 *
	 * @param array $args Field args.
	 * @return void
	 */
	public function render_media_field( $args ) {
		$settings      = ATSLC_Options::get();
		$key           = $args['key'];
		$attachment_id = absint( $settings[ $key ] ?? 0 );
		$image_url     = $attachment_id ? wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) : '';

		?>
		<div class="atslc-media-field" data-target-input="atslc_<?php echo esc_attr( $key ); ?>">
			<input type="hidden" id="atslc_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( ATSLC_Options::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $attachment_id ); ?>" />
			<div class="atslc-media-preview <?php echo $image_url ? 'has-image' : ''; ?>">
				<?php if ( $image_url ) : ?>
					<img src="<?php echo esc_url( $image_url ); ?>" alt="" />
				<?php else : ?>
					<span><?php esc_html_e( 'No image selected', 'ats-live-chat' ); ?></span>
				<?php endif; ?>
			</div>
			<div class="atslc-media-actions">
				<button type="button" class="button button-secondary atslc-media-select"><?php esc_html_e( 'Select image', 'ats-live-chat' ); ?></button>
				<button type="button" class="button-link-delete atslc-media-remove"><?php esc_html_e( 'Remove', 'ats-live-chat' ); ?></button>
			</div>
		</div>
		<?php

		$this->render_description( $args );
	}

	/**
	 * Render the business hours table.
	 *
	 * @return void
	 */
	public function render_business_hours_field() {
		$settings = ATSLC_Options::get();
		$hours    = $settings['business_hours'] ?? ATSLC_Options::get_default_business_hours();
		$days     = array(
			'monday'    => __( 'Monday', 'ats-live-chat' ),
			'tuesday'   => __( 'Tuesday', 'ats-live-chat' ),
			'wednesday' => __( 'Wednesday', 'ats-live-chat' ),
			'thursday'  => __( 'Thursday', 'ats-live-chat' ),
			'friday'    => __( 'Friday', 'ats-live-chat' ),
			'saturday'  => __( 'Saturday', 'ats-live-chat' ),
			'sunday'    => __( 'Sunday', 'ats-live-chat' ),
		);

		?>
		<div class="atslc-hours-note">
			<?php
			printf(
				/* translators: %s: timezone string. */
				esc_html__( 'Availability is calculated using the WordPress site timezone: %s', 'ats-live-chat' ),
				esc_html( wp_timezone_string() ? wp_timezone_string() : __( 'UTC offset', 'ats-live-chat' ) )
			);
			?>
		</div>
		<table class="widefat striped atslc-hours-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Day', 'ats-live-chat' ); ?></th>
					<th><?php esc_html_e( 'Enabled', 'ats-live-chat' ); ?></th>
					<th><?php esc_html_e( 'Start', 'ats-live-chat' ); ?></th>
					<th><?php esc_html_e( 'End', 'ats-live-chat' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $days as $day_key => $day_label ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $day_label ); ?></strong></td>
						<td>
							<input type="checkbox" name="<?php echo esc_attr( ATSLC_Options::OPTION_KEY ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][enabled]" value="1" <?php checked( ! empty( $hours[ $day_key ]['enabled'] ) ); ?> />
						</td>
						<td>
							<input type="time" name="<?php echo esc_attr( ATSLC_Options::OPTION_KEY ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][start]" value="<?php echo esc_attr( $hours[ $day_key ]['start'] ); ?>" />
						</td>
						<td>
							<input type="time" name="<?php echo esc_attr( ATSLC_Options::OPTION_KEY ); ?>[business_hours][<?php echo esc_attr( $day_key ); ?>][end]" value="<?php echo esc_attr( $hours[ $day_key ]['end'] ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'Use overnight ranges like 20:00 to 06:00 if support spans midnight.', 'ats-live-chat' ); ?></p>
		<?php
	}

	/**
	 * Render optional field description text.
	 *
	 * @param array $args Field arguments.
	 * @return void
	 */
	private function render_description( $args ) {
		if ( empty( $args['description'] ) ) {
			return;
		}

		printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
	}
}
