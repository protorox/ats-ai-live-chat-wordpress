<?php
/**
 * GitHub updater integration.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds GitHub release update support.
 */
class ATSLC_GitHub_Updater {

	/**
	 * Release cache key.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'atslc_github_release';

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
	private $plugin_slug;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_basename = plugin_basename( ATSLC_PLUGIN_FILE );
		$this->plugin_slug     = ATSLC_PLUGIN_SLUG;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'inject_plugin_information' ), 20, 3 );
		add_filter( 'http_request_args', array( $this, 'add_github_headers' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'purge_release_cache' ), 10, 2 );
	}

	/**
	 * Add update data to the WordPress update transient.
	 *
	 * @param stdClass $transient Update transient.
	 * @return stdClass
	 */
	public function inject_update( $transient ) {
		if ( empty( $transient->checked ) || ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( empty( $release['version'] ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], ATSLC_VERSION, '<=' ) ) {
			return $transient;
		}

		$transient->response[ $this->plugin_basename ] = (object) array(
			'slug'        => $this->plugin_slug,
			'plugin'      => $this->plugin_basename,
			'new_version' => $release['version'],
			'package'     => $release['package'],
			'url'         => $release['url'],
			'tested'      => ! empty( $release['tested'] ) ? $release['tested'] : '',
			'requires'    => ! empty( $release['requires'] ) ? $release['requires'] : '',
			'requires_php'=> ! empty( $release['requires_php'] ) ? $release['requires_php'] : '',
		);

		return $transient;
	}

	/**
	 * Provide plugin details on the update screen.
	 *
	 * @param false|object|array $result Existing result.
	 * @param string             $action API action.
	 * @param object             $args Request args.
	 * @return false|object|array
	 */
	public function inject_plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( empty( $release['version'] ) ) {
			return $result;
		}

		return (object) array(
			'name'          => __( 'ATS Live Chat', 'ats-live-chat' ),
			'slug'          => $this->plugin_slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://github.com/' . esc_attr( ATSLC_GITHUB_REPO ) . '">ATS</a>',
			'homepage'      => $release['url'],
			'download_link' => $release['package'],
			'requires'      => ! empty( $release['requires'] ) ? $release['requires'] : '',
			'tested'        => ! empty( $release['tested'] ) ? $release['tested'] : '',
			'requires_php'  => ! empty( $release['requires_php'] ) ? $release['requires_php'] : '',
			'sections'      => array(
				'description'  => __( 'A floating WordPress live chat widget with admin controls, offline capture, and Avada-friendly styling.', 'ats-live-chat' ),
				'installation' => __( 'Install the plugin, activate it, then configure the widget in the ATS Live Chat admin menu.', 'ats-live-chat' ),
				'changelog'    => ! empty( $release['changelog'] ) ? wp_kses_post( $release['changelog'] ) : __( 'See the latest GitHub release for change details.', 'ats-live-chat' ),
			),
			'banners'       => array(),
		);
	}

	/**
	 * Add authorization headers for GitHub API and asset requests when a token exists.
	 *
	 * @param array  $args HTTP args.
	 * @param string $url Request URL.
	 * @return array
	 */
	public function add_github_headers( $args, $url ) {
		$repo  = ATSLC_GITHUB_REPO;
		$token = $this->get_github_token();

		if ( false === strpos( $url, 'github.com/' . $repo ) && false === strpos( $url, 'api.github.com/repos/' . $repo ) ) {
			return $args;
		}

		$args['headers']['Accept']     = 'application/vnd.github+json';
		$args['headers']['User-Agent'] = 'ATS-Live-Chat-Updater/' . ATSLC_VERSION . '; ' . home_url( '/' );

		if ( $token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}

		return $args;
	}

	/**
	 * Clear cached release data after plugin updates.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $options Upgrader options.
	 * @return void
	 */
	public function purge_release_cache( $upgrader, $options ) {
		if ( empty( $options['type'] ) || 'plugin' !== $options['type'] ) {
			return;
		}

		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Read the latest GitHub release metadata.
	 *
	 * @return array
	 */
	private function get_latest_release() {
		$cached = get_site_transient( self::CACHE_KEY );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$request = wp_remote_get(
			'https://api.github.com/repos/' . ATSLC_GITHUB_REPO . '/releases/latest',
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $request ) || 200 !== (int) wp_remote_retrieve_response_code( $request ) ) {
			return array();
		}

		$body = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! is_array( $body ) || empty( $body['tag_name'] ) ) {
			return array();
		}

		$release = array(
			'version'      => ltrim( (string) $body['tag_name'], 'v' ),
			'url'          => ! empty( $body['html_url'] ) ? esc_url_raw( $body['html_url'] ) : 'https://github.com/' . ATSLC_GITHUB_REPO,
			'package'      => $this->get_release_asset_url( $body ),
			'changelog'    => ! empty( $body['body'] ) ? wp_kses_post( wpautop( $body['body'] ) ) : '',
			'requires'     => '',
			'tested'       => '',
			'requires_php' => '',
		);

		set_site_transient( self::CACHE_KEY, $release, 15 * MINUTE_IN_SECONDS );

		return $release;
	}

	/**
	 * Find the WordPress-ready plugin zip in release assets.
	 *
	 * @param array $release GitHub release payload.
	 * @return string
	 */
	private function get_release_asset_url( $release ) {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return '';
		}

		foreach ( $release['assets'] as $asset ) {
			if ( empty( $asset['name'] ) || empty( $asset['browser_download_url'] ) ) {
				continue;
			}

			if ( ATSLC_PLUGIN_SLUG . '.zip' === $asset['name'] ) {
				return esc_url_raw( $asset['browser_download_url'] );
			}
		}

		return '';
	}

	/**
	 * Get a GitHub token from wp-config if present.
	 *
	 * @return string
	 */
	private function get_github_token() {
		return defined( 'ATSLC_GITHUB_TOKEN' ) ? trim( (string) ATSLC_GITHUB_TOKEN ) : '';
	}
}
