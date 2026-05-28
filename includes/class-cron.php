<?php
/**
 * Cron handling.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plant en draait automatische slotgeneratie.
 */
class Aardbei_Reserveringen_Cron {

	const HOOK = 'aardbei_reserveringen_generate_slots';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'cron_schedules', array( $this, 'add_custom_schedule' ) );
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Voeg een dagelijkse schedule toe als fallback.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public function add_custom_schedule( $schedules ) {
		if ( ! isset( $schedules['aardbei_daily'] ) ) {
			$schedules['aardbei_daily'] = array(
				'interval' => DAY_IN_SECONDS,
				'display'  => __( 'Aardbei dagelijks', 'aardbei-reserveringen' ),
			);
		}

		return $schedules;
	}

	/**
	 * Plan cron als die nog niet bestaat.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * Verwijder geplande cron.
	 */
	public static function unschedule() {
		$timestamp = wp_next_scheduled( self::HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::HOOK );
			$timestamp = wp_next_scheduled( self::HOOK );
		}
	}

	/**
	 * Draai automatische generatie.
	 */
	public function run() {
		$slots = new Aardbei_Reserveringen_Slots();

		/*
		 * De boekbare periode wordt bepaald door opening_weekday/opening_time.
		 * Deze run mag dagelijks opnieuw komen: de unieke database-index voorkomt dubbele slots.
		 */
		$slots->generate_slots_for_next_bookable_week();
	}
}
