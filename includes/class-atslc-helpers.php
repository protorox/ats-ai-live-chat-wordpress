<?php
/**
 * Shared helper methods.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Utility helpers for the plugin.
 */
class ATSLC_Helpers {

	/**
	 * Determine whether the widget should render on the current request.
	 *
	 * @return bool
	 */
	public static function should_render_widget() {
		if ( is_admin() ) {
			return false;
		}

		$settings = ATSLC_Options::get();

		if ( empty( $settings['enabled'] ) ) {
			return false;
		}

		if ( self::is_current_request_excluded( $settings ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check the exclude rules against the current request.
	 *
	 * Supports IDs, slugs, and path fragments.
	 *
	 * @param array $settings Plugin settings.
	 * @return bool
	 */
	public static function is_current_request_excluded( $settings ) {
		$rules_raw = trim( (string) ( $settings['exclude_rules'] ?? '' ) );

		if ( '' === $rules_raw ) {
			return false;
		}

		$rules        = preg_split( '/[\s,]+/', $rules_raw );
		$current_path = trim( wp_parse_url( home_url( add_query_arg( array(), $GLOBALS['wp']->request ?? '' ) ), PHP_URL_PATH ), '/' );
		$current_id   = get_queried_object_id();
		$post         = get_post( $current_id );
		$post_slug    = $post instanceof WP_Post ? $post->post_name : '';

		foreach ( $rules as $rule ) {
			$rule = trim( sanitize_text_field( $rule ), '/' );

			if ( '' === $rule ) {
				continue;
			}

			if ( ctype_digit( $rule ) && (int) $rule === (int) $current_id ) {
				return true;
			}

			if ( $post_slug && $rule === $post_slug ) {
				return true;
			}

			if ( $current_path && false !== strpos( $current_path, $rule ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compute the online/offline state from business hours.
	 *
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public static function get_online_status( $settings ) {
		$hours = $settings['business_hours'] ?? ATSLC_Options::get_default_business_hours();
		$now   = new DateTimeImmutable( 'now', wp_timezone() );
		$day   = strtolower( $now->format( 'l' ) );

		if ( empty( $hours[ $day ]['enabled'] ) ) {
			return array(
				'is_online' => false,
				'label'     => __( 'Offline', 'ats-live-chat' ),
			);
		}

		$start = $hours[ $day ]['start'] ?? '09:00';
		$end   = $hours[ $day ]['end'] ?? '17:00';
		$time  = $now->format( 'H:i' );

		$is_online = self::is_time_in_window( $time, $start, $end );

		return array(
			'is_online' => $is_online,
			'label'     => $is_online ? __( 'Online now', 'ats-live-chat' ) : __( 'Offline', 'ats-live-chat' ),
		);
	}

	/**
	 * Check if a current time sits inside a start/end window.
	 *
	 * Supports overnight windows.
	 *
	 * @param string $time Current time.
	 * @param string $start Window start.
	 * @param string $end Window end.
	 * @return bool
	 */
	private static function is_time_in_window( $time, $start, $end ) {
		if ( $start === $end ) {
			return true;
		}

		if ( $start < $end ) {
			return ( $time >= $start && $time <= $end );
		}

		return ( $time >= $start || $time <= $end );
	}

	/**
	 * Get the agent avatar URL.
	 *
	 * @param array $settings Settings array.
	 * @return string
	 */
	public static function get_agent_avatar_url( $settings ) {
		$attachment_id = absint( $settings['agent_avatar_id'] ?? 0 );

		if ( ! $attachment_id ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );

		return $url ? $url : '';
	}

	/**
	 * Build the CSS variable string for the widget root.
	 *
	 * @param array $settings Settings array.
	 * @return string
	 */
	public static function get_css_variables( $settings ) {
		$vars = array(
			'--atslc-primary'      => $settings['primary_color'],
			'--atslc-button'       => $settings['button_color'],
			'--atslc-header'       => $settings['header_color'],
			'--atslc-text'         => $settings['text_color'],
			'--atslc-widget-side'  => 'left' === $settings['position'] ? '24px auto auto 24px' : '24px 24px auto auto',
		);

		$output = '';

		foreach ( $vars as $name => $value ) {
			$output .= sprintf( '%1$s:%2$s;', esc_attr( $name ), esc_attr( $value ) );
		}

		return $output;
	}

	/**
	 * Get the display initial for the agent.
	 *
	 * @param array $settings Settings array.
	 * @return string
	 */
	public static function get_agent_initial( $settings ) {
		$name = trim( (string) ( $settings['agent_name'] ?? '' ) );

		if ( '' === $name ) {
			return 'A';
		}

		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );

		return strtoupper( $initial );
	}

	/**
	 * Get the current page URL.
	 *
	 * @return string
	 */
	public static function get_current_page_url() {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ?? '' ) );
	}
}
