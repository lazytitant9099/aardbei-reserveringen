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

		self::setup_roles();

		Aardbei_Reserveringen_Cron::schedule();
	}

	/**
	 * Maak kassamedewerker rol aan en voeg aardbei_kassa capability toe aan admins.
	 */
	public static function setup_roles() {
		if ( ! get_role( 'aardbei_kassamedewerker' ) ) {
			add_role(
				'aardbei_kassamedewerker',
				__( 'Kassamedewerker', 'aardbei-reserveringen' ),
				array(
					'read'          => true,
					'aardbei_kassa' => true,
				)
			);
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'aardbei_kassa' );
		}
	}
}
