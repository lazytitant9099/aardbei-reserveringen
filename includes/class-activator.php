<?php
/**
 * Plugin activation.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activatie taken.
 */
class Aardbei_Reserveringen_Activator {

	/**
	 * Activeer plugin.
	 */
	public static function activate() {
		Aardbei_Reserveringen_Database::create_tables();

		if ( false === get_option( Aardbei_Reserveringen_Settings::OPTION_NAME, false ) ) {
			add_option( Aardbei_Reserveringen_Settings::OPTION_NAME, Aardbei_Reserveringen_Settings::get_default_settings() );
		}

		update_option( 'aardbei_reserveringen_version', AARDBEI_RESERVERINGEN_VERSION );
		update_option( 'aardbei_reserveringen_db_version', AARDBEI_RESERVERINGEN_DB_VERSION );

		Aardbei_Reserveringen_Cron::schedule();
	}
}
