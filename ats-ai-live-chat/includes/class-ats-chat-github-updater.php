<?php
/**
 * GitHub release updater for ATS Chat.
 *
 * Uses GitHub Releases as the update source. Publish releases with an
 * attached zip asset containing the plugin folder `ats-ai-live-chat/`.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ATS_Chat_GitHub_Updater {

	/**
	 * Main plugin file path.
	 *
	 * @var string
	 */
	private $plugin_file;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Constructor.
	 *
	 * @param string $plugin_file Plugin main file.
	 */
	public function __construct( $plugin_file ) {
		$this->plugin_file     = $plugin_file;
		$this->plugin_basename = plugin_basename( $plugin_file );
		$this->slug            = dirname( $this->plugin_basename );

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'auto_update_plugin', array( $this, 'force_auto_update' ), 20, 2 );
	}

	/**
	 * Force automatic updates for this plugin when an update is available.
	 *
	 * @param bool  $update Whether to auto-update.
	 * @param mixed $item Update item object.
	 * @return bool
	 */
	public function force_auto_update( $update, $item ) {
		if ( is_object( $item ) && ! empty( $item->plugin ) && $this->plugin_basename === $item->plugin ) {
			return true;
		}

		return (bool) $update;
	}

	/**
	 * Check GitHub release and inject update object.
	 *
	 * @param stdClass $transient Plugin update transient.
	 * @return stdClass
	 */
	public function check_for_updates( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked ) || ! is_array( $transient->checked ) ) {
			return $transient;
		}

		if ( ! array_key_exists( $this->plugin_basename, $transient->checked ) ) {
			return $transient;
		}

		$config = $this->get_config();
		if ( empty( $config['repo'] ) ) {
			return $transient;
		}

		$release = $this->get_latest_release( $config );
		if ( empty( $release ) || empty( $release['tag_name'] ) ) {
			return $transient;
		}

		$new_version = $this->normalize_version( $release['tag_name'] );
		if ( '' === $new_version ) {
			return $transient;
		}

		if ( version_compare( ATS_CHAT_VERSION, $new_version, '>=' ) ) {
			return $transient;
		}

		$package_url = $this->get_release_package_url( $release, $config );
		if ( '' === $package_url ) {
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'id'           => 'https://github.com/' . $config['repo'],
			'slug'         => $this->slug,
			'plugin'       => $this->plugin_basename,
			'new_version'  => $new_version,
			'url'          => 'https://github.com/' . $config['repo'],
			'package'      => $package_url,
			'tested'       => get_bloginfo( 'version' ),
			'requires_php' => '7.4',
		);

		return $transient;
	}

	/**
	 * Provide plugin details modal information.
	 *
	 * @param false|object|array<string,mixed> $result Existing result.
	 * @param string                           $action Action type.
	 * @param object                           $args Plugin API args.
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$config = $this->get_config();
		if ( empty( $config['repo'] ) ) {
			return $result;
		}

		$release = $this->get_latest_release( $config );
		if ( empty( $release ) || empty( $release['tag_name'] ) ) {
			return $result;
		}

		$version = $this->normalize_version( $release['tag_name'] );
		$package = $this->get_release_package_url( $release, $config );

		return (object) array(
			'name'          => 'ATS AI Live Chat',
			'slug'          => $this->slug,
			'version'       => $version,
			'author'        => '<a href="https://github.com/' . esc_attr( $config['repo'] ) . '">ATS</a>',
			'homepage'      => 'https://github.com/' . esc_attr( $config['repo'] ),
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'download_link' => $package,
			'tested'        => get_bloginfo( 'version' ),
			'sections'      => array(
				'description' => ! empty( $release['body'] ) ? wp_kses_post( wpautop( (string) $release['body'] ) ) : 'GitHub release update.',
			),
		);
	}

	/**
	 * Fetch latest GitHub release with short transient cache.
	 *
	 * @param array<string,string> $config Updater config.
	 * @return array<string,mixed>
	 */
	private function get_latest_release( $config ) {
		$cache_key = 'ats_chat_gh_release_' . md5( $config['repo'] );
		$cached    = get_site_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . rawurlencode( $config['repo'] ) . '/releases/latest';
		$response = $this->github_request( $url, $config );

		if ( empty( $response ) || ! is_array( $response ) ) {
			return array();
		}

		set_site_transient( $cache_key, $response, MINUTE_IN_SECONDS );
		return $response;
	}

	/**
	 * Perform GitHub API request.
	 *
	 * @param string               $url API URL.
	 * @param array<string,string> $config Updater config.
	 * @return array<string,mixed>
	 */
	private function github_request( $url, $config ) {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'ATS-AI-Live-Chat-Updater/' . ATS_CHAT_VERSION,
		);

		if ( ! empty( $config['token'] ) ) {
			$headers['Authorization'] = 'Bearer ' . trim( $config['token'] );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 20,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : array();
	}

	/**
	 * Pick release package URL.
	 *
	 * Prefer a release asset zip named like plugin folder (recommended), then
	 * fallback to the GitHub zipball URL.
	 *
	 * @param array<string,mixed>  $release Release payload.
	 * @param array<string,string> $config Updater config.
	 * @return string
	 */
	private function get_release_package_url( $release, $config ) {
		unset( $config );

		if ( ! empty( $release['assets'] ) && is_array( $release['assets'] ) ) {
			$preferred_names = array(
				'ats-ai-live-chat.zip',
				$this->slug . '.zip',
			);

			foreach ( $release['assets'] as $asset ) {
				if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
					continue;
				}
				$asset_name = strtolower( (string) $asset['name'] );
				if ( in_array( $asset_name, $preferred_names, true ) ) {
					return esc_url_raw( (string) $asset['browser_download_url'] );
				}
			}

			foreach ( $release['assets'] as $asset ) {
				if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
					continue;
				}
				$asset_name = strtolower( (string) $asset['name'] );
				if ( '.zip' === substr( $asset_name, -4 ) ) {
					return esc_url_raw( (string) $asset['browser_download_url'] );
				}
			}
		}

		if ( ! empty( $release['zipball_url'] ) ) {
			return esc_url_raw( (string) $release['zipball_url'] );
		}

		return '';
	}

	/**
	 * Normalize version tags like v1.2.3 -> 1.2.3.
	 *
	 * @param string $version Raw version.
	 * @return string
	 */
	private function normalize_version( $version ) {
		$version = trim( (string) $version );
		$version = ltrim( $version, 'vV' );
		return preg_replace( '/[^0-9a-zA-Z\.\-]/', '', $version );
	}

	/**
	 * Updater configuration.
	 *
	 * Set constants in wp-config.php or use filter. If not set, defaults to
	 * this plugin's canonical repo.
	 * - ATS_CHAT_GITHUB_REPO: "owner/repo"
	 * - ATS_CHAT_GITHUB_TOKEN: optional token for private repos
	 *
	 * @return array<string,string>
	 */
	private function get_config() {
		$config = array(
			'repo'  => defined( 'ATS_CHAT_GITHUB_REPO' ) ? (string) ATS_CHAT_GITHUB_REPO : 'protorox/ats-ai-live-chat-wordpress',
			'token' => defined( 'ATS_CHAT_GITHUB_TOKEN' ) ? (string) ATS_CHAT_GITHUB_TOKEN : '',
		);

		$config = apply_filters( 'ats_chat_github_updater_config', $config );
		if ( ! is_array( $config ) ) {
			$config = array();
		}

		$repo  = isset( $config['repo'] ) ? trim( (string) $config['repo'] ) : '';
		$token = isset( $config['token'] ) ? trim( (string) $config['token'] ) : '';

		return array(
			'repo'  => $repo,
			'token' => $token,
		);
	}
}
