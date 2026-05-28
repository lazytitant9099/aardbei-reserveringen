<?php
/**
 * Plugin deactivation.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deactivatie taken.
 */
class Aardbei_Reserveringen_Deactivator {

	/**
	 * Deactiveer plugin.
	 */
	public static function deactivate() {
		Aardbei_Reserveringen_Cron::unschedule();
	}
}
