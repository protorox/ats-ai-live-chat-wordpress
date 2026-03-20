<?php
/**
 * Plugin Name: ATS Live Chat
 * Plugin URI: https://example.com/
 * Description: A polished floating live chat widget with WordPress admin controls, offline capture, and Avada-friendly front-end behavior.
 * Version: 1.0.2
 * Author: ATS
 * Text Domain: ats-live-chat
 * Domain Path: /languages
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ATSLC_VERSION', '1.0.2' );
define( 'ATSLC_PLUGIN_FILE', __FILE__ );
define( 'ATSLC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ATSLC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ATSLC_PLUGIN_SLUG', 'ats-ai-live-chat-wordpress' );
define( 'ATSLC_GITHUB_REPO', defined( 'ATSLC_GITHUB_REPO_OVERRIDE' ) ? ATSLC_GITHUB_REPO_OVERRIDE : 'protorox/ats-ai-live-chat-wordpress' );

require_once ATSLC_PLUGIN_DIR . 'includes/class-atslc-db.php';
require_once ATSLC_PLUGIN_DIR . 'includes/class-atslc-options.php';
require_once ATSLC_PLUGIN_DIR . 'includes/class-atslc-helpers.php';
require_once ATSLC_PLUGIN_DIR . 'includes/class-atslc-github-updater.php';
require_once ATSLC_PLUGIN_DIR . 'admin/class-atslc-admin.php';
require_once ATSLC_PLUGIN_DIR . 'public/class-atslc-public.php';
require_once ATSLC_PLUGIN_DIR . 'includes/class-atslc-plugin.php';

register_activation_hook( __FILE__, array( 'ATSLC_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ATSLC_DB', 'deactivate' ) );

/**
 * Boot the plugin.
 *
 * @return ATSLC_Plugin
 */
function atslc() {
	return ATSLC_Plugin::get_instance();
}

atslc();
