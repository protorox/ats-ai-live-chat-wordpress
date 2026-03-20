<?php
/**
 * Widget view.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div
	id="atslc-widget"
	class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
	style="<?php echo esc_attr( $css_vars ); ?>"
	data-online="<?php echo esc_attr( $online_status['is_online'] ? '1' : '0' ); ?>"
	data-position="<?php echo esc_attr( $settings['position'] ); ?>"
>
	<button type="button" class="atslc-launcher" aria-expanded="false" aria-controls="atslc-panel">
		<span class="atslc-launcher-icon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
				<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
			</svg>
		</span>
		<span class="atslc-launcher-text"><?php echo esc_html( $settings['widget_title'] ); ?></span>
		<span class="atslc-status-dot <?php echo $online_status['is_online'] ? 'is-online' : 'is-offline'; ?>" aria-hidden="true"></span>
	</button>

	<div id="atslc-panel" class="atslc-panel" aria-hidden="true">
		<div class="atslc-header">
			<div class="atslc-header-meta">
				<div class="atslc-avatar">
					<?php if ( $avatar_url ) : ?>
						<img src="<?php echo esc_url( $avatar_url ); ?>" alt="<?php echo esc_attr( $settings['agent_name'] ); ?>" />
					<?php else : ?>
						<span><?php echo esc_html( ATSLC_Helpers::get_agent_initial( $settings ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="atslc-header-copy">
					<strong><?php echo esc_html( $settings['widget_title'] ); ?></strong>
					<span><?php echo esc_html( $settings['agent_name'] ); ?> | <?php echo esc_html( $online_status['label'] ); ?></span>
				</div>
			</div>
			<div class="atslc-header-actions">
				<button type="button" class="atslc-icon-button" data-action="minimize" aria-label="<?php esc_attr_e( 'Minimize chat', 'ats-live-chat' ); ?>">
					<span aria-hidden="true">-</span>
				</button>
				<button type="button" class="atslc-icon-button" data-action="close" aria-label="<?php esc_attr_e( 'Close chat', 'ats-live-chat' ); ?>">
					<span aria-hidden="true">x</span>
				</button>
			</div>
		</div>

		<div class="atslc-body">
			<div class="atslc-transcript" aria-live="polite" aria-label="<?php esc_attr_e( 'Chat messages', 'ats-live-chat' ); ?>"></div>

			<div class="atslc-onboarding atslc-online-only">
				<p><?php esc_html_e( 'Start with your details so the team can follow up properly.', 'ats-live-chat' ); ?></p>
				<form class="atslc-profile-form">
					<input type="text" name="visitor_name" placeholder="<?php esc_attr_e( 'Your name', 'ats-live-chat' ); ?>" />
					<input type="email" name="visitor_email" placeholder="<?php esc_attr_e( 'Email address (optional)', 'ats-live-chat' ); ?>" />
					<button type="submit"><?php esc_html_e( 'Start chat', 'ats-live-chat' ); ?></button>
				</form>
			</div>

			<div class="atslc-offline-state atslc-offline-only">
				<p class="atslc-offline-copy"><?php echo esc_html( $settings['offline_message'] ); ?></p>
				<form class="atslc-offline-form">
					<input type="text" name="visitor_name" placeholder="<?php esc_attr_e( 'Your name', 'ats-live-chat' ); ?>" required />
					<input type="email" name="visitor_email" placeholder="<?php esc_attr_e( 'Email address', 'ats-live-chat' ); ?>" required />
					<textarea name="message" rows="4" placeholder="<?php esc_attr_e( 'How can we help?', 'ats-live-chat' ); ?>" required></textarea>
					<button type="submit"><?php esc_html_e( 'Send message', 'ats-live-chat' ); ?></button>
				</form>
			</div>

			<p class="atslc-feedback" hidden></p>
		</div>

		<div class="atslc-footer atslc-online-only">
			<form class="atslc-composer">
				<textarea name="message" rows="1" placeholder="<?php esc_attr_e( 'Type your message...', 'ats-live-chat' ); ?>"></textarea>
				<button type="submit"><?php esc_html_e( 'Send', 'ats-live-chat' ); ?></button>
			</form>
		</div>
	</div>
</div>
