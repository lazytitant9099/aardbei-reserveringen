<?php
/**
 * Slot logic.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert tijdsloten.
 */
class Aardbei_Reserveringen_Slots {

	/**
	 * Haal tijdsloten op.
	 *
	 * @param string|null $start_date Startdatum Y-m-d.
	 * @param string|null $end_date   Einddatum Y-m-d.
	 * @param bool        $public_only Alleen publiek boekbare slots.
	 * @return array
	 */
	public function get_slots( $start_date = null, $end_date = null, $public_only = false ) {
		global $wpdb;

		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();
		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();
		$where              = array( '1=1' );
		$params             = array();

		$start_date = $this->sanitize_date( $start_date );
		$end_date   = $this->sanitize_date( $end_date );

		if ( $public_only ) {
			$range = $this->get_current_bookable_range();

			if ( ! $start_date || $start_date < $range['start'] ) {
				$start_date = $range['start'];
			}

			if ( ! $end_date || $end_date > $range['end'] ) {
				$end_date = $range['end'];
			}

			$where[] = "s.status = 'open'";
			$where[] = 's.manual_closed = 0';
		}

		if ( $start_date ) {
			$where[]  = 's.date >= %s';
			$params[] = $start_date;
		}

		if ( $end_date ) {
			$where[]  = 's.date <= %s';
			$params[] = $end_date;
		}

		$sql = "SELECT s.*, COALESCE(b.booked_persons, 0) AS booked_persons
			FROM {$slots_table} s
			LEFT JOIN (
				SELECT slot_id, SUM(persons) AS booked_persons
				FROM {$reservations_table}
				WHERE status = 'confirmed'
				GROUP BY slot_id
			) b ON b.slot_id = s.id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY s.date ASC, s.start_time ASC';

		$prepared = $params ? $wpdb->prepare( $sql, $params ) : $sql; // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$slots    = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $slots ) {
			return array();
		}

		foreach ( $slots as $index => $slot ) {
			$booked    = (int) $slot['booked_persons'];
			$capacity  = (int) $slot['capacity'];
			$remaining = max( 0, $capacity - $booked );

			$slots[ $index ]['booked_persons'] = $booked;
			$slots[ $index ]['remaining']      = $remaining;

			if ( $public_only && $remaining <= 0 ) {
				unset( $slots[ $index ] );
			}
		}

		return array_values( $slots );
	}

	/**
	 * Haal een enkel slot op.
	 *
	 * @param int $slot_id Slot ID.
	 * @return array|null
	 */
	public function get_slot( $slot_id ) {
		global $wpdb;

		$slot_id = absint( $slot_id );
		if ( ! $slot_id ) {
			return null;
		}

		$table = Aardbei_Reserveringen_Database::get_slots_table();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $slot_id ),
			ARRAY_A
		);
	}

	/**
	 * Capaciteitsinformatie voor een slot.
	 *
	 * @param int $slot_id Slot ID.
	 * @return array|null
	 */
	public function get_slot_capacity_info( $slot_id ) {
		global $wpdb;

		$slot_id = absint( $slot_id );
		if ( ! $slot_id ) {
			return null;
		}

		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();
		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT s.*, COALESCE(b.booked_persons, 0) AS booked_persons
				FROM {$slots_table} s
				LEFT JOIN (
					SELECT slot_id, SUM(persons) AS booked_persons
					FROM {$reservations_table}
					WHERE status = 'confirmed'
					GROUP BY slot_id
				) b ON b.slot_id = s.id
				WHERE s.id = %d",
				$slot_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['booked_persons'] = (int) $row['booked_persons'];
		$row['capacity']       = (int) $row['capacity'];
		$row['remaining']      = max( 0, $row['capacity'] - $row['booked_persons'] );

		return $row;
	}

	/**
	 * Maak een handmatig tijdslot.
	 *
	 * @param array $data Slotdata.
	 * @return int|WP_Error
	 */
	public function create_slot( $data ) {
		global $wpdb;

		$date       = isset( $data['date'] ) ? $this->sanitize_date( $data['date'] ) : '';
		$start_time = isset( $data['start_time'] ) ? $this->sanitize_time( $data['start_time'] ) : '';
		$end_time   = isset( $data['end_time'] ) ? $this->sanitize_time( $data['end_time'] ) : '';
		$capacity   = isset( $data['capacity'] ) ? absint( $data['capacity'] ) : 0;
		$status     = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'open';

		if ( ! $date || ! $start_time || ! $end_time || $capacity < 1 ) {
			return new WP_Error( 'invalid_slot', __( 'Vul een geldige datum, tijd en capaciteit in.', 'aardbei-reserveringen' ) );
		}

		if ( $end_time <= $start_time ) {
			return new WP_Error( 'invalid_time', __( 'De eindtijd moet na de starttijd liggen.', 'aardbei-reserveringen' ) );
		}

		if ( ! in_array( $status, array( 'open', 'closed' ), true ) ) {
			$status = 'open';
		}

		$table = Aardbei_Reserveringen_Database::get_slots_table();
		$now   = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			$table,
			array(
				'date'                  => $date,
				'start_time'            => $start_time,
				'end_time'              => $end_time,
				'capacity'              => $capacity,
				'status'                => $status,
				'manual_closed'         => 'closed' === $status ? 1 : 0,
				'created_from_template' => 0,
				'created_at'            => $now,
				'updated_at'            => null,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'slot_exists', __( 'Dit tijdslot bestaat al of kon niet worden opgeslagen.', 'aardbei-reserveringen' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Pas capaciteit aan.
	 *
	 * @param int $slot_id  Slot ID.
	 * @param int $capacity Nieuwe capaciteit.
	 * @return bool|WP_Error
	 */
	public function update_slot_capacity( $slot_id, $capacity ) {
		global $wpdb;

		$slot_id  = absint( $slot_id );
		$capacity = absint( $capacity );

		if ( ! $slot_id || $capacity < 1 ) {
			return new WP_Error( 'invalid_capacity', __( 'Vul een geldige capaciteit in.', 'aardbei-reserveringen' ) );
		}

		$info = $this->get_slot_capacity_info( $slot_id );
		if ( ! $info ) {
			return new WP_Error( 'slot_not_found', __( 'Tijdslot niet gevonden.', 'aardbei-reserveringen' ) );
		}

		if ( $capacity < (int) $info['booked_persons'] ) {
			return new WP_Error( 'capacity_too_low', __( 'De capaciteit kan niet lager zijn dan het aantal geboekte personen.', 'aardbei-reserveringen' ) );
		}

		$table = Aardbei_Reserveringen_Database::get_slots_table();

		return false !== $wpdb->update(
			$table,
			array(
				'capacity'   => $capacity,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $slot_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Sluit een tijdslot.
	 *
	 * @param int  $slot_id Slot ID.
	 * @param bool $manual  Handmatig gesloten.
	 * @return bool
	 */
	public function close_slot( $slot_id, $manual = true ) {
		global $wpdb;

		$slot_id = absint( $slot_id );
		if ( ! $slot_id ) {
			return false;
		}

		$table = Aardbei_Reserveringen_Database::get_slots_table();

		return false !== $wpdb->update(
			$table,
			array(
				'status'        => 'closed',
				'manual_closed' => $manual ? 1 : 0,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $slot_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Open een tijdslot.
	 *
	 * @param int $slot_id Slot ID.
	 * @return bool
	 */
	public function open_slot( $slot_id ) {
		global $wpdb;

		$slot_id = absint( $slot_id );
		if ( ! $slot_id ) {
			return false;
		}

		$table = Aardbei_Reserveringen_Database::get_slots_table();

		return false !== $wpdb->update(
			$table,
			array(
				'status'        => 'open',
				'manual_closed' => 0,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => $slot_id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Genereer slots voor de huidige boekbare periode.
	 *
	 * @return int Aantal nieuw aangemaakte slots.
	 */
	public function generate_slots_for_next_bookable_week() {
		$range          = $this->get_current_bookable_range();
		$settings       = Aardbei_Reserveringen_Settings::get_settings();
		$bookable_weeks = max( 1, absint( $settings['bookable_weeks'] ) );
		$total          = 0;
		$monday         = new DateTimeImmutable( $range['start'], $this->get_timezone() );

		for ( $week = 0; $week < $bookable_weeks; $week++ ) {
			$total += $this->generate_slots_for_week( $monday->modify( '+' . $week . ' weeks' )->format( 'Y-m-d' ) );
		}

		return $total;
	}

	/**
	 * Genereer slots voor een week op basis van maandag.
	 *
	 * @param string $monday_date Maandag Y-m-d.
	 * @return int Aantal nieuw aangemaakte slots.
	 */
	public function generate_slots_for_week( $monday_date ) {
		global $wpdb;

		$monday_date = $this->sanitize_date( $monday_date );
		if ( ! $monday_date ) {
			return 0;
		}

		$templates_table = Aardbei_Reserveringen_Database::get_week_templates_table();
		$slots_table     = Aardbei_Reserveringen_Database::get_slots_table();
		$templates       = $wpdb->get_results( "SELECT * FROM {$templates_table} WHERE active = 1 ORDER BY weekday ASC, start_time ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( ! $templates ) {
			return 0;
		}

		$created = 0;
		$monday  = new DateTimeImmutable( $monday_date, $this->get_timezone() );
		$now     = current_time( 'mysql' );

		foreach ( $templates as $template ) {
			$weekday = absint( $template['weekday'] );
			if ( $weekday < 1 || $weekday > 7 ) {
				continue;
			}

			$date             = $monday->modify( '+' . ( $weekday - 1 ) . ' days' )->format( 'Y-m-d' );
			$duration_minutes = isset( $template['slot_duration_minutes'] ) ? absint( $template['slot_duration_minutes'] ) : 60;

			if ( ! in_array( $duration_minutes, array( 15, 30, 45, 60, 90, 120 ), true ) ) {
				$duration_minutes = 60;
			}

			$slot_start = new DateTimeImmutable( $date . ' ' . $template['start_time'], $this->get_timezone() );
			$range_end  = new DateTimeImmutable( $date . ' ' . $template['end_time'], $this->get_timezone() );

			while ( $slot_start < $range_end ) {
				$slot_end = $slot_start->modify( '+' . $duration_minutes . ' minutes' );

				if ( $slot_end > $range_end ) {
					break;
				}

				$inserted = $wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO {$slots_table}
						(date, start_time, end_time, capacity, status, manual_closed, created_from_template, created_at, updated_at)
						VALUES (%s, %s, %s, %d, %s, %d, %d, %s, NULL)",
						$date,
						$slot_start->format( 'H:i:s' ),
						$slot_end->format( 'H:i:s' ),
						absint( $template['capacity'] ),
						'open',
						0,
						1,
						$now
					)
				);

				if ( $inserted ) {
					$created++;
				}

				$slot_start = $slot_end;
			}
		}

		return $created;
	}

	/**
	 * Bepaal huidige boekbare periode.
	 *
	 * @return array
	 */
	public function get_current_bookable_range() {
		$settings        = Aardbei_Reserveringen_Settings::get_settings();
		$opening_weekday = min( 7, max( 1, absint( $settings['opening_weekday'] ) ) );
		$opening_time    = preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $settings['opening_time'] ) ? $settings['opening_time'] : '09:00';
		$bookable_weeks  = max( 1, absint( $settings['bookable_weeks'] ) );
		$timezone        = $this->get_timezone();
		$now             = new DateTimeImmutable( 'now', $timezone );
		$today           = $now->setTime( 0, 0, 0 );
		$current_weekday = (int) $now->format( 'N' );
		$days_since_open = ( $current_weekday - $opening_weekday + 7 ) % 7;
		$time_parts      = explode( ':', $opening_time );

		$opening_moment = $today
			->modify( '-' . $days_since_open . ' days' )
			->setTime( (int) $time_parts[0], (int) $time_parts[1], 0 );

		if ( $now < $opening_moment ) {
			$opening_moment = $opening_moment->modify( '-7 days' );
		}

		/*
		 * Vanaf het laatste openingsmoment wordt de eerstvolgende maandag boekbaar.
		 * Bij de standaardinstelling zondag 09:00 is dat dus de maandag erna.
		 */
		$opening_day_number = (int) $opening_moment->format( 'N' );
		$days_until_monday  = ( 8 - $opening_day_number ) % 7;
		if ( 0 === $days_until_monday ) {
			$days_until_monday = 7;
		}

		$start = $opening_moment->modify( '+' . $days_until_monday . ' days' )->setTime( 0, 0, 0 );
		$end   = $start->modify( '+' . ( ( $bookable_weeks * 7 ) - 1 ) . ' days' );

		return array(
			'start' => $start->format( 'Y-m-d' ),
			'end'   => $end->format( 'Y-m-d' ),
		);
	}

	/**
	 * Is dit slot publiek boekbaar?
	 *
	 * @param int $slot_id Slot ID.
	 * @return bool
	 */
	public function is_slot_bookable( $slot_id ) {
		$slot = $this->get_slot_capacity_info( $slot_id );

		if ( ! $slot ) {
			return false;
		}

		if ( 'open' !== $slot['status'] || (int) $slot['manual_closed'] ) {
			return false;
		}

		$range = $this->get_current_bookable_range();
		if ( $slot['date'] < $range['start'] || $slot['date'] > $range['end'] ) {
			return false;
		}

		return (int) $slot['remaining'] > 0;
	}

	/**
	 * Valideer datum.
	 *
	 * @param string|null $date Datum.
	 * @return string
	 */
	private function sanitize_date( $date ) {
		$date = is_string( $date ) ? sanitize_text_field( wp_unslash( $date ) ) : '';

		return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ? $date : '';
	}

	/**
	 * Valideer tijd.
	 *
	 * @param string|null $time Tijd.
	 * @return string
	 */
	private function sanitize_time( $time ) {
		$time = is_string( $time ) ? sanitize_text_field( wp_unslash( $time ) ) : '';

		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $time ) ) {
			return $time . ':00';
		}

		if ( preg_match( '/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time ) ) {
			return $time;
		}

		return '';
	}

	/**
	 * WordPress tijdzone.
	 *
	 * @return DateTimeZone
	 */
	private function get_timezone() {
		if ( function_exists( 'wp_timezone' ) ) {
			return wp_timezone();
		}

		return new DateTimeZone( 'UTC' );
	}
}
