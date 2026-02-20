<?php
/**
 * Plugin Name: ATS AI Live Chat
 * Plugin URI: https://example.com/
 * Description: Self-hosted live chat for WordPress with visitor tracking, admin inbox, WooCommerce context, and optional AI assistance.
 * Version: 1.1.4
 * Author: ATS
 * License: GPLv2 or later
 * Text Domain: ats-ai-live-chat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATS_CHAT_VERSION', '1.1.4' );
define( 'ATS_CHAT_PLUGIN_FILE', __FILE__ );
define( 'ATS_CHAT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATS_CHAT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-activator.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-deactivator.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-db.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-ai.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-woocommerce.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-github-updater.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-rest.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-admin.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-frontend.php';
require_once ATS_CHAT_PLUGIN_DIR . 'includes/class-ats-chat-plugin.php';

register_activation_hook( __FILE__, array( 'ATS_Chat_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ATS_Chat_Deactivator', 'deactivate' ) );

/**
 * Bootstrap plugin.
 *
 * @return ATS_Chat_Plugin
 */
function ats_chat_plugin() {
	return ATS_Chat_Plugin::instance();
}

add_action( 'plugins_loaded', 'ats_chat_plugin' );
