<?php
/**
 * Offline messages page view.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$current_url = add_query_arg(
	array(
		'page' => 'atslc-live-chat-messages',
	),
	admin_url( 'admin.php' )
);

$notice = isset( $_GET['atslc_notice'] ) ? sanitize_key( wp_unslash( $_GET['atslc_notice'] ) ) : '';
?>
<div class="wrap atslc-admin-wrap">
	<h1><?php esc_html_e( 'ATS Live Chat Messages', 'ats-live-chat' ); ?></h1>

	<?php if ( 'marked-read' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Message marked as read.', 'ats-live-chat' ); ?></p></div>
	<?php elseif ( 'deleted' === $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Message deleted.', 'ats-live-chat' ); ?></p></div>
	<?php elseif ( 'failed' === $notice ) : ?>
		<div class="notice notice-error is-dismissible"><p><?php esc_html_e( 'The message action could not be completed.', 'ats-live-chat' ); ?></p></div>
	<?php endif; ?>

	<div class="atslc-message-summary">
		<div class="atslc-summary-card">
			<span><?php esc_html_e( 'All messages', 'ats-live-chat' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $counts['all'] ) ); ?></strong>
		</div>
		<div class="atslc-summary-card">
			<span><?php esc_html_e( 'Unread', 'ats-live-chat' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $counts['unread'] ) ); ?></strong>
		</div>
		<div class="atslc-summary-card">
			<span><?php esc_html_e( 'Read', 'ats-live-chat' ); ?></span>
			<strong><?php echo esc_html( number_format_i18n( $counts['read'] ) ); ?></strong>
		</div>
	</div>

	<ul class="subsubsub atslc-subfilters">
		<li><a href="<?php echo esc_url( $current_url ); ?>" class="<?php echo '' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'ats-live-chat' ); ?></a> | </li>
		<li><a href="<?php echo esc_url( add_query_arg( 'status', 'unread', $current_url ) ); ?>" class="<?php echo 'unread' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Unread', 'ats-live-chat' ); ?></a> | </li>
		<li><a href="<?php echo esc_url( add_query_arg( 'status', 'read', $current_url ) ); ?>" class="<?php echo 'read' === $status_filter ? 'current' : ''; ?>"><?php esc_html_e( 'Read', 'ats-live-chat' ); ?></a></li>
	</ul>

	<table class="widefat striped atslc-messages-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Status', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Context', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Visitor', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Message', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Page URL', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Received', 'ats-live-chat' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'ats-live-chat' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $messages ) ) : ?>
				<tr>
					<td colspan="7"><?php esc_html_e( 'No messages found.', 'ats-live-chat' ); ?></td>
				</tr>
			<?php else : ?>
				<?php foreach ( $messages as $message ) : ?>
					<tr class="<?php echo 'unread' === $message['status'] ? 'is-unread' : ''; ?>">
						<td><span class="atslc-status-pill is-<?php echo esc_attr( $message['status'] ); ?>"><?php echo esc_html( ucfirst( $message['status'] ) ); ?></span></td>
						<td><span class="atslc-context-pill is-<?php echo esc_attr( $message['context'] ); ?>"><?php echo esc_html( ucfirst( $message['context'] ) ); ?></span></td>
						<td>
							<strong><?php echo esc_html( $message['visitor_name'] ? $message['visitor_name'] : __( 'Anonymous', 'ats-live-chat' ) ); ?></strong>
							<?php if ( ! empty( $message['visitor_email'] ) ) : ?>
								<br />
								<a href="mailto:<?php echo esc_attr( $message['visitor_email'] ); ?>"><?php echo esc_html( $message['visitor_email'] ); ?></a>
							<?php endif; ?>
						</td>
						<td class="atslc-message-cell"><?php echo esc_html( $message['message'] ); ?></td>
						<td>
							<?php if ( ! empty( $message['page_url'] ) ) : ?>
								<a href="<?php echo esc_url( $message['page_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( wp_html_excerpt( $message['page_url'], 48, '...' ) ); ?></a>
							<?php else : ?>
								<span>--</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $message['created_at'] ) ); ?></td>
						<td>
							<div class="atslc-row-actions">
								<?php if ( 'unread' === $message['status'] ) : ?>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
										<input type="hidden" name="action" value="atslc_mark_message_read" />
										<input type="hidden" name="message_id" value="<?php echo esc_attr( $message['id'] ); ?>" />
										<?php wp_nonce_field( 'atslc_message_action', 'atslc_message_nonce' ); ?>
										<button type="submit" class="button button-secondary"><?php esc_html_e( 'Mark read', 'ats-live-chat' ); ?></button>
									</form>
								<?php endif; ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return window.confirm('<?php echo esc_js( __( 'Delete this message permanently?', 'ats-live-chat' ) ); ?>');">
									<input type="hidden" name="action" value="atslc_delete_message" />
									<input type="hidden" name="message_id" value="<?php echo esc_attr( $message['id'] ); ?>" />
									<?php wp_nonce_field( 'atslc_message_action', 'atslc_message_nonce' ); ?>
									<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', 'ats-live-chat' ); ?></button>
								</form>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: total messages. */
						esc_html__( '%s items', 'ats-live-chat' ),
						esc_html( number_format_i18n( $total ) )
					);
					?>
				</span>
				<span class="pagination-links">
					<?php for ( $page = 1; $page <= $total_pages; $page++ ) : ?>
						<?php
						$page_url = add_query_arg(
							array(
								'page'   => 'atslc-live-chat-messages',
								'status' => $status_filter,
								'paged'  => $page,
							),
							admin_url( 'admin.php' )
						);
						?>
						<a class="button <?php echo $page === $page_number ? 'button-primary' : 'button-secondary'; ?>" href="<?php echo esc_url( $page_url ); ?>"><?php echo esc_html( $page ); ?></a>
					<?php endfor; ?>
				</span>
			</div>
		</div>
	<?php endif; ?>
</div>
