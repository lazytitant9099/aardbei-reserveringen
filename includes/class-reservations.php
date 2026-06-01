<?php
/**
 * Reservation logic.
 *
 * @package Aardbei_Reserveringen
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert reserveringen.
 */
class Aardbei_Reserveringen_Reservations {

	/**
	 * Maak een reservering.
	 *
	 * @param array $data Ruwe reserveringsdata.
	 * @return int|WP_Error
	 */
	public function create_reservation( $data ) {
		global $wpdb;

		$slot_id = isset( $data['slot_id'] ) ? absint( $data['slot_id'] ) : 0;
		$name    = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
		$email   = isset( $data['email'] ) ? sanitize_email( wp_unslash( $data['email'] ) ) : '';
		$phone   = isset( $data['phone'] ) ? sanitize_text_field( wp_unslash( $data['phone'] ) ) : '';
		$persons = isset( $data['persons'] ) ? absint( $data['persons'] ) : 0;
		$note    = isset( $data['note'] ) ? sanitize_textarea_field( wp_unslash( $data['note'] ) ) : '';

		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		$ip_key      = $ip ? 'aardbei_rl_ip_' . md5( $ip ) : '';
		$ip_count    = $ip_key ? (int) get_transient( $ip_key ) : 0;
		$email_key   = $email ? 'aardbei_rl_email_' . md5( $email ) : '';
		$email_count = $email_key ? (int) get_transient( $email_key ) : 0;

		if ( ! $this->passes_spam_check( $data ) ) {
			return new WP_Error( 'spam_detected', __( 'De reservering kon niet worden opgeslagen. Probeer het opnieuw.', 'aardbei-reserveringen' ) );
		}

		if ( $ip_key && $ip_count >= 5 ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Te veel reserveringen vanaf dit IP-adres. Probeer het later opnieuw.', 'aardbei-reserveringen' ) );
		}

		if ( $email_key && $email_count >= 3 ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Te veel reserveringen voor dit e-mailadres. Probeer het later opnieuw.', 'aardbei-reserveringen' ) );
		}

		if ( ! $slot_id ) {
			return new WP_Error( 'slot_not_found', __( 'Tijdslot niet gevonden.', 'aardbei-reserveringen' ) );
		}

		if ( $persons < 1 ) {
			return new WP_Error( 'invalid_persons', __( 'Kies minimaal 1 persoon.', 'aardbei-reserveringen' ) );
		}

		if ( '' === $name ) {
			return new WP_Error( 'invalid_name', __( 'Vul je naam in.', 'aardbei-reserveringen' ) );
		}

		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Vul een geldig e-mailadres in.', 'aardbei-reserveringen' ) );
		}

		if ( '' === $phone ) {
			return new WP_Error( 'invalid_phone', __( 'Vul je telefoonnummer in.', 'aardbei-reserveringen' ) );
		}

		$slots = new Aardbei_Reserveringen_Slots();
		if ( ! $slots->is_slot_bookable( $slot_id ) ) {
			return new WP_Error( 'slot_not_bookable', __( 'Dit tijdslot is niet meer boekbaar.', 'aardbei-reserveringen' ) );
		}

		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();
		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();

		/*
		 * Dit gebruikt een transactionele dubbele check waar MySQL/InnoDB dat ondersteunt.
		 * Als transacties niet actief zijn, blijft de tweede check vlak voor insert alsnog waardevol.
		 */
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$slot = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$slots_table} WHERE id = %d FOR UPDATE", $slot_id ),
			ARRAY_A
		);

		if ( ! $slot ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new WP_Error( 'slot_not_found', __( 'Tijdslot niet gevonden.', 'aardbei-reserveringen' ) );
		}

		$range = $slots->get_current_bookable_range();
		if ( 'open' !== $slot['status'] || (int) $slot['manual_closed'] || $slot['date'] < $range['start'] || $slot['date'] > $range['end'] || ! $slots->is_slot_in_future( $slot ) ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new WP_Error( 'slot_closed', __( 'Dit tijdslot is gesloten.', 'aardbei-reserveringen' ) );
		}

		$booked_persons = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(persons), 0) FROM {$reservations_table} WHERE slot_id = %d AND status = 'confirmed'",
				$slot_id
			)
		);

		$remaining = max( 0, (int) $slot['capacity'] - $booked_persons );
		if ( $persons > $remaining ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new WP_Error(
				'not_enough_capacity',
				sprintf(
					/* translators: %d: remaining capacity. */
					__( 'Er zijn nog maar %d plekken beschikbaar.', 'aardbei-reserveringen' ),
					$remaining
				)
			);
		}

		$cancel_token = $this->generate_cancel_token();
		$inserted     = $wpdb->insert(
			$reservations_table,
			array(
				'slot_id'      => $slot_id,
				'name'         => $name,
				'email'        => $email,
				'phone'        => $phone,
				'persons'      => $persons,
				'note'         => $note,
				'status'       => 'confirmed',
				'cancel_token' => $cancel_token,
				'created_at'   => current_time( 'mysql' ),
				'cancelled_at' => null,
			),
			array( '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return new WP_Error( 'reservation_failed', __( 'De reservering kon niet worden opgeslagen. Probeer het opnieuw.', 'aardbei-reserveringen' ) );
		}

		$reservation_id = (int) $wpdb->insert_id;
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Mark token as used to prevent replays
		$token = isset( $data['aardbei_spam_token'] ) ? sanitize_text_field( wp_unslash( $data['aardbei_spam_token'] ) ) : '';
		if ( $token ) {
			set_transient( 'aardbei_used_token_' . $token, 1, 3600 );
		}

		// Increment rate limiting transients
		if ( $ip_key ) {
			set_transient( $ip_key, $ip_count + 1, HOUR_IN_SECONDS );
		}
		if ( $email_key ) {
			set_transient( $email_key, $email_count + 1, HOUR_IN_SECONDS );
		}

		$mailer = new Aardbei_Reserveringen_Mailer();
		$mailer->send_customer_confirmation( $reservation_id );
		if ( Aardbei_Reserveringen_Settings::get_setting( 'booking_mail_admin', 1 ) ) {
			$mailer->send_admin_notification( $reservation_id );
		}

		return $reservation_id;
	}

	/**
	 * Eenvoudige spamcheck voor publieke reserveringen.
	 *
	 * @param array $data Ruwe reserveringsdata.
	 * @return bool
	 */
	private function passes_spam_check( $data ) {
		$honeypot   = isset( $data['website'] ) ? sanitize_text_field( wp_unslash( $data['website'] ) ) : '';
		$started_at = isset( $data['aardbei_started_at'] ) ? absint( $data['aardbei_started_at'] ) : 0;
		$random     = isset( $data['aardbei_spam_random'] ) ? sanitize_text_field( wp_unslash( $data['aardbei_spam_random'] ) ) : '';
		$token      = isset( $data['aardbei_spam_token'] ) ? sanitize_text_field( wp_unslash( $data['aardbei_spam_token'] ) ) : '';

		if ( '' !== trim( $honeypot ) || ! $started_at || '' === $random || '' === $token ) {
			return false;
		}

		// Enforce token reuse restriction (transient check)
		if ( get_transient( 'aardbei_used_token_' . $token ) ) {
			return false;
		}

		$expected_token = wp_hash( $started_at . '|' . $random . '|aardbei_reservation_form' );
		if ( ! hash_equals( $expected_token, $token ) ) {
			return false;
		}

		$elapsed = current_time( 'timestamp' ) - $started_at;
		// Must take at least 3 seconds, but no more than 1 hour (3600 seconds)
		return $elapsed >= 3 && $elapsed <= 3600;
	}

	/**
	 * Annuleer via token.
	 *
	 * @param string $token Token.
	 * @return bool|WP_Error
	 */
	public function cancel_reservation_by_token( $token ) {
		global $wpdb;

		$token = sanitize_text_field( wp_unslash( $token ) );
		if ( '' === $token ) {
			return new WP_Error( 'invalid_token', __( 'Deze annuleringslink is ongeldig.', 'aardbei-reserveringen' ) );
		}

		$table       = Aardbei_Reserveringen_Database::get_reservations_table();
		$reservation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE cancel_token = %s", $token ),
			ARRAY_A
		);

		if ( ! $reservation ) {
			return new WP_Error( 'invalid_token', __( 'Deze annuleringslink is ongeldig.', 'aardbei-reserveringen' ) );
		}

		if ( 'cancelled' === $reservation['status'] ) {
			return new WP_Error( 'already_cancelled', __( 'Deze reservering is al geannuleerd.', 'aardbei-reserveringen' ) );
		}

		$updated = $wpdb->update(
			$table,
			array(
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $reservation['id'] ) ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'cancel_failed', __( 'De reservering kon niet worden geannuleerd.', 'aardbei-reserveringen' ) );
		}

		if ( Aardbei_Reserveringen_Settings::get_setting( 'cancellation_mail_admin', 1 ) ) {
			$mailer = new Aardbei_Reserveringen_Mailer();
			$mailer->send_admin_cancellation_notification( absint( $reservation['id'] ) );
		}

		return true;
	}

	/**
	 * Annuleer als beheerder.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool|WP_Error
	 */
	public function cancel_reservation_by_admin( $reservation_id ) {
		global $wpdb;

		$reservation_id = absint( $reservation_id );
		if ( ! $reservation_id ) {
			return new WP_Error( 'reservation_not_found', __( 'Reservering niet gevonden.', 'aardbei-reserveringen' ) );
		}

		$table       = Aardbei_Reserveringen_Database::get_reservations_table();
		$reservation = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $reservation_id ),
			ARRAY_A
		);

		if ( ! $reservation ) {
			return new WP_Error( 'reservation_not_found', __( 'Reservering niet gevonden.', 'aardbei-reserveringen' ) );
		}

		if ( 'cancelled' === $reservation['status'] ) {
			return true;
		}

		return false !== $wpdb->update(
			$table,
			array(
				'status'       => 'cancelled',
				'cancelled_at' => current_time( 'mysql' ),
			),
			array( 'id' => $reservation_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Haal reserveringen op.
	 *
	 * @param array $args Filters.
	 * @return array
	 */
	public function get_reservations( $args = array() ) {
		global $wpdb;

		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();
		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();
		$where              = array( '1=1' );
		$params             = array();
		$limit              = isset( $args['limit'] ) ? absint( $args['limit'] ) : 100;
		$limit              = min( 500, max( 1, $limit ) );

		if ( ! empty( $args['date_from'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_from'] ) ) {
			$where[]  = 's.date >= %s';
			$params[] = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $args['date_to'] ) ) {
			$where[]  = 's.date <= %s';
			$params[] = $args['date_to'];
		}

		if ( ! empty( $args['status'] ) && in_array( $args['status'], array( 'confirmed', 'cancelled' ), true ) ) {
			$where[]  = 'r.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['slot_id'] ) ) {
			$where[]  = 'r.slot_id = %d';
			$params[] = absint( $args['slot_id'] );
		}

		$params[] = $limit;

		$sql = "SELECT r.*, s.date, s.start_time, s.end_time
			FROM {$reservations_table} r
			INNER JOIN {$slots_table} s ON s.id = r.slot_id
			WHERE " . implode( ' AND ', $where ) . '
			ORDER BY s.date DESC, s.start_time DESC, r.created_at DESC
			LIMIT %d';

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
	}

	/**
	 * Haal reserveringen voor een slot op.
	 *
	 * @param int $slot_id Slot ID.
	 * @return array
	 */
	public function get_reservations_by_slot( $slot_id ) {
		return $this->get_reservations(
			array(
				'slot_id' => absint( $slot_id ),
				'limit'   => 100,
			)
		);
	}

	/**
	 * Recente reserveringen.
	 *
	 * @param int $limit Aantal.
	 * @return array
	 */
	public function get_recent_reservations( $limit = 10 ) {
		return $this->get_reservations(
			array(
				'limit' => absint( $limit ),
			)
		);
	}

	/**
	 * Totaal personen voor slot.
	 *
	 * @param int $slot_id Slot ID.
	 * @return int
	 */
	public function get_total_persons_for_slot( $slot_id ) {
		global $wpdb;

		$table   = Aardbei_Reserveringen_Database::get_reservations_table();
		$slot_id = absint( $slot_id );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(persons), 0) FROM {$table} WHERE slot_id = %d AND status = 'confirmed'",
				$slot_id
			)
		);
	}

	/**
	 * Vergelijk deze week met vorige week (geboekte personen).
	 *
	 * @return array|null
	 */
	public function get_weekly_comparison() {
		global $wpdb;

		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();
		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();

		$today      = current_time( 'Y-m-d' );
		$this_start = date( 'Y-m-d', strtotime( 'monday this week', strtotime( $today ) ) );
		$this_end   = date( 'Y-m-d', strtotime( 'sunday this week', strtotime( $today ) ) );
		$prev_start = date( 'Y-m-d', strtotime( '-7 days', strtotime( $this_start ) ) );
		$prev_end   = date( 'Y-m-d', strtotime( '-7 days', strtotime( $this_end ) ) );

		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(r.persons), 0)
				FROM {$reservations_table} r
				INNER JOIN {$slots_table} s ON s.id = r.slot_id
				WHERE s.date BETWEEN %s AND %s AND r.status = 'confirmed'",
				$this_start,
				$this_end
			)
		);

		$last_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(r.persons), 0)
				FROM {$reservations_table} r
				INNER JOIN {$slots_table} s ON s.id = r.slot_id
				WHERE s.date BETWEEN %s AND %s AND r.status = 'confirmed'",
				$prev_start,
				$prev_end
			)
		);

		$diff = $this_week - $last_week;

		return array(
			'this_week' => $this_week,
			'last_week' => $last_week,
			'diff'      => abs( $diff ),
			'trend'     => $diff > 0 ? 'up' : ( $diff < 0 ? 'down' : 'flat' ),
		);
	}

	/**
	 * Dashboard statistieken.
	 *
	 * @return array
	 */
	public function get_dashboard_stats() {
		global $wpdb;

		$slots_helper = new Aardbei_Reserveringen_Slots();
		$range        = $slots_helper->get_current_bookable_range();
		$slots        = $slots_helper->get_slots( $range['start'], $range['end'], false );
		$total        = 0;
		$booked       = 0;
		$full_slots   = 0;
		$closed_slots = 0;

		foreach ( $slots as $slot ) {
			$total  += (int) $slot['capacity'];
			$booked += (int) $slot['booked_persons'];

			if ( (int) $slot['remaining'] <= 0 && (int) $slot['capacity'] > 0 ) {
				$full_slots++;
			}

			if ( 'closed' === $slot['status'] || (int) $slot['manual_closed'] ) {
				$closed_slots++;
			}
		}

		$reservations_table = Aardbei_Reserveringen_Database::get_reservations_table();
		$slots_table        = Aardbei_Reserveringen_Database::get_slots_table();

		$reservation_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$reservations_table} r
				INNER JOIN {$slots_table} s ON s.id = r.slot_id
				WHERE s.date >= %s AND s.date <= %s AND r.status = 'confirmed'",
				$range['start'],
				$range['end']
			)
		);

		$cancelled_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$reservations_table} r
				INNER JOIN {$slots_table} s ON s.id = r.slot_id
				WHERE s.date >= %s AND s.date <= %s AND r.status = 'cancelled'",
				$range['start'],
				$range['end']
			)
		);

		return array(
			'range'             => $range,
			'total_capacity'    => $total,
			'booked_persons'    => $booked,
			'remaining_capacity' => max( 0, $total - $booked ),
			'reservation_count' => $reservation_count,
			'full_slots'        => $full_slots,
			'closed_slots'      => $closed_slots,
			'cancelled_count'   => $cancelled_count,
		);
	}

	/**
	 * Meld klant aan bij de kassa.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool|WP_Error
	 */
	public function check_in_reservation( $reservation_id ) {
		global $wpdb;

		$reservation_id = absint( $reservation_id );
		if ( ! $reservation_id ) {
			return new WP_Error( 'reservation_not_found', __( 'Reservering niet gevonden.', 'aardbei-reserveringen' ) );
		}

		$table       = Aardbei_Reserveringen_Database::get_reservations_table();
		$reservation = $wpdb->get_row(
			$wpdb->prepare( "SELECT id, status FROM {$table} WHERE id = %d", $reservation_id ),
			ARRAY_A
		);

		if ( ! $reservation ) {
			return new WP_Error( 'reservation_not_found', __( 'Reservering niet gevonden.', 'aardbei-reserveringen' ) );
		}

		if ( 'confirmed' !== $reservation['status'] ) {
			return new WP_Error( 'reservation_not_confirmed', __( 'Alleen bevestigde reserveringen kunnen worden aangemeld.', 'aardbei-reserveringen' ) );
		}

		return false !== $wpdb->update(
			$table,
			array( 'checked_in_at' => current_time( 'mysql' ) ),
			array( 'id' => $reservation_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Maak aanmelding ongedaan.
	 *
	 * @param int $reservation_id Reservering ID.
	 * @return bool
	 */
	public function undo_check_in( $reservation_id ) {
		global $wpdb;

		$table = Aardbei_Reserveringen_Database::get_reservations_table();

		return false !== $wpdb->update(
			$table,
			array( 'checked_in_at' => null ),
			array( 'id' => absint( $reservation_id ) ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Maak uniek cancel token.
	 *
	 * @return string
	 */
	public function generate_cancel_token() {
		global $wpdb;

		$table = Aardbei_Reserveringen_Database::get_reservations_table();

		for ( $attempt = 0; $attempt < 10; $attempt++ ) {
			$token  = wp_generate_password( 48, false, false );
			$exists = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE cancel_token = %s", $token )
			);

			if ( ! $exists ) {
				return $token;
			}
		}

		return wp_hash( wp_generate_uuid4() . microtime( true ) );
	}
}
