<?php
/**
 * Plugin settings.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Leest en bewaart plugininstellingen.
 */
class Aardbei_Reserveringen_Settings {

	const OPTION_NAME = 'aardbei_reserveringen_settings';

	/**
	 * Haal instellingen op, aangevuld met defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, self::get_default_settings() );
	}

	/**
	 * Standaard instellingen.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'opening_weekday'         => 7,
			'opening_time'            => '09:00',
			'bookable_weeks'          => 1,
			'max_persons_for_contact' => 9,
			'admin_email'             => 'reservering@kopenwaarhetgroeit.nl',
			'customer_mail_subject'   => __( 'Je reservering voor aardbeien plukken', 'aardbei-reserveringen' ),
			'admin_mail_subject'      => __( 'Nieuwe reservering voor aardbeien plukken', 'aardbei-reserveringen' ),
			'booking_mail_admin'      => 1,
			'cancellation_mail_admin' => 1,
			'show_remaining_capacity' => 1,
			'popup_enabled'           => 1,
			'popup_button_text'       => '🍓 Reserveer je pluktijd',
			'popup_button_bg_color'   => '#cf2e2e',
			'popup_button_text_color' => '#ffffff',
			'popup_button_hover_color' => '#a82424',
			'frontend_widget_title'   => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'De Manderveense Aardbei',
			'frontend_reserve_button_text' => 'Pluktijd reserveren',
			'frontend_card_title'     => 'Zelfpluk',
			'frontend_card_subtitle'  => 'Zelf aardbeien plukken bij ons op het veld',
			'frontend_card_image_url' => '',
			'frontend_action_bg_color' => '#171717',
			'frontend_action_text_color' => '#ffffff',
			'frontend_widget_width'   => 420,
			'frontend_powered_by_text' => 'ModernVisuals',
		);
	}

	/**
	 * Werk instellingen bij — tab-bewust zodat een opslaan van één tab
	 * de instellingen van andere tabs niet overschrijft.
	 *
	 * @param array $data Ruwe formulierdata.
	 * @return bool
	 */
	public static function update_settings( $data ) {
		$current = self::get_settings();
		$tab     = isset( $data['_tab'] ) ? sanitize_key( wp_unslash( $data['_tab'] ) ) : '';

		// Begin altijd met de huidige opgeslagen waarden; overschrijf alleen
		// de velden die in het formulier van de betreffende tab zitten.
		$settings = $current;
		$settings['frontend_powered_by_text'] = 'ModernVisuals';

		// ---- Tab: Boekingsvenster ----------------------------------------
		if ( in_array( $tab, array( 'booking', '' ), true ) ) {
			$opening_weekday = isset( $data['opening_weekday'] ) ? absint( wp_unslash( $data['opening_weekday'] ) ) : 7;
			$opening_weekday = min( 7, max( 1, $opening_weekday ) );

			$opening_time = isset( $data['opening_time'] ) ? sanitize_text_field( wp_unslash( $data['opening_time'] ) ) : '09:00';
			if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $opening_time ) ) {
				$opening_time = '09:00';
			}

			$bookable_weeks = isset( $data['bookable_weeks'] ) ? absint( wp_unslash( $data['bookable_weeks'] ) ) : 1;
			$bookable_weeks = min( 8, max( 1, $bookable_weeks ) );

			$max_persons_for_contact = isset( $data['max_persons_for_contact'] ) ? absint( wp_unslash( $data['max_persons_for_contact'] ) ) : 9;
			$max_persons_for_contact = min( 99, max( 1, $max_persons_for_contact ) );

			$settings['opening_weekday']         = $opening_weekday;
			$settings['opening_time']            = $opening_time;
			$settings['bookable_weeks']          = $bookable_weeks;
			$settings['max_persons_for_contact'] = $max_persons_for_contact;
		}

		// ---- Tab: E-mail -------------------------------------------------
		if ( in_array( $tab, array( 'email', '' ), true ) ) {
			$admin_email = isset( $data['admin_email'] ) ? sanitize_email( wp_unslash( $data['admin_email'] ) ) : $current['admin_email'];
			if ( ! is_email( $admin_email ) ) {
				$admin_email = $current['admin_email'];
			}

			$settings['admin_email']             = $admin_email;
			$settings['customer_mail_subject']   = isset( $data['customer_mail_subject'] ) ? sanitize_text_field( wp_unslash( $data['customer_mail_subject'] ) ) : $current['customer_mail_subject'];
			$settings['admin_mail_subject']      = isset( $data['admin_mail_subject'] ) ? sanitize_text_field( wp_unslash( $data['admin_mail_subject'] ) ) : $current['admin_mail_subject'];
			$settings['booking_mail_admin']      = empty( $data['booking_mail_admin'] ) ? 0 : 1;
			$settings['cancellation_mail_admin'] = empty( $data['cancellation_mail_admin'] ) ? 0 : 1;
		}

		// ---- Tab: Frontend -----------------------------------------------
		if ( in_array( $tab, array( 'frontend', '' ), true ) ) {
			$frontend_widget_title = isset( $data['frontend_widget_title'] ) ? sanitize_text_field( wp_unslash( $data['frontend_widget_title'] ) ) : $current['frontend_widget_title'];
			if ( '' === $frontend_widget_title ) {
				$frontend_widget_title = get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'De Manderveense Aardbei';
			}

			$frontend_reserve_button_text = isset( $data['frontend_reserve_button_text'] ) ? sanitize_text_field( wp_unslash( $data['frontend_reserve_button_text'] ) ) : $current['frontend_reserve_button_text'];
			if ( '' === $frontend_reserve_button_text ) {
				$frontend_reserve_button_text = 'Pluktijd reserveren';
			}

			$frontend_widget_width = isset( $data['frontend_widget_width'] ) ? absint( wp_unslash( $data['frontend_widget_width'] ) ) : absint( $current['frontend_widget_width'] );
			$frontend_widget_width = min( 560, max( 360, $frontend_widget_width ) );

			$settings['show_remaining_capacity']      = empty( $data['show_remaining_capacity'] ) ? 0 : 1;
			$settings['frontend_widget_title']        = $frontend_widget_title;
			$settings['frontend_reserve_button_text'] = $frontend_reserve_button_text;
			$settings['frontend_card_title']          = isset( $data['frontend_card_title'] ) ? sanitize_text_field( wp_unslash( $data['frontend_card_title'] ) ) : $current['frontend_card_title'];
			$settings['frontend_card_subtitle']       = isset( $data['frontend_card_subtitle'] ) ? sanitize_text_field( wp_unslash( $data['frontend_card_subtitle'] ) ) : $current['frontend_card_subtitle'];
			$settings['frontend_card_image_url']      = isset( $data['frontend_card_image_url'] ) ? esc_url_raw( wp_unslash( $data['frontend_card_image_url'] ) ) : $current['frontend_card_image_url'];
			$settings['frontend_action_bg_color']     = self::sanitize_hex_setting( isset( $data['frontend_action_bg_color'] ) ? wp_unslash( $data['frontend_action_bg_color'] ) : '', $current['frontend_action_bg_color'] );
			$settings['frontend_action_text_color']   = self::sanitize_hex_setting( isset( $data['frontend_action_text_color'] ) ? wp_unslash( $data['frontend_action_text_color'] ) : '', $current['frontend_action_text_color'] );
			$settings['frontend_widget_width']        = $frontend_widget_width;
		}

		// ---- Tab: Popup --------------------------------------------------
		if ( in_array( $tab, array( 'popup', '' ), true ) ) {
			$popup_button_text = isset( $data['popup_button_text'] ) ? sanitize_text_field( wp_unslash( $data['popup_button_text'] ) ) : $current['popup_button_text'];
			if ( '' === $popup_button_text ) {
				$popup_button_text = '🍓 Reserveer je pluktijd';
			}

			$settings['popup_enabled']            = empty( $data['popup_enabled'] ) ? 0 : 1;
			$settings['popup_button_text']        = $popup_button_text;
			$settings['popup_button_bg_color']    = self::sanitize_hex_setting( isset( $data['popup_button_bg_color'] ) ? wp_unslash( $data['popup_button_bg_color'] ) : '', $current['popup_button_bg_color'] );
			$settings['popup_button_text_color']  = self::sanitize_hex_setting( isset( $data['popup_button_text_color'] ) ? wp_unslash( $data['popup_button_text_color'] ) : '', $current['popup_button_text_color'] );
			$settings['popup_button_hover_color'] = self::sanitize_hex_setting( isset( $data['popup_button_hover_color'] ) ? wp_unslash( $data['popup_button_hover_color'] ) : '', $current['popup_button_hover_color'] );
		}

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Haal een enkele instelling op.
	 *
	 * @param string $key     Sleutel.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = null ) {
		$settings = self::get_settings();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Sanitize een hexkleur met fallback.
	 *
	 * @param string $value    Ingevoerde kleur.
	 * @param string $fallback Fallback kleur.
	 * @return string
	 */
	private static function sanitize_hex_setting( $value, $fallback ) {
		$value = sanitize_text_field( $value );
		$color = sanitize_hex_color( $value );

		return $color ? $color : $fallback;
	}
}
