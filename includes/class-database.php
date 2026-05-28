<?php
/**
 * Database setup.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert de eigen plugin-tabellen.
 */
class Aardbei_Reserveringen_Database {

	/**
	 * Maak of update de plugin-tabellen.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate    = $wpdb->get_charset_collate();
		$week_templates     = self::get_week_templates_table();
		$slots              = self::get_slots_table();
		$reservations       = self::get_reservations_table();
		$week_templates_sql = "CREATE TABLE {$week_templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			weekday tinyint(1) unsigned NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			capacity mediumint(8) unsigned NOT NULL,
			slot_duration_minutes smallint(5) unsigned NOT NULL DEFAULT 60,
			active tinyint(1) unsigned NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			KEY weekday (weekday),
			KEY active (active)
		) {$charset_collate};";

		$slots_sql = "CREATE TABLE {$slots} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			capacity mediumint(8) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'closed',
			manual_closed tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_from_template tinyint(1) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_slot (date, start_time, end_time),
			KEY date (date),
			KEY status (status),
			KEY manual_closed (manual_closed),
			KEY date_status (date, status)
		) {$charset_collate};";

		$reservations_sql = "CREATE TABLE {$reservations} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slot_id bigint(20) unsigned NOT NULL,
			name varchar(190) NOT NULL,
			email varchar(190) NOT NULL,
			phone varchar(50) NOT NULL,
			persons mediumint(8) unsigned NOT NULL,
			note text NULL,
			status varchar(20) NOT NULL DEFAULT 'confirmed',
			cancel_token varchar(64) NOT NULL,
			created_at datetime NOT NULL,
			cancelled_at datetime NULL,
			PRIMARY KEY  (id),
			KEY slot_id (slot_id),
			KEY status (status),
			UNIQUE KEY cancel_token (cancel_token),
			KEY slot_status (slot_id, status)
		) {$charset_collate};";

		dbDelta( $week_templates_sql );
		dbDelta( $slots_sql );
		dbDelta( $reservations_sql );

		update_option( 'aardbei_reserveringen_db_version', AARDBEI_RESERVERINGEN_DB_VERSION );
	}

	/**
	 * Tabelnaam voor week templates.
	 *
	 * @return string
	 */
	public static function get_week_templates_table() {
		global $wpdb;

		return $wpdb->prefix . 'aardbei_week_templates';
	}

	/**
	 * Tabelnaam voor slots.
	 *
	 * @return string
	 */
	public static function get_slots_table() {
		global $wpdb;

		return $wpdb->prefix . 'aardbei_slots';
	}

	/**
	 * Tabelnaam voor reserveringen.
	 *
	 * @return string
	 */
	public static function get_reservations_table() {
		global $wpdb;

		return $wpdb->prefix . 'aardbei_reservations';
	}

	/**
	 * Standaardinstellingen. Deze methode bestaat ook hier zodat activatie weinig afhankelijkheden heeft.
	 *
	 * @return array
	 */
	public static function get_settings_defaults() {
		return array(
			'opening_weekday'       => 7,
			'opening_time'          => '09:00',
			'bookable_weeks'        => 1,
			'admin_email'           => get_option( 'admin_email' ),
			'customer_mail_subject' => 'Je reservering voor aardbeien plukken',
			'admin_mail_subject'    => 'Nieuwe reservering voor aardbeien plukken',
			'cancellation_mail_admin' => 1,
			'show_remaining_capacity' => 1,
			'popup_enabled'         => 1,
			'popup_button_text'     => '🍓 Reserveer je pluktijd',
			'popup_button_bg_color' => '#cf2e2e',
			'popup_button_text_color' => '#ffffff',
			'popup_button_hover_color' => '#a82424',
			'frontend_widget_title' => get_bloginfo( 'name' ) ? get_bloginfo( 'name' ) : 'De Manderveense Aardbei',
			'frontend_reserve_button_text' => 'Pluktijd reserveren',
			'frontend_card_title'   => 'Zelfpluk',
			'frontend_card_subtitle' => 'Zelf aardbeien plukken bij ons op het veld',
			'frontend_card_image_url' => '',
			'frontend_action_bg_color' => '#171717',
			'frontend_action_text_color' => '#ffffff',
			'frontend_widget_width' => 420,
			'frontend_powered_by_text' => 'ModernVisuals',
		);
	}
}
