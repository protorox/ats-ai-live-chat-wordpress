<?php
/**
 * Settings page view.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$avatar_url   = ATSLC_Helpers::get_agent_avatar_url( $settings );
$online_state = ATSLC_Helpers::get_online_status( $settings );
?>
<div class="wrap atslc-admin-wrap">
	<div class="atslc-admin-hero">
		<div>
			<h1><?php esc_html_e( 'ATS Live Chat', 'ats-live-chat' ); ?></h1>
			<p><?php esc_html_e( 'Manage the front-end chat widget, brand styling, business hours, and notifications from one place.', 'ats-live-chat' ); ?></p>
		</div>
		<div class="atslc-admin-preview">
			<div class="atslc-admin-preview-card">
				<div class="atslc-admin-preview-header" style="background: <?php echo esc_attr( $settings['header_color'] ); ?>;">
					<div class="atslc-admin-preview-avatar">
						<?php if ( $avatar_url ) : ?>
							<img src="<?php echo esc_url( $avatar_url ); ?>" alt="" />
						<?php else : ?>
							<span><?php echo esc_html( ATSLC_Helpers::get_agent_initial( $settings ) ); ?></span>
						<?php endif; ?>
					</div>
					<div>
						<strong><?php echo esc_html( $settings['widget_title'] ); ?></strong>
						<span><?php echo esc_html( $online_state['label'] ); ?></span>
					</div>
				</div>
				<div class="atslc-admin-preview-body">
					<p><?php echo esc_html( $settings['show_welcome'] ? $settings['welcome_message'] : $settings['offline_message'] ); ?></p>
				</div>
				<div class="atslc-admin-preview-button" style="background: <?php echo esc_attr( $settings['button_color'] ); ?>;"></div>
			</div>
		</div>
	</div>

	<?php settings_errors( ATSLC_Options::OPTION_KEY ); ?>

	<form class="atslc-settings-form" action="options.php" method="post">
		<?php settings_fields( 'atslc_settings_group' ); ?>

		<div class="atslc-tab-nav" role="tablist" aria-label="<?php esc_attr_e( 'ATS Live Chat settings sections', 'ats-live-chat' ); ?>">
			<button type="button" class="atslc-tab-button is-active" data-target="atslc-panel-general"><?php esc_html_e( 'General', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-copy"><?php esc_html_e( 'Copy', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-appearance"><?php esc_html_e( 'Appearance', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-behavior"><?php esc_html_e( 'Behavior', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-notifications"><?php esc_html_e( 'Notifications', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-availability"><?php esc_html_e( 'Business Hours', 'ats-live-chat' ); ?></button>
			<button type="button" class="atslc-tab-button" data-target="atslc-panel-advanced"><?php esc_html_e( 'Advanced', 'ats-live-chat' ); ?></button>
		</div>

		<div id="atslc-panel-general" class="atslc-settings-panel is-active">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_general' ); ?>
			</div>
		</div>

		<div id="atslc-panel-copy" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_copy' ); ?>
			</div>
		</div>

		<div id="atslc-panel-appearance" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_appearance' ); ?>
			</div>
		</div>

		<div id="atslc-panel-behavior" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_behavior' ); ?>
			</div>
		</div>

		<div id="atslc-panel-notifications" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_notifications' ); ?>
			</div>
		</div>

		<div id="atslc-panel-availability" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_availability' ); ?>
			</div>
		</div>

		<div id="atslc-panel-advanced" class="atslc-settings-panel">
			<div class="atslc-settings-card">
				<?php do_settings_sections( 'atslc_advanced' ); ?>
			</div>
		</div>

		<div class="atslc-settings-actions">
			<?php submit_button( __( 'Save Settings', 'ats-live-chat' ), 'primary large', 'submit', false ); ?>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=atslc-live-chat-messages' ) ); ?>"><?php esc_html_e( 'View Messages', 'ats-live-chat' ); ?></a>
		</div>
	</form>
</div>
