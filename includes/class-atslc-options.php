<?php
/**
 * Settings defaults and sanitization.
 *
 * @package ATSLiveChat
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin options.
 */
class ATSLC_Options {

	/**
	 * Option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'atslc_settings';

	/**
	 * Get the default settings payload.
	 *
	 * @return array
	 */
	public static function get_defaults() {
		return array(
			'enabled'             => 1,
			'widget_title'        => __( 'Chat with us', 'ats-live-chat' ),
			'welcome_message'     => __( 'Hi there. How can we help today?', 'ats-live-chat' ),
			'offline_message'     => __( 'We are currently offline. Leave your details and we will get back to you shortly.', 'ats-live-chat' ),
			'show_welcome'        => 1,
			'agent_avatar_id'     => 0,
			'agent_name'          => __( 'Support Team', 'ats-live-chat' ),
			'position'            => 'right',
			'primary_color'       => '#1f7aff',
			'button_color'        => '#1f7aff',
			'header_color'        => '#111827',
			'text_color'          => '#0f172a',
			'sound_notifications' => 1,
			'email_notifications' => 1,
			'notification_email'  => get_option( 'admin_email' ),
			'business_hours'      => self::get_default_business_hours(),
			'custom_css'          => '',
			'exclude_rules'       => '',
			'hide_on_mobile'      => 0,
			'hide_on_desktop'     => 0,
			'auto_open_seconds'   => 0,
		);
	}

	/**
	 * Get normalized settings.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed|null
	 */
	public static function get_item( $key ) {
		$settings = self::get();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Sanitize the plugin settings.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$defaults = self::get_defaults();
		$output   = self::get();
		$input    = is_array( $input ) ? $input : array();

		$output['enabled']             = ! empty( $input['enabled'] ) ? 1 : 0;
		$output['widget_title']        = sanitize_text_field( $input['widget_title'] ?? $defaults['widget_title'] );
		$output['welcome_message']     = sanitize_textarea_field( $input['welcome_message'] ?? $defaults['welcome_message'] );
		$output['offline_message']     = sanitize_textarea_field( $input['offline_message'] ?? $defaults['offline_message'] );
		$output['show_welcome']        = ! empty( $input['show_welcome'] ) ? 1 : 0;
		$output['agent_avatar_id']     = absint( $input['agent_avatar_id'] ?? 0 );
		$output['agent_name']          = sanitize_text_field( $input['agent_name'] ?? $defaults['agent_name'] );
		$output['position']            = in_array( $input['position'] ?? '', array( 'left', 'right' ), true ) ? $input['position'] : $defaults['position'];
		$output['primary_color']       = self::sanitize_color( $input['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color'] );
		$output['button_color']        = self::sanitize_color( $input['button_color'] ?? $defaults['button_color'], $defaults['button_color'] );
		$output['header_color']        = self::sanitize_color( $input['header_color'] ?? $defaults['header_color'], $defaults['header_color'] );
		$output['text_color']          = self::sanitize_color( $input['text_color'] ?? $defaults['text_color'], $defaults['text_color'] );
		$output['sound_notifications'] = ! empty( $input['sound_notifications'] ) ? 1 : 0;
		$output['email_notifications'] = ! empty( $input['email_notifications'] ) ? 1 : 0;
		$output['notification_email']  = sanitize_email( $input['notification_email'] ?? $defaults['notification_email'] );
		$output['business_hours']      = self::sanitize_business_hours( $input['business_hours'] ?? array() );
		$output['custom_css']          = sanitize_textarea_field( $input['custom_css'] ?? '' );
		$output['exclude_rules']       = sanitize_textarea_field( $input['exclude_rules'] ?? '' );
		$output['hide_on_mobile']      = ! empty( $input['hide_on_mobile'] ) ? 1 : 0;
		$output['hide_on_desktop']     = ! empty( $input['hide_on_desktop'] ) ? 1 : 0;
		$output['auto_open_seconds']   = max( 0, absint( $input['auto_open_seconds'] ?? 0 ) );

		if ( empty( $output['notification_email'] ) ) {
			$output['notification_email'] = $defaults['notification_email'];
		}

		add_settings_error(
			self::OPTION_KEY,
			'atslc_settings_saved',
			__( 'ATS Live Chat settings saved.', 'ats-live-chat' ),
			'updated'
		);

		return $output;
	}

	/**
	 * Default business hours.
	 *
	 * @return array
	 */
	public static function get_default_business_hours() {
		$days   = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		$hours  = array();

		foreach ( $days as $day ) {
			$is_weekday   = in_array( $day, array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday' ), true );
			$hours[ $day ] = array(
				'enabled' => $is_weekday ? 1 : 0,
				'start'   => '09:00',
				'end'     => '17:00',
			);
		}

		return $hours;
	}

	/**
	 * Sanitize a color string.
	 *
	 * @param string $value Raw value.
	 * @param string $fallback Fallback color.
	 * @return string
	 */
	private static function sanitize_color( $value, $fallback ) {
		$color = sanitize_hex_color( $value );

		return $color ? $color : $fallback;
	}

	/**
	 * Sanitize a business-hours matrix.
	 *
	 * @param array $hours Raw hours.
	 * @return array
	 */
	private static function sanitize_business_hours( $hours ) {
		$defaults = self::get_default_business_hours();
		$output   = array();

		foreach ( $defaults as $day => $default ) {
			$row = isset( $hours[ $day ] ) && is_array( $hours[ $day ] ) ? $hours[ $day ] : array();

			$output[ $day ] = array(
				'enabled' => ! empty( $row['enabled'] ) ? 1 : 0,
				'start'   => self::sanitize_time( $row['start'] ?? $default['start'], $default['start'] ),
				'end'     => self::sanitize_time( $row['end'] ?? $default['end'], $default['end'] ),
			);
		}

		return $output;
	}

	/**
	 * Sanitize a 24-hour time string.
	 *
	 * @param string $value Raw value.
	 * @param string $fallback Fallback.
	 * @return string
	 */
	private static function sanitize_time( $value, $fallback ) {
		$value = sanitize_text_field( $value );

		return preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : $fallback;
	}
}
